<?php
// includes/accounting_engine.php - Double-Entry General Ledger Engine

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit.php';

class AccountingEngine {

    /**
     * Post balanced journal entry
     */
    public static function createJournalEntry(
        int $branchId,
        string $referenceType,
        ?int $referenceId,
        string $description,
        array $lines, // [['account_id' => X, 'debit' => Y, 'credit' => Z, 'notes' => '...']]
        int $userId,
        ?string $entryDate = null
    ): int {
        $db = getDB();
        $date = $entryDate ?: date('Y-m-d');
        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0);
            $totalCredit += (float)($line['credit'] ?? 0);
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        // Strict accounting safeguard: Debits must equal Credits!
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception("Unbalanced journal entry! Total Debit ($totalDebit) does not match Total Credit ($totalCredit).");
        }

        if ($totalDebit <= 0) {
            return 0; // Nothing to post
        }

        $entryNumber = 'JRN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $stmt = $db->prepare("
            INSERT INTO journal_entries (branch_id, entry_number, entry_date, reference_type, reference_id, description, total_debit, total_credit, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$branchId, $entryNumber, $date, $referenceType, $referenceId, $description, $totalDebit, $totalCredit, $userId]);
        $entryId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare("
            INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, notes)
            VALUES (?, ?, ?, ?, ?)
        ");

        $accUpdate = $db->prepare("
            UPDATE accounts 
            SET current_balance = current_balance + ? 
            WHERE id = ?
        ");

        foreach ($lines as $l) {
            $deb = round((float)($l['debit'] ?? 0), 2);
            $crd = round((float)($l['credit'] ?? 0), 2);
            $accId = (int)$l['account_id'];
            $notes = $l['notes'] ?? '';

            if ($deb <= 0 && $crd <= 0) continue;

            $lineStmt->execute([$entryId, $accId, $deb, $crd, $notes]);

            // Update account balance based on account type
            // Assets, COGS, Expenses: Debit increases balance, Credit decreases
            // Liabilities, Equity, Revenue: Credit increases balance, Debit decreases
            $accInfo = $db->query("SELECT account_type FROM accounts WHERE id = $accId")->fetch();
            $type = $accInfo['account_type'] ?? 'asset';

            if (in_array($type, ['asset', 'cogs', 'expense'])) {
                $balanceChange = $deb - $crd;
            } else {
                $balanceChange = $crd - $deb;
            }
            $accUpdate->execute([$balanceChange, $accId]);
        }

        return $entryId;
    }

    /**
     * Post sales journal entry
     */
    public static function postSaleJournal(int $orderId, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        // Check if journal already posted
        $check = $db->prepare("SELECT id FROM journal_entries WHERE reference_type = 'orders' AND reference_id = ?");
        $check->execute([$orderId]);
        if ($check->fetch()) return; // Prevent duplicate entries!

        $lines = [];
        $branchId = (int)$order['branch_id'];

        // 1. Fetch payments made for this order
        $pStmt = $db->prepare("
            SELECT p.*, pm.account_id, pm.name as method_name 
            FROM payments p 
            JOIN payment_methods pm ON p.payment_method_id = pm.id 
            WHERE p.order_id = ?
        ");
        $pStmt->execute([$orderId]);
        $payments = $pStmt->fetchAll();

        $totalPaid = 0.00;
        foreach ($payments as $p) {
            $amt = (float)$p['amount'];
            $accId = (int)($p['account_id'] ?: 1); // Default to Cash in Register (1)
            $lines[] = [
                'account_id' => $accId,
                'debit' => $amt,
                'credit' => 0.00,
                'notes' => "Payment via " . $p['method_name']
            ];
            $totalPaid += $amt;
        }

        // Customer credit if remaining balance
        $grandTotal = (float)$order['grand_total'];
        $remaining = round($grandTotal - $totalPaid, 2);
        if ($remaining > 0) {
            // Debit Accounts Receivable (Account 4)
            $lines[] = [
                'account_id' => 4,
                'debit' => $remaining,
                'credit' => 0.00,
                'notes' => "Customer Receivable for Order #" . $order['order_number']
            ];
        }

        // Discount if any (Debit Contra-Revenue Account 12)
        $discount = (float)$order['discount_amount'];
        if ($discount > 0) {
            $lines[] = [
                'account_id' => 12,
                'debit' => $discount,
                'credit' => 0.00,
                'notes' => "Discount on Order #" . $order['order_number']
            ];
        }

        // Credit Sales Revenue (Account 9) with Subtotal
        $subtotal = (float)$order['subtotal'];
        $lines[] = [
            'account_id' => 9,
            'debit' => 0.00,
            'credit' => $subtotal,
            'notes' => "Gross Menu Sales for Order #" . $order['order_number']
        ];

        // Credit Sales Tax (Account 7) if any
        $tax = (float)$order['tax_amount'];
        if ($tax > 0) {
            $lines[] = [
                'account_id' => 7,
                'debit' => 0.00,
                'credit' => $tax,
                'notes' => "Sales Tax on Order #" . $order['order_number']
            ];
        }

        // Credit Service Charge (Account 10) if any
        $service = (float)$order['service_charge_amount'];
        if ($service > 0) {
            $lines[] = [
                'account_id' => 10,
                'debit' => 0.00,
                'credit' => $service,
                'notes' => "Service Charge on Order #" . $order['order_number']
            ];
        }

        // Credit Delivery Fee (Account 11) if any
        $delivery = (float)$order['delivery_fee'];
        if ($delivery > 0) {
            $lines[] = [
                'account_id' => 11,
                'debit' => 0.00,
                'credit' => $delivery,
                'notes' => "Delivery Fee on Order #" . $order['order_number']
            ];
        }

        self::createJournalEntry(
            $branchId,
            'orders',
            $orderId,
            "Sales Revenue - Order #" . $order['order_number'],
            $lines,
            $userId,
            substr($order['created_at'], 0, 10)
        );
    }

    /**
     * Post COGS consumption journal entry
     */
    public static function postCogsJournal(int $orderId, float $cogsAmount, int $userId, int $branchId): void {
        if ($cogsAmount <= 0) return;

        // Debit COGS (Account 13), Credit Inventory Asset (Account 5)
        $lines = [
            [
                'account_id' => 13,
                'debit' => $cogsAmount,
                'credit' => 0.00,
                'notes' => "COGS for Order #$orderId"
            ],
            [
                'account_id' => 5,
                'debit' => 0.00,
                'credit' => $cogsAmount,
                'notes' => "Ingredient inventory reduction for Order #$orderId"
            ]
        ];

        self::createJournalEntry(
            $branchId,
            'orders_cogs',
            $orderId,
            "Cost of Goods Sold - Order #$orderId",
            $lines,
            $userId
        );
    }

    /**
     * Post Expense journal entry
     */
    public static function postExpenseJournal(int $expenseId, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT e.*, c.account_id as exp_acc_id, pm.account_id as pay_acc_id, c.name as cat_name 
            FROM expenses e 
            JOIN expense_categories c ON e.category_id = c.id 
            JOIN payment_methods pm ON e.payment_method_id = pm.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$expenseId]);
        $exp = $stmt->fetch();
        if (!$exp) return;

        $amount = (float)$exp['amount'];
        $expAccountId = (int)($exp['exp_acc_id'] ?: 18); // Default Maintenance/General (18)
        $payAccountId = (int)($exp['pay_acc_id'] ?: 1);  // Default Cash (1)

        $lines = [
            [
                'account_id' => $expAccountId,
                'debit' => $amount,
                'credit' => 0.00,
                'notes' => "Expense: " . $exp['cat_name'] . " - " . $exp['description']
            ],
            [
                'account_id' => $payAccountId,
                'debit' => 0.00,
                'credit' => $amount,
                'notes' => "Paid to " . $exp['payee']
            ]
        ];

        self::createJournalEntry(
            (int)$exp['branch_id'],
            'expenses',
            $expenseId,
            "Expense #" . $exp['expense_number'] . " (" . $exp['cat_name'] . ")",
            $lines,
            $userId,
            $exp['expense_date']
        );
    }

    /**
     * Post Purchase Order receipt journal
     */
    public static function postPurchaseJournal(int $poId, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
        $stmt->execute([$poId]);
        $po = $stmt->fetch();
        if (!$po) return;

        $grandTotal = (float)$po['grand_total'];
        $paid = (float)$po['paid_amount'];
        $unpaid = round($grandTotal - $paid, 2);

        $lines = [
            // Debit Inventory Asset (5)
            [
                'account_id' => 5,
                'debit' => $grandTotal,
                'credit' => 0.00,
                'notes' => "Stock receipt from PO #" . $po['po_number']
            ]
        ];

        if ($paid > 0) {
            // Credit Cash (1) or Bank
            $lines[] = [
                'account_id' => 1,
                'debit' => 0.00,
                'credit' => $paid,
                'notes' => "Payment for PO #" . $po['po_number']
            ];
        }

        if ($unpaid > 0) {
            // Credit Accounts Payable (6)
            $lines[] = [
                'account_id' => 6,
                'debit' => 0.00,
                'credit' => $unpaid,
                'notes' => "Payable to Supplier for PO #" . $po['po_number']
            ];
        }

        self::createJournalEntry(
            (int)$po['branch_id'],
            'purchase_orders',
            $poId,
            "Purchase Receiving - PO #" . $po['po_number'],
            $lines,
            $userId,
            $po['po_date']
        );
    }

    /**
     * Post Wastage journal
     */
    public static function postWastageJournal(int $wastageId, float $totalCost, string $reason, int $userId, int $branchId): void {
        if ($totalCost <= 0) return;

        // Debit Spoilage & Wastage Expense (19), Credit Inventory (5)
        $lines = [
            [
                'account_id' => 19,
                'debit' => $totalCost,
                'credit' => 0.00,
                'notes' => "Wastage Loss: $reason"
            ],
            [
                'account_id' => 5,
                'debit' => 0.00,
                'credit' => $totalCost,
                'notes' => "Inventory write-off from spoilage"
            ]
        ];

        self::createJournalEntry(
            $branchId,
            'stock_wastage',
            $wastageId,
            "Inventory Wastage Write-off: $reason",
            $lines,
            $userId
        );
    }

    /**
     * Post Refund journal
     */
    public static function postRefundJournal(int $refundId, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT r.*, pm.account_id as pay_acc_id, o.order_number 
            FROM refunds r 
            JOIN payment_methods pm ON r.refund_method_id = pm.id 
            JOIN orders o ON r.order_id = o.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$refundId]);
        $ref = $stmt->fetch();
        if (!$ref) return;

        $amount = (float)$ref['amount'];
        $payAccountId = (int)($ref['pay_acc_id'] ?: 1);

        // Debit Sales Revenue (9) or Contra-Revenue, Credit Cash/Bank (Payment method)
        $lines = [
            [
                'account_id' => 9,
                'debit' => $amount,
                'credit' => 0.00,
                'notes' => "Sales Refund for Order #" . $ref['order_number'] . " (" . $ref['reason'] . ")"
            ],
            [
                'account_id' => $payAccountId,
                'debit' => 0.00,
                'credit' => $amount,
                'notes' => "Cash refund paid out"
            ]
        ];

        self::createJournalEntry(
            (int)$ref['branch_id'],
            'refunds',
            $refundId,
            "Refund #" . $ref['refund_number'] . " for Order #" . $ref['order_number'],
            $lines,
            $userId
        );
    }
}
