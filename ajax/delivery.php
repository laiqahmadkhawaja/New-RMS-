<?php
// ajax/delivery.php - Delivery Dispatch & Rider Cash Reconciliation API (PRD 13)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. UPDATE DELIVERY STATUS (Assigned -> Picked Up -> Delivered -> Cash Handover)
if ($action === 'update_delivery_status') {
    requirePermission('delivery.manage');
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $status = $_POST['status'] ?? ''; // 'picked_up', 'delivered', 'failed'

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM delivery_assignments WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $da = $stmt->fetch();
        if (!$da) throw new Exception("Delivery record not found.");

        $timeField = $status === 'picked_up' ? 'picked_up_at = NOW(),' : ($status === 'delivered' ? 'delivered_at = NOW(),' : '');

        $db->prepare("
            UPDATE delivery_assignments 
            SET status = ?, $timeField notes = CONCAT(COALESCE(notes, ''), ' | Status: ', ?) 
            WHERE id = ?
        ")->execute([$status, $status, $assignmentId]);

        // Update main order delivery status
        $db->prepare("UPDATE orders SET delivery_status = ? WHERE id = ?")->execute([$status, $da['order_id']]);

        // If delivered, mark rider available
        if ($status === 'delivered') {
            $db->prepare("UPDATE riders SET status = 'available' WHERE id = ?")->execute([$da['rider_id']]);
        }

        $db->commit();
        jsonResponse(['success' => true, 'message' => "Delivery status updated to $status."]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 2. RIDER CASH HANDOVER RECONCILIATION (PRD 13.5)
if ($action === 'handover_cash') {
    requirePermission('delivery.manage');
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $cashCollected = (float)($_POST['cash_collected'] ?? 0);

    try {
        $db->prepare("
            UPDATE delivery_assignments 
            SET cash_collected = ?, cash_handed_over = 1 
            WHERE id = ?
        ")->execute([$cashCollected, $assignmentId]);

        logAudit('rider_cash_handover', 'delivery_assignments', $assignmentId, null, ['cash_collected' => $cashCollected]);
        jsonResponse(['success' => true, 'message' => "Rider cash collection of " . formatCurrency($cashCollected) . " received and reconciled."]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 3. CREATE RIDER / DELIVERY ZONE
if ($action === 'create_rider') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $vehicle = trim($_POST['vehicle_number'] ?? '');
    if (!empty($name) && !empty($phone)) {
        $db->prepare("INSERT INTO riders (branch_id, name, phone, vehicle_number) VALUES (?, ?, ?, ?)")->execute([$branchId, $name, $phone, $vehicle]);
        jsonResponse(['success' => true, 'message' => "Delivery rider '$name' registered."]);
    }
}

if ($action === 'create_zone') {
    $name = trim($_POST['name'] ?? '');
    $fee = (float)($_POST['delivery_fee'] ?? 0);
    $min = (float)($_POST['min_order_amount'] ?? 0);
    $time = (int)($_POST['estimated_minutes'] ?? 30);
    if (!empty($name)) {
        $db->prepare("INSERT INTO delivery_zones (branch_id, name, delivery_fee, min_order_amount, estimated_minutes) VALUES (?, ?, ?, ?, ?)")->execute([$branchId, $name, $fee, $min, $time]);
        jsonResponse(['success' => true, 'message' => "Delivery zone '$name' created."]);
    }
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
