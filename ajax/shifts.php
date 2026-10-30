<?php
// ajax/shifts.php - Cashier Shift Opening, Closing & Reconciliation API (PRD 18.4)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. OPEN NEW SHIFT
if ($action === 'open_shift') {
    requirePermission('shifts.manage');
    $registerId = (int)($_POST['register_id'] ?? 1);
    $openingCash = (float)($_POST['opening_cash'] ?? 0);
    $notes = trim($_POST['notes'] ?? 'Shift opened');

    // Check if open shift exists for this register
    $chk = $db->prepare("SELECT id FROM cash_shifts WHERE register_id = ? AND status = 'open'");
    $chk->execute([$registerId]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'An active shift is already open for this POS register.'], 400);
    }

    $shiftNumber = 'SHF-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

    $db->prepare("
        INSERT INTO cash_shifts (branch_id, register_id, user_id, shift_number, opened_at, opening_cash, status, notes)
        VALUES (?, ?, ?, ?, NOW(), ?, 'open', ?)
    ")->execute([$branchId, $registerId, $userId, $shiftNumber, $openingCash, $notes]);

    $shiftId = (int)$db->lastInsertId();
    logAudit('open_shift', 'cash_shifts', $shiftId, null, ['shift_number' => $shiftNumber, 'opening_cash' => $openingCash]);

    jsonResponse(['success' => true, 'message' => "Shift #$shiftNumber opened successfully with opening cash " . formatCurrency($openingCash)]);
}

// 2. CLOSE SHIFT & RECONCILE (PRD 18.4 Closing Workflow)
if ($action === 'close_shift') {
    requirePermission('shifts.manage');
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $actualCash = (float)($_POST['actual_cash'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $db->prepare("SELECT * FROM cash_shifts WHERE id = ? AND status = 'open'");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch();
    if (!$shift) jsonResponse(['success' => false, 'message' => 'Active shift not found.'], 404);

    // Calculate Cash Collections during shift
    $cashSalesQuery = "
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p 
        WHERE p.shift_id = ? AND p.payment_method_id = 1
    ";
    $stmt = $db->prepare($cashSalesQuery);
    $stmt->execute([$shiftId]);
    $cashSales = (float)$stmt->fetchColumn();

    // Card and digital sales
    $digitalSalesQuery = "
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p 
        WHERE p.shift_id = ? AND p.payment_method_id != 1
    ";
    $stmt = $db->prepare($digitalSalesQuery);
    $stmt->execute([$shiftId]);
    $digitalSales = (float)$stmt->fetchColumn();

    // Refunds during shift
    $refundQuery = "
        SELECT COALESCE(SUM(r.amount), 0) 
        FROM refunds r 
        JOIN orders o ON r.order_id = o.id 
        WHERE o.shift_id = ?
    ";
    $stmt = $db->prepare($refundQuery);
    $stmt->execute([$shiftId]);
    $refunds = (float)$stmt->fetchColumn();

    $openingCash = (float)$shift['opening_cash'];
    $expectedCash = $openingCash + $cashSales - $refunds;
    $cashDiff = $actualCash - $expectedCash;

    $db->prepare("
        UPDATE cash_shifts SET 
            closed_at = NOW(),
            closing_cash_actual = ?,
            closing_cash_expected = ?,
            cash_difference = ?,
            total_cash_sales = ?,
            total_digital_sales = ?,
            total_refunds = ?,
            status = 'closed',
            notes = CONCAT(COALESCE(notes, ''), ' | Closing: ', ?) 
        WHERE id = ?
    ")->execute([
        $actualCash, $expectedCash, $cashDiff, $cashSales, $digitalSales, $refunds, $notes, $shiftId
    ]);

    logAudit('close_shift', 'cash_shifts', $shiftId, null, [
        'expected' => $expectedCash,
        'actual' => $actualCash,
        'difference' => $cashDiff
    ]);

    jsonResponse([
        'success' => true,
        'message' => "Shift #{$shift['shift_number']} closed. Difference: " . formatCurrency($cashDiff),
        'expected' => $expectedCash,
        'actual' => $actualCash,
        'difference' => $cashDiff
    ]);
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
