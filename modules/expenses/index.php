<?php
// modules/expenses/index.php - Restaurant Operating Expenses Log
$pageTitle = 'Operating Expenses';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('expenses.manage');

$db = getDB();
$branchId = currentBranchId();

$dateFrom = cleanInput(get('date_from', date('Y-m-01')));
$dateTo = cleanInput(get('date_to', date('Y-m-d')));
$catFilter = (int)get('category_id', 0);

$sql = "
    SELECT e.*, c.name as category_name, pm.name as method_name, u.full_name as creator_name 
    FROM expenses e 
    JOIN expense_categories c ON e.category_id = c.id 
    JOIN payment_methods pm ON e.payment_method_id = pm.id 
    JOIN users u ON e.created_by = u.id 
    WHERE e.branch_id = ? AND e.expense_date BETWEEN ? AND ? 
";
$params = [$branchId, $dateFrom, $dateTo];
if ($catFilter) {
    $sql .= " AND e.category_id = ?";
    $params[] = $catFilter;
}
$sql .= " ORDER BY e.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$totalExpenses = array_sum(array_column($expenses, 'amount'));
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-cash-coin text-danger me-2"></i> Operating Expenses</h3>
        <p class="text-muted small mb-0">Total Expenses for Period: <b class="text-danger fs-6"><?= formatCurrency($totalExpenses) ?></b> (Tracked separately from COGS & stock purchases)</p>
    </div>

    <!-- Actions (Dedicated page per PRD 25.3: "Add an expense: Dedicated expense page") -->
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/expenses/categories.php" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-folder me-1"></i> Expense Categories
        </a>
        <a href="<?= BASE_URL ?>modules/expenses/create.php" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-plus-circle me-1"></i> Record Expense
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card-rms p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Expense Category</label>
            <select name="category_id" class="form-select form-select-sm">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold">
                <i class="bi bi-filter"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Expense List Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Expense #</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Payee</th>
                    <th>Description</th>
                    <th>Payment Method</th>
                    <th class="text-end">Amount</th>
                    <th>Staff Member</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No operating expenses found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($e['expense_number']) ?></td>
                            <td class="small text-muted"><?= date('d-M-Y', strtotime($e['expense_date'])) ?></td>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($e['category_name']) ?></span></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($e['payee']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($e['description']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($e['method_name']) ?></span></td>
                            <td class="text-end fw-bold fs-6 text-danger"><?= formatCurrency($e['amount']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($e['creator_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
