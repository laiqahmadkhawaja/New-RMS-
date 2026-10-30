<?php
// modules/expenses/create.php - Dedicated Operating Expense Entry Page (PRD 25.3)
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/accounting_engine.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';

requirePermission('expenses.manage');

$pageTitle = 'Record Operating Expense';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Security token expired.');
    } else {
        $categoryId = (int)post('category_id');
        $expenseDate = cleanInput(post('expense_date', date('Y-m-d')));
        $payee = cleanInput(post('payee'));
        $amount = (float)post('amount', 0);
        $methodId = (int)post('payment_method_id', 1);
        $description = cleanInput(post('description'));

        if (!$categoryId || empty($payee) || $amount <= 0 || empty($description)) {
            setFlash('error', 'Category, payee, amount, and description are required.');
        } else {
            try {
                $db->beginTransaction();

                $expNumber = 'EXP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

                $stmt = $db->prepare("
                    INSERT INTO expenses (branch_id, category_id, expense_number, expense_date, payee, amount, payment_method_id, description, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$branchId, $categoryId, $expNumber, $expenseDate, $payee, $amount, $methodId, $description, $userId]);
                $expenseId = (int)$db->lastInsertId();

                // Double-Entry Accounting Posting (Debit Expense Account, Credit Cash/Bank)
                AccountingEngine::postExpenseJournal($expenseId, $userId);

                $db->commit();
                logAudit('create_expense', 'expenses', $expenseId, null, ['number' => $expNumber, 'amount' => $amount]);

                setFlash('success', "Operating Expense #$expNumber for " . formatCurrency($amount) . " posted to General Ledger successfully!");
                echo "<script>window.location.href = '" . BASE_URL . "modules/expenses/index.php';</script>";
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', "Database error: " . $e->getMessage());
            }
        }
    }
}

$categories = $db->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Record Operating Expense</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>modules/expenses/index.php">Expenses</a></li>
                <li class="breadcrumb-item active">New Entry</li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>modules/expenses/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Expenses
    </a>
</div>

<form method="POST" action="">
    <?= csrfField(); ?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card-rms p-4 shadow-sm mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Expense Details</h5>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Expense Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Paid To / Payee <span class="text-danger">*</span></label>
                        <input type="text" name="payee" class="form-control" placeholder="e.g. Landlord, WAPDA, AC Repairman..." required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Amount (Rs.) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control form-control-lg fw-bold text-danger" step="0.01" placeholder="0.00" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Paid From / Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method_id" class="form-select" required>
                            <?php foreach ($paymentMethods as $pm): ?>
                                <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Detailed Description / Purpose <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Explain the expense in detail for accounting and audit compliance..." required></textarea>
                    </div>
                </div>

                <div class="alert alert-info py-2 small mb-4">
                    <i class="bi bi-info-circle-fill me-1"></i> This transaction will automatically post a balanced journal voucher to the Double-Entry General Ledger, debiting the operating expense account and crediting your cash/bank account.
                </div>

                <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow">
                    <i class="bi bi-check2-circle me-1"></i> Post Expense to Accounts
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
