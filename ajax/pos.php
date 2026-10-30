<?php
// ajax/pos.php - Backend AJAX API for Restaurant POS Terminal
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory_engine.php';
require_once __DIR__ . '/../includes/accounting_engine.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized session.'], 401);
}

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$branchId = currentBranchId();
$userId = currentUserId();

// 1. GET FULL MENU (Categories, Items, Variants, Modifiers)
if ($action === 'get_menu') {
    $categories = $db->query("SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();
    
    $items = $db->query("
        SELECT m.*, c.name as category_name 
        FROM menu_items m 
        JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.is_active = 1 AND m.is_available = 1 
        ORDER BY m.is_popular DESC, m.name ASC
    ")->fetchAll();

    $variants = $db->query("SELECT * FROM menu_variants WHERE is_active = 1 ORDER BY menu_item_id, final_price ASC")->fetchAll();
    $variantsByItem = [];
    foreach ($variants as $v) {
        $variantsByItem[$v['menu_item_id']][] = $v;
    }

    $modGroups = $db->query("SELECT * FROM modifier_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    $modifiers = $db->query("SELECT * FROM modifiers WHERE is_active = 1 ORDER BY group_id, price ASC")->fetchAll();
    $modsByGroup = [];
    foreach ($modifiers as $m) {
        $modsByGroup[$m['group_id']][] = $m;
    }
    $modGroupsById = [];
    foreach ($modGroups as &$g) {
        $g['options'] = $modsByGroup[$g['id']] ?? [];
        $modGroupsById[$g['id']] = $g;
    }
    unset($g);

    // Fetch Category Modifier Groups mapping
    $catModRows = $db->query("SELECT category_id, modifier_group_id FROM menu_category_modifier_groups ORDER BY sort_order ASC")->fetchAll();
    $catModGroups = [];
    foreach ($catModRows as $row) {
        $catModGroups[$row['category_id']][] = (int)$row['modifier_group_id'];
    }

    // Fetch Item-Specific Modifier Groups mapping
    $itemModRows = $db->query("SELECT menu_item_id, modifier_group_id FROM menu_item_modifier_groups ORDER BY sort_order ASC")->fetchAll();
    $itemModGroups = [];
    foreach ($itemModRows as $row) {
        $itemModGroups[$row['menu_item_id']][] = (int)$row['modifier_group_id'];
    }

    // Attach variants and applicable modifier groups to each item
    foreach ($items as &$it) {
        $it['variants'] = $variantsByItem[$it['id']] ?? [];

        // Resolution rule:
        //   - If item has explicit group assignments → use ONLY those (item overrides category)
        //   - If no item-level assignments → fall back to category defaults
        $directGroupIds = $itemModGroups[$it['id']] ?? null;
        if ($directGroupIds !== null) {
            // Item has explicit selection (even if empty = intentionally no add-ons)
            $effectiveGroupIds = $directGroupIds;
        } else {
            // Fall back to category defaults
            $effectiveGroupIds = $catModGroups[$it['category_id']] ?? [];
        }

        $dishGroups = [];
        foreach ($effectiveGroupIds as $gId) {
            if (isset($modGroupsById[$gId])) {
                $dishGroups[] = $modGroupsById[$gId];
            }
        }
        $it['modifier_groups'] = $dishGroups;
    }
    unset($it);

    jsonResponse([
        'success' => true,
        'categories' => $categories,
        'items' => $items,
        'modifier_groups' => $modGroups
    ]);
}

// 2. SAVE OR UPDATE ORDER (Draft or Submitted)
if ($action === 'save_order') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['items'])) {
        jsonResponse(['success' => false, 'message' => 'No items in order.'], 400);
    }

    $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;
    $orderTypeId = (int)($data['order_type_id'] ?? 1);
    $tableId = !empty($data['table_id']) ? (int)$data['table_id'] : null;
    $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : 1;
    $notes = $data['notes'] ?? '';
    $discountAmount = (float)($data['discount_amount'] ?? 0);
    $taxPercent = (float)($data['tax_percent'] ?? 0);
    $serviceChargePercent = (float)($data['service_charge_percent'] ?? 0);
    $deliveryFee = (float)($data['delivery_fee'] ?? 0);

    // Active shift
    $shift = getActiveCashShift($branchId);
    $shiftId = $shift ? (int)$shift['id'] : null;

    try {
        $db->beginTransaction();

        $subtotal = 0.00;
        foreach ($data['items'] as $item) {
            $qty = (int)$item['quantity'];
            $price = (float)$item['unit_price'];
            $modTotal = 0.00;
            if (!empty($item['modifiers'])) {
                foreach ($item['modifiers'] as $m) {
                    $modTotal += (float)$m['price'];
                }
            }
            $subtotal += ($price + $modTotal) * $qty;
        }

        $taxAmount = round(($subtotal - $discountAmount) * ($taxPercent / 100), 2);
        if ($taxAmount < 0) $taxAmount = 0.00;

        $serviceAmount = round(($subtotal - $discountAmount) * ($serviceChargePercent / 100), 2);
        if ($serviceAmount < 0) $serviceAmount = 0.00;

        $grandTotal = round($subtotal - $discountAmount + $taxAmount + $serviceAmount + $deliveryFee, 2);

        $customerName = !empty($data['customer_name']) ? cleanInput($data['customer_name']) : null;
        $customerPhone = !empty($data['customer_phone']) ? cleanInput($data['customer_phone']) : null;

        if (!$orderId) {
            $prefix = getSetting('order_prefix', 'ORD-');
            $orderNumber = $prefix . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

            $stmt = $db->prepare("
                INSERT INTO orders (
                    branch_id, shift_id, order_number, order_type_id, table_id, customer_id, 
                    customer_name, customer_phone, cashier_id, subtotal, discount_amount, 
                    tax_percent, tax_amount, service_charge_percent, service_charge_amount, 
                    delivery_fee, grand_total, order_status, kitchen_status, payment_status, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'pending', 'unpaid', ?, NOW())
            ");
            $stmt->execute([
                $branchId, $shiftId, $orderNumber, $orderTypeId, $tableId, $customerId,
                $customerName, $customerPhone, $userId, $subtotal, $discountAmount,
                $taxPercent, $taxAmount, $serviceChargePercent, $serviceAmount,
                $deliveryFee, $grandTotal, $notes
            ]);
            $orderId = (int)$db->lastInsertId();

            // Update table status if dine-in
            if ($tableId) {
                $db->prepare("UPDATE restaurant_tables SET status = 'occupied', current_order_id = ? WHERE id = ?")->execute([$orderId, $tableId]);
            }
        } else {
            // Update existing order
            $stmt = $db->prepare("
                SELECT order_number FROM orders WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            $orderNumber = $stmt->fetchColumn();

            $upStmt = $db->prepare("
                UPDATE orders SET 
                    order_type_id = ?, table_id = ?, customer_id = ?, 
                    customer_name = COALESCE(?, customer_name), customer_phone = COALESCE(?, customer_phone),
                    subtotal = ?, discount_amount = ?, tax_percent = ?, tax_amount = ?, 
                    service_charge_percent = ?, service_charge_amount = ?, 
                    delivery_fee = ?, grand_total = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $upStmt->execute([
                $orderTypeId, $tableId, $customerId, 
                $customerName, $customerPhone,
                $subtotal, $discountAmount,
                $taxPercent, $taxAmount, $serviceChargePercent, $serviceAmount,
                $deliveryFee, $grandTotal, $notes, $orderId
            ]);

            // Clear old items to replace cleanly
            $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
        }

        // Insert Order Items and Modifiers
        $itemStmt = $db->prepare("
            INSERT INTO order_items (
                order_id, menu_item_id, menu_variant_id, item_name, variant_name, 
                kitchen_station_id, quantity, unit_price, subtotal, special_instructions, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $modStmt = $db->prepare("
            INSERT INTO order_item_modifiers (order_item_id, modifier_id, modifier_name, price)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($data['items'] as $item) {
            $qty = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $itemSubtotal = $unitPrice * $qty;
            $stationId = !empty($item['kitchen_station_id']) ? (int)$item['kitchen_station_id'] : 1;

            $itemStmt->execute([
                $orderId,
                $item['menu_item_id'],
                $item['menu_variant_id'] ?? null,
                $item['name'],
                $item['variant_name'] ?? null,
                $stationId,
                $qty,
                $unitPrice,
                $itemSubtotal,
                $item['instructions'] ?? null
            ]);
            $orderItemId = (int)$db->lastInsertId();

            if (!empty($item['modifiers'])) {
                foreach ($item['modifiers'] as $m) {
                    $modStmt->execute([$orderItemId, $m['id'], $m['name'], $m['price']]);
                }
            }
        }

        // If delivery order, create or update delivery assignment
        if ($orderTypeId == 3) {
            $zoneId = !empty($data['delivery_zone_id']) ? (int)$data['delivery_zone_id'] : null;
            $riderId = !empty($data['rider_id']) ? (int)$data['rider_id'] : 1;
            
            $chkDel = $db->prepare("SELECT id FROM delivery_assignments WHERE order_id = ?");
            $chkDel->execute([$orderId]);
            if (!$chkDel->fetch()) {
                $db->prepare("
                    INSERT INTO delivery_assignments (order_id, rider_id, zone_id, delivery_fee, status, assigned_at)
                    VALUES (?, ?, ?, ?, 'assigned', NOW())
                ")->execute([$orderId, $riderId, $zoneId, $deliveryFee]);
            }
        }

        $db->commit();

        logAudit('save_order', 'orders', $orderId, null, ['order_number' => $orderNumber, 'total' => $grandTotal]);

        jsonResponse([
            'success' => true,
            'message' => "Order #$orderNumber saved successfully.",
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'grand_total' => $grandTotal
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// 3. SEND TO KITCHEN (Generates KOT tickets)
if ($action === 'send_to_kitchen') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if (!$orderId) jsonResponse(['success' => false, 'message' => 'Invalid order ID.'], 400);

    try {
        $db->beginTransaction();
        $createdTickets = sendOrderToKitchen($db, $orderId);
        $db->prepare("UPDATE orders SET order_status = 'sent_to_kitchen' WHERE id = ? AND order_status IN ('draft', 'confirmed')")->execute([$orderId]);
        $db->commit();
        jsonResponse([
            'success' => true,
            'message' => 'Order sent to Kitchen Display!',
            'tickets' => $createdTickets
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// 4. PROCESS PAYMENT (Supports Split Payments & Customer Credit)
if ($action === 'process_payment') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['order_id']) || empty($data['payments'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid payment payload.'], 400);
    }

    $orderId = (int)$data['order_id'];
    $payments = $data['payments']; // [['method_id' => 1, 'amount' => 500, 'tendered' => 1000, 'change' => 500]]
    $shift = getActiveCashShift($branchId);
    $shiftId = $shift ? (int)$shift['id'] : null;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new Exception("Order not found.");

        $totalPaidNow = 0.00;
        $payStmt = $db->prepare("
            INSERT INTO payments (branch_id, shift_id, order_id, payment_method_id, payment_number, amount, tendered_amount, change_returned, reference_number, received_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($payments as $p) {
            $methodId = (int)$p['method_id'];
            $amount = (float)$p['amount'];
            $tendered = (float)($p['tendered'] ?? $amount);
            $change = (float)($p['change'] ?? 0.00);
            $ref = $p['reference'] ?? null;

            if ($amount <= 0) continue;

            $payNumber = 'PAY-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $payStmt->execute([
                $branchId, $shiftId, $orderId, $methodId, $payNumber, $amount, $tendered, $change, $ref, $userId
            ]);
            $totalPaidNow += $amount;

            // If Customer Credit (Method ID 4)
            if ($methodId === 4) {
                $custId = (int)($order['customer_id'] ?? 1);
                if ($custId === 1) {
                    throw new Exception("Walk-in customer cannot purchase on credit. Please select a registered account customer to use Customer Credit.");
                }
                $cRow = $db->query("SELECT id, name, credit_limit, current_balance FROM customers WHERE id = $custId")->fetch();
                if (!$cRow || (float)$cRow['credit_limit'] <= 0) {
                    throw new Exception("Customer " . ($cRow ? $cRow['name'] : '') . " does not have an approved credit limit.");
                }
                if (((float)$cRow['current_balance'] + $amount) > (float)$cRow['credit_limit']) {
                    throw new Exception("Credit limit exceeded for {$cRow['name']}. Limit: Rs. " . number_format($cRow['credit_limit'], 2) . ", Current Balance: Rs. " . number_format($cRow['current_balance'], 2));
                }

                // Update customer balance and ledger
                $db->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $custId]);
                $db->prepare("
                    INSERT INTO customer_ledger (customer_id, transaction_type, reference_type, reference_id, debit, credit, balance, notes, created_at)
                    VALUES (?, 'sale_credit', 'orders', ?, ?, 0.00, (SELECT current_balance FROM customers WHERE id = ?), ?, NOW())
                ")->execute([$custId, $orderId, $amount, $custId, "Credit Sale Order #" . $order['order_number']]);
            }
        }

        $newPaidAmount = (float)$order['paid_amount'] + $totalPaidNow;
        $grandTotal = (float)$order['grand_total'];
        $paymentStatus = ($newPaidAmount >= ($grandTotal - 0.01)) ? 'paid' : 'partially_paid';

        // Automatically send to Kitchen KDS (generates KOT tickets and sets kitchen_status = 'preparing')
        sendOrderToKitchen($db, $orderId);

        $currKitchen = $db->query("SELECT kitchen_status FROM orders WHERE id = $orderId")->fetchColumn();
        $orderStatus = ($paymentStatus === 'paid' && $currKitchen === 'completed') ? 'completed' : 'preparing';

        $custName = !empty($data['customer_name']) ? cleanInput($data['customer_name']) : null;
        $custPhone = !empty($data['customer_phone']) ? cleanInput($data['customer_phone']) : null;

        // Update Order
        $db->prepare("
            UPDATE orders 
            SET paid_amount = ?, payment_status = ?, order_status = ?, 
                customer_name = COALESCE(?, customer_name), customer_phone = COALESCE(?, customer_phone),
                updated_at = NOW() 
            WHERE id = ?
        ")->execute([$newPaidAmount, $paymentStatus, $orderStatus, $custName, $custPhone, $orderId]);

        // Release Table if paid
        if (!empty($order['table_id']) && $paymentStatus === 'paid') {
            $db->prepare("UPDATE restaurant_tables SET status = 'available', current_order_id = NULL WHERE id = ?")->execute([$order['table_id']]);
        }

        // Award Loyalty Points if paid and customer exists
        if (!empty($order['customer_id']) && $order['customer_id'] > 1 && $paymentStatus === 'paid') {
            $earnedPoints = (int)floor($grandTotal / 100); // 1 point per 100 Rs
            if ($earnedPoints > 0) {
                $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$earnedPoints, $order['customer_id']]);
            }
        }

        // 5. INVENTORY CONSUMPTION (Recipe Engine)
        $cogs = InventoryEngine::consumeStockForOrder($orderId, $branchId, $userId);

        // 6. DOUBLE-ENTRY ACCOUNTING POSTING
        AccountingEngine::postSaleJournal($orderId, $userId);
        AccountingEngine::postCogsJournal($orderId, $cogs, $userId, $branchId);

        $db->commit();

        logAudit('payment_received', 'orders', $orderId, null, [
            'order_number' => $order['order_number'],
            'amount_paid' => $totalPaidNow,
            'status' => $paymentStatus
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Payment recorded and order completed successfully!',
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'payment_status' => $paymentStatus
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// 5. GET HELD / ACTIVE ORDERS (For resuming tabs)
if ($action === 'get_held_orders') {
    $orders = $db->query("
        SELECT o.*, t.table_number, c.name as customer_name, c.phone as customer_phone, ot.name as order_type_name 
        FROM orders o 
        LEFT JOIN restaurant_tables t ON o.table_id = t.id 
        LEFT JOIN customers c ON o.customer_id = c.id 
        JOIN order_types ot ON o.order_type_id = ot.id 
        WHERE o.branch_id = $branchId AND o.payment_status != 'paid' AND o.order_status != 'cancelled' 
        ORDER BY o.id DESC LIMIT 20
    ")->fetchAll();

    jsonResponse(['success' => true, 'orders' => $orders]);
}

// 6. LOAD SINGLE ORDER DETAILS
if ($action === 'get_order_details') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    $order = $db->query("SELECT * FROM orders WHERE id = $orderId")->fetch();
    if (!$order) jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);

    $items = $db->query("SELECT * FROM order_items WHERE order_id = $orderId")->fetchAll();
    foreach ($items as &$it) {
        $it['modifiers'] = $db->query("SELECT * FROM order_item_modifiers WHERE order_item_id = " . $it['id'])->fetchAll();
    }
    $order['items'] = $items;

    jsonResponse(['success' => true, 'order' => $order]);
}

jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
