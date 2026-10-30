<?php
// ajax/customers.php - Customer CRM, Credit Ledgers & Loyalty API
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

// 1. SAVE CUSTOMER (Create/Update)
if ($action === 'save_customer') {
    requirePermission('customers.manage');
    $id = (int)($_POST['customer_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $deliveryNotes = trim($_POST['delivery_notes'] ?? '');
    $creditLimit = (float)($_POST['credit_limit'] ?? 0);

    if (empty($name) || empty($phone)) {
        jsonResponse(['success' => false, 'message' => 'Customer name and phone are required.'], 400);
    }

    try {
        if ($id) {
            $db->prepare("
                UPDATE customers 
                SET name = ?, phone = ?, email = ?, address = ?, delivery_notes = ?, credit_limit = ? 
                WHERE id = ?
            ")->execute([$name, $phone, $email, $address, $deliveryNotes, $creditLimit, $id]);
            logAudit('update_customer', 'customers', $id, null, ['name' => $name, 'phone' => $phone]);
        } else {
            $db->prepare("
                INSERT INTO customers (name, phone, email, address, delivery_notes, credit_limit, current_balance, loyalty_points)
                VALUES (?, ?, ?, ?, ?, ?, 0.00, 0)
            ")->execute([$name, $phone, $email, $address, $deliveryNotes, $creditLimit]);
            $id = (int)$db->lastInsertId();
            logAudit('create_customer', 'customers', $id, null, ['name' => $name, 'phone' => $phone]);
        }

        jsonResponse(['success' => true, 'message' => "Customer '$name' saved successfully."]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 2. RECEIVE CUSTOMER CREDIT PAYMENT (PRD 12.4 Partial payments & settlements)
if ($action === 'receive_payment') {
    requirePermission('customers.manage');
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $methodId = (int)($_POST['payment_method_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? 'Customer balance settlement');

    if (!$customerId || $amount <= 0) {
        jsonResponse(['success' => false, 'message' => 'Valid customer and received amount required.'], 400);
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $cust = $stmt->fetch();
        if (!$cust) throw new Exception("Customer not found.");

        // Deduct from balance
        $db->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $customerId]);
        $newBal = (float)$cust['current_balance'] - $amount;

        // Record in customer ledger
        $db->prepare("
            INSERT INTO customer_ledger (customer_id, transaction_type, reference_type, reference_id, debit, credit, balance, notes, created_at)
            VALUES (?, 'payment_receipt', 'customer_payment', NULL, 0.00, ?, ?, ?, NOW())
        ")->execute([$customerId, $amount, $newBal, $notes]);

        // Post accounting journal (Debit Cash in Register: Account 1, Credit Accounts Receivable: Account 4)
        $lines = [
            [
                'account_id' => 1, // Cash
                'debit' => $amount,
                'credit' => 0.00,
                'notes' => "Payment received from " . $cust['name']
            ],
            [
                'account_id' => 4, // Accounts Receivable
                'debit' => 0.00,
                'credit' => $amount,
                'notes' => "Customer credit settlement"
            ]
        ];

        AccountingEngine::createJournalEntry(
            $branchId,
            'customer_payment',
            $customerId,
            "Customer Balance Settlement - " . $cust['name'],
            $lines,
            $userId
        );

        $db->commit();
        jsonResponse(['success' => true, 'message' => "Payment of " . formatCurrency($amount) . " received from {$cust['name']}."]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
