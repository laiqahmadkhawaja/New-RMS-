<?php
// ajax/reservations.php - Table Bookings & Waiting List API (PRD 15)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. CREATE RESERVATION
if ($action === 'create_reservation') {
    requirePermission('reservations.manage');
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $resDate = cleanInput($_POST['reservation_date'] ?? date('Y-m-d'));
    $time = cleanInput($_POST['arrival_time'] ?? '19:00');
    $guests = (int)($_POST['guests_count'] ?? 2);
    $tableId = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
    $notes = cleanInput($_POST['special_requests'] ?? '');

    if (empty($name) || empty($phone)) {
        jsonResponse(['success' => false, 'message' => 'Customer name and phone are required.'], 400);
    }

    try {
        $db->prepare("
            INSERT INTO reservations (branch_id, customer_name, phone, reservation_date, arrival_time, guests_count, table_id, special_requests, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
        ")->execute([$branchId, $name, $phone, $resDate, $time, $guests, $tableId, $notes]);

        if ($tableId) {
            $db->prepare("UPDATE restaurant_tables SET status = 'reserved' WHERE id = ?")->execute([$tableId]);
        }

        jsonResponse(['success' => true, 'message' => "Table reservation confirmed for $name!"]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 2. ADD TO WAITING LIST
if ($action === 'add_waiting') {
    requirePermission('reservations.manage');
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $size = (int)($_POST['party_size'] ?? 2);
    $notes = cleanInput($_POST['notes'] ?? '');

    if (empty($name) || empty($phone)) {
        jsonResponse(['success' => false, 'message' => 'Name and phone required.'], 400);
    }

    $db->prepare("
        INSERT INTO waiting_list (branch_id, customer_name, phone, party_size, status, notes)
        VALUES (?, ?, ?, ?, 'waiting', ?)
    ")->execute([$branchId, $name, $phone, $size, $notes]);

    jsonResponse(['success' => true, 'message' => "$name added to waiting list queue."]);
}

// 3. SEAT WAITING GUEST
if ($action === 'seat_waiting') {
    requirePermission('reservations.manage');
    $waitId = (int)($_POST['waiting_id'] ?? 0);
    $db->prepare("UPDATE waiting_list SET status = 'seated' WHERE id = ?")->execute([$waitId]);
    jsonResponse(['success' => true, 'message' => 'Guest marked as seated.']);
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
