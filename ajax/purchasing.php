<?php
// ajax/purchasing.php - Suppliers & Purchase Order Processing API
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory_engine.php';
require_once __DIR__ . '/../includes/accounting_engine.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. SAVE SUPPLIER
if ($action === 'save_supplier') {
    requirePermission('purchasing.manage');
    $id = (int)($_POST['supplier_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $openBal = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name) || empty($phone)) {
        jsonResponse(['success' => false, 'message' => 'Supplier name and phone are required.'], 400);
    }

    try {
        if ($id) {
            $db->prepare("
                UPDATE suppliers 
                SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? 
                WHERE id = ?
            ")->execute([$name, $person, $phone, $email, $address, $id]);
            logAudit('update_supplier', 'suppliers', $id, null, ['name' => $name]);
        } else {
            $db->prepare("
                INSERT INTO suppliers (name, contact_person, phone, email, address, opening_balance, current_balance)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $person, $phone, $email, $address, $openBal, $openBal]);
            $id = (int)$db->lastInsertId();
            logAudit('create_supplier', 'suppliers', $id, null, ['name' => $name, 'opening_balance' => $openBal]);
        }

        jsonResponse(['success' => true, 'message' => "Supplier '$name' saved successfully."]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 2. RECORD SUPPLIER PAYMENT
if ($action === 'pay_supplier') {
    requirePermission('purchasing.manage');
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $methodId = (int)($_POST['payment_method_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');

    if (!$supplierId || $amount <= 0) {
        jsonResponse(['success' => false, 'message' => 'Valid supplier and payment amount required.'], 400);
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        if (!$supplier) throw new Exception("Supplier not found.");

        // Deduct from supplier balance
        $db->prepare("UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $supplierId]);
        $newBal = (float)$supplier['current_balance'] - $amount;

        // Record in supplier ledger
        $db->prepare("
            INSERT INTO supplier_ledger (supplier_id, transaction_type, reference_type, reference_id, debit, credit, balance, notes, created_at)
            VALUES (?, 'payment', 'supplier_payments', NULL, ?, 0.00, ?, ?, NOW())
        ")->execute([$supplierId, $amount, $newBal, $notes]);

        // Post accounting journal (Debit AP: Account 6, Credit Cash/Bank: Account 1)
        $lines = [
            [
                'account_id' => 6, // Accounts Payable
                'debit' => $amount,
                'credit' => 0.00,
                'notes' => "Payment to Supplier " . $supplier['name']
            ],
            [
                'account_id' => 1, // Cash in Register
                'debit' => 0.00,
                'credit' => $amount,
                'notes' => "Cash payment disbursed"
            ]
        ];

        AccountingEngine::createJournalEntry(
            $branchId,
            'supplier_payments',
            $supplierId,
            "Supplier Payment - " . $supplier['name'],
            $lines,
            $userId
        );

        $db->commit();
        jsonResponse(['success' => true, 'message' => "Payment of " . formatCurrency($amount) . " to {$supplier['name']} posted successfully."]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
