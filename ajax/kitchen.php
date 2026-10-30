<?php
// ajax/kitchen.php - Kitchen Display System Backend API
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$db = getDB();
$branchId = currentBranchId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. GET ACTIVE KITCHEN TICKETS
if ($action === 'get_tickets') {
    $stationId = !empty($_GET['station_id']) ? (int)$_GET['station_id'] : null;

    $sql = "
        SELECT o.id as order_id, o.order_number, o.created_at, o.kitchen_status, ot.name as order_type_name, 
               t.table_number, TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as elapsed_minutes 
        FROM orders o 
        JOIN order_types ot ON o.order_type_id = ot.id 
        LEFT JOIN restaurant_tables t ON o.table_id = t.id 
        WHERE o.branch_id = ? 
        AND o.order_status != 'cancelled' 
        AND o.kitchen_status IN ('pending', 'preparing', 'ready')
        ORDER BY o.created_at ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branchId]);
    $orders = $stmt->fetchAll();

    foreach ($orders as &$ord) {
        $itemSql = "
            SELECT oi.*, ks.name as station_name 
            FROM order_items oi 
            LEFT JOIN kitchen_stations ks ON oi.kitchen_station_id = ks.id 
            WHERE oi.order_id = ? 
        ";
        $params = [$ord['order_id']];
        if ($stationId) {
            $itemSql .= " AND (oi.kitchen_station_id = ? OR oi.kitchen_station_id IS NULL)";
            $params[] = $stationId;
        }

        $iStmt = $db->prepare($itemSql);
        $iStmt->execute($params);
        $items = $iStmt->fetchAll();

        foreach ($items as &$it) {
            $it['modifiers'] = $db->query("SELECT * FROM order_item_modifiers WHERE order_item_id = " . $it['id'])->fetchAll();
        }

        $ord['items'] = $items;
    }

    // Filter out orders that have 0 items for the selected station
    if ($stationId) {
        $orders = array_values(array_filter($orders, fn($o) => !empty($o['items'])));
    }

    jsonResponse(['success' => true, 'orders' => $orders]);
}

// 2. UPDATE KITCHEN STATUS
if ($action === 'update_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? ''; // 'preparing', 'ready', 'served'

    if (!in_array($status, ['preparing', 'ready', 'completed'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
    }

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE orders SET kitchen_status = ? WHERE id = ?")->execute([$status, $orderId]);
        $db->prepare("UPDATE order_items SET kitchen_status = ? WHERE order_id = ?")->execute([$status, $orderId]);

        // If kitchen preparation is completed and order is paid, mark overall order completed
        if ($status === 'completed') {
            $ordRow = $db->query("SELECT payment_status FROM orders WHERE id = $orderId")->fetch();
            if ($ordRow && $ordRow['payment_status'] === 'paid') {
                $db->prepare("UPDATE orders SET order_status = 'completed' WHERE id = ?")->execute([$orderId]);
            }
        }

        $db->commit();

        jsonResponse(['success' => true, 'message' => "Order updated to $status"]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
