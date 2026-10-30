<?php
// modules/accounting/index.php - Double-Entry General Ledger & Chart of Accounts
$pageTitle = 'Double-Entry Accounting';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('accounting.view');

$db = getDB();
$branchId = currentBranchId();

$accounts = $db->query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY code ASC")->fetchAll();

// Group accounts by classification
$grouped = [];
foreach ($accounts as $a) {
    $grouped[$a['account_type']][] = $a;
}

// Recent Journal Entries
$recentJournals = $db->query("
    SELECT je.*, u.full_name as creator_name 
    FROM journal_entries je 
    JOIN users u ON je.created_by = u.id 
    WHERE je.branch_id = $branchId 
    ORDER BY je.id DESC LIMIT 15
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-bank text-danger me-2"></i> Double-Entry General Ledger</h3>
        <p class="text-muted small mb-0">Auditable financial engine connecting POS sales, inventory COGS, supplier payables, and operating expenses</p>
    </div>

    <!-- Actions (Dedicated pages per PRD 25.3) -->
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/accounting/reports.php" class="btn btn-outline-dark btn-sm fw-semibold">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Financial Statements (P&L, Balance Sheet)
        </a>
        <?php if (userHasPermission('accounting.journals')): ?>
            <a href="<?= BASE_URL ?>modules/accounting/journal_create.php" class="btn btn-danger btn-sm fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> Post Journal Voucher
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Chart of Accounts Summary Tabs -->
<div class="row g-4 mb-4">
    <!-- Chart of Accounts by Class -->
    <div class="col-12 col-xl-7">
        <div class="card-rms p-4 shadow-sm">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Chart of Accounts</h5>

            <?php 
            $typeTitles = [
                'asset' => ['Title' => 'Assets (1000s)', 'Badge' => 'bg-primary'],
                'liability' => ['Title' => 'Liabilities (2000s)', 'Badge' => 'bg-danger'],
                'equity' => ['Title' => 'Equity (3000s)', 'Badge' => 'bg-info text-dark'],
                'revenue' => ['Title' => 'Revenue & Income (4000s)', 'Badge' => 'bg-success'],
                'cogs' => ['Title' => 'Cost of Goods Sold (5000s)', 'Badge' => 'bg-warning text-dark'],
                'expense' => ['Title' => 'Operating Expenses (6000s)', 'Badge' => 'bg-secondary']
            ];
            ?>

            <div class="accordion accordion-flush" id="coaAccordion">
                <?php foreach ($typeTitles as $typeKey => $meta): 
                    $accList = $grouped[$typeKey] ?? [];
                    $subtotal = array_sum(array_column($accList, 'current_balance'));
                ?>
                    <div class="accordion-item border rounded mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $typeKey ?>">
                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                    <span>
                                        <span class="badge <?= $meta['Badge'] ?> me-2"><?= count($accList) ?></span>
                                        <?= $meta['Title'] ?>
                                    </span>
                                    <span class="fw-bold fs-6"><?= formatCurrency($subtotal) ?></span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $typeKey ?>" class="accordion-collapse collapse" data-bs-parent="#coaAccordion">
                            <div class="accordion-body p-0">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <tbody>
                                        <?php foreach ($accList as $acc): ?>
                                            <tr>
                                                <td style="width: 15%;"><code class="fw-bold"><?= $acc['code'] ?></code></td>
                                                <td style="width: 55%;">
                                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($acc['name']) ?></span>
                                                    <small class="text-muted d-block"><?= htmlspecialchars($acc['description'] ?? '') ?></small>
                                                </td>
                                                <td style="width: 20%;" class="text-end fw-bold">
                                                    <?= formatCurrency($acc['current_balance']) ?>
                                                </td>
                                                <td style="width: 10%;" class="text-end">
                                                    <a href="<?= BASE_URL ?>modules/accounting/ledger.php?account_id=<?= $acc['id'] ?>" class="btn btn-sm btn-link text-danger p-0" title="View Account Ledger">
                                                        Ledger <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Journal Entries Register -->
    <div class="col-12 col-xl-5">
        <div class="card-rms p-4 shadow-sm">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Recent Posted Journal Vouchers</h5>
            <?php if (empty($recentJournals)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-check fs-2"></i>
                    <div class="mt-2">No journal entries posted yet.</div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentJournals as $j): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-bold text-danger"><?= htmlspecialchars($j['entry_number']) ?></span>
                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($j['reference_type']) ?></span>
                                    <div class="small fw-semibold text-dark text-truncate mt-1" style="max-width: 250px;">
                                        <?= htmlspecialchars($j['description']) ?>
                                    </div>
                                    <small class="text-muted"><?= date('d-M-Y', strtotime($j['entry_date'])) ?> | By <?= htmlspecialchars($j['creator_name']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success-subtle text-success fs-6"><?= formatCurrency($j['total_debit']) ?></span>
                                    <div class="text-muted small">Balanced</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
