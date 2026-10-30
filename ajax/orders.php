<?php
// ajax/orders.php - Order Cancellation & Refund Processing API
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/accounting_engine.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. CANCEL ORDER (PRD 6.4)
if ($action === 'cancel_order') {
    requirePermission('orders.cancel');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if (empty($reason)) jsonResponse(['success' => false, 'message' => 'Cancellation reason is required'], 400);

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new Exception("Order not found.");

        if ($order['order_status'] === 'cancelled') {
            throw new Exception("Order is already cancelled.");
        }

        // Cancel order
        $db->prepare("
            UPDATE orders 
            SET order_status = 'cancelled', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ")->execute([$reason, $userId, $orderId]);

        // Release table if any
        if (!empty($order['table_id'])) {
            $db->prepare("UPDATE restaurant_tables SET status = 'available', current_order_id = NULL WHERE id = ?")->execute([$order['table_id']]);
        }

        $db->commit();
        logAudit('cancel_order', 'orders', $orderId, ['status' => $order['order_status']], ['status' => 'cancelled', 'reason' => $reason]);

        jsonResponse(['success' => true, 'message' => "Order #{$order['order_number']} has been cancelled."]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 2. PROCESS REFUND (PRD 12.7)
if ($action === 'process_refund') {
    requirePermission('orders.refund');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $refundMethodId = (int)($_POST['refund_method_id'] ?? 1);
    $reason = trim($_POST['reason'] ?? '');
    $restoreInventory = !empty($_POST['restore_inventory']) ? 1 : 0;

    if ($amount <= 0 || empty($reason)) {
        jsonResponse(['success' => false, 'message' => 'Valid refund amount and reason are required'], 400);
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new Exception("Order not found.");

        if ((float)$order['paid_amount'] < $amount) {
            throw new Exception("Refund amount cannot exceed total paid amount (" . formatCurrency($order['paid_amount']) . ")");
        }

        $refundNumber = 'REF-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $stmt = $db->prepare("
            INSERT INTO refunds (branch_id, refund_number, order_id, amount, reason, authorized_by, refund_method_id, restore_inventory, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$branchId, $refundNumber, $orderId, $amount, $reason, $userId, $refundMethodId, $restoreInventory]);
        $refundId = (int)$db->lastInsertId();

        // Update order status
        $newPaid = (float)$order['paid_amount'] - $amount;
        $db->prepare("UPDATE orders SET paid_amount = ?, payment_status = 'refunded', updated_at = NOW() WHERE id = ?")->execute([$newPaid, $orderId]);

        // Post accounting entry for refund
        AccountingEngine::postRefundJournal($refundId, $userId);

        $db->commit();
        logAudit('refund_processed', 'refunds', $refundId, null, [
            'order_id' => $orderId,
            'refund_number' => $refundNumber,
            'amount' => $amount
        ]);

        jsonResponse([
            'success' => true,
            'message' => "Refund #$refundNumber for " . formatCurrency($amount) . " processed successfully!",
            'refund_id' => $refundId
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
