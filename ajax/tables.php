<?php
// ajax/tables.php - Table Operations API (Status, Transfer, CRUD)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. UPDATE TABLE STATUS
if ($action === 'set_status') {
    $tableId = (int)($_POST['table_id'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    if (!in_array($status, ['available', 'occupied', 'reserved', 'cleaning', 'unavailable'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
    }

    $db->prepare("UPDATE restaurant_tables SET status = ? WHERE id = ?")->execute([$status, $tableId]);
    logAudit('table_status_change', 'restaurant_tables', $tableId, null, ['new_status' => $status]);

    jsonResponse(['success' => true, 'message' => "Table marked as $status"]);
}

// 2. TRANSFER ORDER BETWEEN TABLES (PRD 5.3)
if ($action === 'transfer_table') {
    $fromTableId = (int)($_POST['from_table_id'] ?? 0);
    $toTableId = (int)($_POST['to_table_id'] ?? 0);

    if (!$fromTableId || !$toTableId || $fromTableId === $toTableId) {
        jsonResponse(['success' => false, 'message' => 'Invalid table selection'], 400);
    }

    try {
        $db->beginTransaction();

        // Check source table
        $stmt = $db->prepare("SELECT current_order_id, table_number FROM restaurant_tables WHERE id = ?");
        $stmt->execute([$fromTableId]);
        $from = $stmt->fetch();
        if (!$from || !$from['current_order_id']) {
            throw new Exception("Source table has no active order to transfer.");
        }

        $orderId = (int)$from['current_order_id'];

        // Check destination table
        $stmt = $db->prepare("SELECT status, table_number FROM restaurant_tables WHERE id = ?");
        $stmt->execute([$toTableId]);
        $to = $stmt->fetch();
        if (!$to) throw new Exception("Target table not found.");
        if ($to['status'] === 'occupied') {
            throw new Exception("Target table {$to['table_number']} is already occupied.");
        }

        // Reassign order
        $db->prepare("UPDATE orders SET table_id = ? WHERE id = ?")->execute([$toTableId, $orderId]);

        // Release old table
        $db->prepare("UPDATE restaurant_tables SET status = 'available', current_order_id = NULL WHERE id = ?")->execute([$fromTableId]);

        // Occupy new table
        $db->prepare("UPDATE restaurant_tables SET status = 'occupied', current_order_id = ? WHERE id = ?")->execute([$orderId, $toTableId]);

        $db->commit();

        logAudit('table_transfer', 'orders', $orderId, null, [
            'from_table' => $from['table_number'],
            'to_table' => $to['table_number']
        ]);

        jsonResponse([
            'success' => true,
            'message' => "Order successfully transferred from Table {$from['table_number']} to Table {$to['table_number']}."
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 3. CREATE NEW TABLE
if ($action === 'create_table') {
    $areaId = (int)($_POST['dining_area_id'] ?? 1);
    $tableNumber = trim($_POST['table_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 4);
    $shape = $_POST['shape'] ?? 'square';
    $serviceCharge = (float)($_POST['service_charge_percent'] ?? 0);

    if (empty($tableNumber)) jsonResponse(['success' => false, 'message' => 'Table number is required'], 400);

    $db->prepare("
        INSERT INTO restaurant_tables (branch_id, dining_area_id, table_number, capacity, shape, service_charge_percent)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$branchId, $areaId, $tableNumber, $capacity, $shape, $serviceCharge]);

    jsonResponse(['success' => true, 'message' => "Table $tableNumber created successfully!"]);
}

// 4. CREATE DINING AREA
if ($action === 'create_area') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) jsonResponse(['success' => false, 'message' => 'Area name is required'], 400);

    $db->prepare("INSERT INTO dining_areas (branch_id, name, description) VALUES (?, ?, ?)")->execute([$branchId, $name, $description]);
    jsonResponse(['success' => true, 'message' => "Area $name added successfully!"]);
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
