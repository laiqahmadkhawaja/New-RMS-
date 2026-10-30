<?php
// modules/customers/ledger.php - Customer Account Statement & Transaction History (PRD 14.2)
$pageTitle = 'Customer Ledger Statement';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('customers.manage');

$db = getDB();
$customerId = (int)($_GET['customer_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    echo "<div class='p-4'><div class='alert alert-warning d-flex align-items-center justify-content-between'><span><i class='bi bi-exclamation-triangle me-2'></i>Customer not found or invalid customer ID specified.</span> <a href='index.php' class='btn btn-sm btn-outline-dark'>Return to Customers</a></div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$transactions = $db->query("
    SELECT * FROM customer_ledger 
    WHERE customer_id = $customerId 
    ORDER BY id ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-file-text text-danger me-2"></i> Customer Account Statement</h3>
        <p class="text-muted small mb-0"><?= htmlspecialchars($customer['name']) ?> | Phone: <?= htmlspecialchars($customer['phone']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm fw-semibold">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
        <a href="<?= BASE_URL ?>modules/customers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<!-- Customer Profile Summary Card -->
<div class="card-rms p-4 mb-4">
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <small class="text-muted text-uppercase fw-bold">Customer Info</small>
            <h5 class="fw-bold text-dark mt-1 mb-0"><?= htmlspecialchars($customer['name']) ?></h5>
            <div class="small text-muted"><?= htmlspecialchars($customer['phone']) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($customer['address'] ?? 'No address') ?></div>
        </div>
        <div class="col-6 col-md-4 text-md-center">
            <small class="text-muted text-uppercase fw-bold">Credit Limit</small>
            <h5 class="fw-bold text-dark mt-1 mb-0"><?= formatCurrency($customer['credit_limit']) ?></h5>
            <small class="text-muted">Loyalty: <?= $customer['loyalty_points'] ?> points</small>
        </div>
        <div class="col-6 col-md-4 text-md-end">
            <small class="text-muted text-uppercase fw-bold">Outstanding Balance</small>
            <h3 class="fw-bold <?= (float)$customer['current_balance'] > 0 ? 'text-danger' : 'text-success' ?> mt-1 mb-0">
                <?= formatCurrency($customer['current_balance']) ?>
            </h3>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light text-uppercase fw-bold">
                <tr>
                    <th>Date & Time</th>
                    <th>Transaction Type</th>
                    <th>Reference / Notes</th>
                    <th class="text-end">Debit (+)</th>
                    <th class="text-end">Credit (-)</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No account transactions recorded for this customer.</td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td class="text-muted"><?= date('d-M-Y H:i', strtotime($t['created_at'])) ?></td>
                            <td>
                                <span class="badge <?php
                                    echo match($t['transaction_type']) {
                                        'sale_credit' => 'bg-danger-subtle text-danger border',
                                        'payment_receipt' => 'bg-success-subtle text-success border',
                                        default => 'bg-secondary'
                                    };
                                ?> text-capitalize"><?= str_replace('_', ' ', $t['transaction_type']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($t['notes'] ?? '-') ?></td>
                            <td class="text-end text-danger fw-bold">
                                <?= (float)$t['debit'] > 0 ? formatCurrency($t['debit']) : '-' ?>
                            </td>
                            <td class="text-end text-success fw-bold">
                                <?= (float)$t['credit'] > 0 ? formatCurrency($t['credit']) : '-' ?>
                            </td>
                            <td class="text-end fw-bold fs-6"><?= formatCurrency($t['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
