<?php
// modules/expenses/categories.php - Expense Categories & General Ledger Mapping
$pageTitle = 'Expense Categories';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('expenses.manage');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken(post('csrf_token'))) {
        $name = cleanInput(post('name'));
        $desc = cleanInput(post('description'));
        $accId = !empty(post('account_id')) ? (int)post('account_id') : 18;
        if (!empty($name)) {
            $db->prepare("INSERT INTO expense_categories (name, description, account_id) VALUES (?, ?, ?)")->execute([$name, $desc, $accId]);
            setFlash('success', "Category '$name' created.");
            header('Location: ' . BASE_URL . 'modules/expenses/categories.php');
            exit;
        }
    }
}

$categories = $db->query("
    SELECT ec.*, a.code as acc_code, a.name as acc_name,
           (SELECT COUNT(*) FROM expenses WHERE category_id = ec.id) as expenses_count,
           (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = ec.id) as total_amount 
    FROM expense_categories ec 
    LEFT JOIN accounts a ON ec.account_id = a.id 
    WHERE ec.is_active = 1 
    ORDER BY ec.name ASC
")->fetchAll();

$accounts = $db->query("SELECT * FROM accounts WHERE account_type = 'expense' AND is_active = 1 ORDER BY code ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-folder-fill text-danger me-2"></i> Expense Categories</h3>
        <p class="text-muted small mb-0">Manage operating cost categories and their corresponding General Ledger accounts</p>
    </div>
    <a href="<?= BASE_URL ?>modules/expenses/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Expenses
    </a>
</div>

<div class="row g-4">
    <!-- Categories List -->
    <div class="col-12 col-lg-8">
        <div class="card-rms overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small fw-bold">
                        <tr>
                            <th>Category</th>
                            <th>Chart of Accounts Mapping</th>
                            <th>Entries Count</th>
                            <th class="text-end">Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></span>
                                    <small class="text-muted d-block"><?= htmlspecialchars($c['description'] ?? '') ?></small>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($c['acc_code'] ?? '18') ?></code> - <?= htmlspecialchars($c['acc_name'] ?? 'General Expense') ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= $c['expenses_count'] ?> records</span></td>
                                <td class="text-end fw-bold text-danger"><?= formatCurrency($c['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Category Form (Dedicated Panel on Right, NO modals) -->
    <div class="col-12 col-lg-4">
        <div class="card-rms p-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">New Expense Category</h5>
            <form method="POST" action="">
                <?= csrfField(); ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Category Title <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Internet, Marketing..." required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">GL Expense Account</label>
                    <select name="account_id" class="form-select">
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= $a['code'] ?> - <?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief note on what costs belong here..."></textarea>
                </div>

                <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Save Category
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
