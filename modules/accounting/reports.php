<?php
// modules/accounting/reports.php - Financial Statements (Trial Balance, P&L, Balance Sheet)
$pageTitle = 'Financial Statements';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('accounting.view');

$db = getDB();
$branchId = currentBranchId();
$tab = get('tab', 'trial_balance');

// 1. Fetch Trial Balance data (Accounts with balances)
$accounts = $db->query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY code ASC")->fetchAll();

$trialDebitTotal = 0.00;
$trialCreditTotal = 0.00;
$trialRows = [];

foreach ($accounts as $a) {
    $bal = (float)$a['current_balance'];
    $deb = 0.00;
    $crd = 0.00;

    // Normal balance conventions:
    // Assets, COGS, Expenses: Debit balance
    // Liabilities, Equity, Revenue: Credit balance
    if (in_array($a['account_type'], ['asset', 'cogs', 'expense'])) {
        if ($bal >= 0) $deb = $bal;
        else $crd = abs($bal);
    } else {
        if ($bal >= 0) $crd = $bal;
        else $deb = abs($bal);
    }

    $trialDebitTotal += $deb;
    $trialCreditTotal += $crd;

    $trialRows[] = [
        'code' => $a['code'],
        'name' => $a['name'],
        'type' => $a['account_type'],
        'debit' => $deb,
        'credit' => $crd
    ];
}

// 2. Profit and Loss calculations
$revenueAccounts = array_filter($accounts, fn($a) => $a['account_type'] === 'revenue');
$cogsAccounts = array_filter($accounts, fn($a) => $a['account_type'] === 'cogs');
$expenseAccounts = array_filter($accounts, fn($a) => $a['account_type'] === 'expense');

$totalRevenue = array_sum(array_column($revenueAccounts, 'current_balance'));
$totalCogs = array_sum(array_column($cogsAccounts, 'current_balance'));
$grossProfit = $totalRevenue - $totalCogs;
$totalExpenses = array_sum(array_column($expenseAccounts, 'current_balance'));
$netProfit = $grossProfit - $totalExpenses;

// 3. Balance Sheet calculations
$assetAccounts = array_filter($accounts, fn($a) => $a['account_type'] === 'asset');
$liabilityAccounts = array_filter($accounts, fn($a) => $a['account_type'] === 'liability');
$equityAccounts = array_filter($accounts, fn($a) => $a['account_type'] === 'equity');

$totalAssets = array_sum(array_column($assetAccounts, 'current_balance'));
$totalLiabilities = array_sum(array_column($liabilityAccounts, 'current_balance'));
$totalEquity = array_sum(array_column($equityAccounts, 'current_balance')) + $netProfit;
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph text-danger me-2"></i> Financial Statements</h3>
        <p class="text-muted small mb-0">Standard GAAP & IFRS compliant reports generated directly from balanced journal entries</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm fw-semibold">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
        <a href="<?= BASE_URL ?>modules/accounting/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> General Ledger
        </a>
    </div>
</div>

<!-- Statement Tabs -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'trial_balance' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=trial_balance">
            Trial Balance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'pnl' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=pnl">
            Profit & Loss Statement (P&L)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'balance_sheet' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=balance_sheet">
            Balance Sheet
        </a>
    </li>
</ul>

<!-- ================= 1. TRIAL BALANCE TAB ================= -->
<?php if ($tab === 'trial_balance'): ?>
    <div class="card-rms p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold mb-0">Trial Balance Verification</h5>
                <small class="text-muted">As of <?= date('d-M-Y') ?></small>
            </div>
            <div>
                <?php if (abs($trialDebitTotal - $trialCreditTotal) < 0.05): ?>
                    <span class="badge bg-success-subtle text-success fs-6"><i class="bi bi-check-circle-fill me-1"></i> Perfectly Balanced</span>
                <?php else: ?>
                    <span class="badge bg-danger text-white fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i> Discrepancy Found</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 font-monospace">
                <thead class="table-light text-uppercase fw-bold small">
                    <tr>
                        <th>Account Code</th>
                        <th>Account Description</th>
                        <th>Classification</th>
                        <th class="text-end">Debit (Rs.)</th>
                        <th class="text-end">Credit (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trialRows as $tr): ?>
                        <tr>
                            <td><code><?= $tr['code'] ?></code></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($tr['name']) ?></td>
                            <td><span class="badge bg-light text-dark border text-uppercase small"><?= $tr['type'] ?></span></td>
                            <td class="text-end fw-bold text-primary"><?= $tr['debit'] > 0 ? formatCurrency($tr['debit']) : '-' ?></td>
                            <td class="text-end fw-bold text-success"><?= $tr['credit'] > 0 ? formatCurrency($tr['credit']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark fs-6 fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">TOTALS:</td>
                        <td class="text-end text-info"><?= formatCurrency($trialDebitTotal) ?></td>
                        <td class="text-end text-success"><?= formatCurrency($trialCreditTotal) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ================= 2. PROFIT & LOSS STATEMENT TAB ================= -->
<?php if ($tab === 'pnl'): ?>
    <div class="card-rms p-4 shadow-sm" style="max-width: 900px; margin: auto;">
        <div class="text-center mb-4 border-bottom pb-3">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></h4>
            <h5 class="text-uppercase text-secondary fw-bold mb-0">Statement of Profit & Loss</h5>
            <small class="text-muted">For the period ending <?= date('d M, Y') ?></small>
        </div>

        <!-- Operating Revenue -->
        <h6 class="fw-bold text-uppercase text-muted border-bottom pb-1">1. Operating Revenue</h6>
        <table class="table table-sm mb-3">
            <tbody>
                <?php foreach ($revenueAccounts as $ra): ?>
                    <tr>
                        <td><?= htmlspecialchars($ra['name']) ?> (<code><?= $ra['code'] ?></code>)</td>
                        <td class="text-end fw-semibold"><?= formatCurrency($ra['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top">
                    <td>Total Operating Revenue</td>
                    <td class="text-end text-success fs-6"><?= formatCurrency($totalRevenue) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Cost of Goods Sold -->
        <h6 class="fw-bold text-uppercase text-muted border-bottom pb-1">2. Cost of Goods Sold (Direct Costs)</h6>
        <table class="table table-sm mb-3">
            <tbody>
                <?php foreach ($cogsAccounts as $ca): ?>
                    <tr>
                        <td><?= htmlspecialchars($ca['name']) ?> (<code><?= $ca['code'] ?></code>)</td>
                        <td class="text-end text-muted"><?= formatCurrency($ca['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top">
                    <td>Total Cost of Goods Sold (COGS)</td>
                    <td class="text-end text-danger"><?= formatCurrency($totalCogs) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Gross Profit -->
        <div class="card p-3 bg-light border-0 mb-4 d-flex flex-row justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">GROSS PROFIT</h5>
            <h4 class="fw-bold mb-0 text-success"><?= formatCurrency($grossProfit) ?></h4>
        </div>

        <!-- Operating Expenses -->
        <h6 class="fw-bold text-uppercase text-muted border-bottom pb-1">3. Operating Expenses</h6>
        <table class="table table-sm mb-3">
            <tbody>
                <?php foreach ($expenseAccounts as $ea): ?>
                    <tr>
                        <td><?= htmlspecialchars($ea['name']) ?> (<code><?= $ea['code'] ?></code>)</td>
                        <td class="text-end text-muted"><?= formatCurrency($ea['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top">
                    <td>Total Operating Expenses</td>
                    <td class="text-end text-danger"><?= formatCurrency($totalExpenses) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Net Profit -->
        <div class="card p-3 <?= $netProfit >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> border-0 d-flex flex-row justify-content-between align-items-center">
            <h4 class="fw-bold mb-0">NET OPERATING PROFIT</h4>
            <h3 class="fw-bold mb-0"><?= formatCurrency($netProfit) ?></h3>
        </div>
    </div>
<?php endif; ?>

<!-- ================= 3. BALANCE SHEET TAB ================= -->
<?php if ($tab === 'balance_sheet'): ?>
    <div class="card-rms p-4 shadow-sm" style="max-width: 900px; margin: auto;">
        <div class="text-center mb-4 border-bottom pb-3">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></h4>
            <h5 class="text-uppercase text-secondary fw-bold mb-0">Balance Sheet</h5>
            <small class="text-muted">As of <?= date('d M, Y') ?></small>
        </div>

        <!-- Assets -->
        <h5 class="fw-bold text-primary border-bottom pb-1 mb-2">ASSETS</h5>
        <table class="table table-sm mb-4">
            <tbody>
                <?php foreach ($assetAccounts as $aa): ?>
                    <tr>
                        <td><?= htmlspecialchars($aa['name']) ?> (<code><?= $aa['code'] ?></code>)</td>
                        <td class="text-end fw-semibold"><?= formatCurrency($aa['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-primary fw-bold fs-6">
                    <td>TOTAL ASSETS</td>
                    <td class="text-end text-primary"><?= formatCurrency($totalAssets) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Liabilities -->
        <h5 class="fw-bold text-danger border-bottom pb-1 mb-2">LIABILITIES</h5>
        <table class="table table-sm mb-4">
            <tbody>
                <?php foreach ($liabilityAccounts as $la): ?>
                    <tr>
                        <td><?= htmlspecialchars($la['name']) ?> (<code><?= $la['code'] ?></code>)</td>
                        <td class="text-end fw-semibold"><?= formatCurrency($la['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-danger fw-bold fs-6">
                    <td>TOTAL LIABILITIES</td>
                    <td class="text-end text-danger"><?= formatCurrency($totalLiabilities) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Equity -->
        <h5 class="fw-bold text-success border-bottom pb-1 mb-2">EQUITY</h5>
        <table class="table table-sm mb-4">
            <tbody>
                <?php foreach ($equityAccounts as $eq): ?>
                    <tr>
                        <td><?= htmlspecialchars($eq['name']) ?> (<code><?= $eq['code'] ?></code>)</td>
                        <td class="text-end fw-semibold"><?= formatCurrency($eq['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td>Current Period Net Retained Earnings</td>
                    <td class="text-end fw-semibold <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= formatCurrency($netProfit) ?>
                    </td>
                </tr>
                <tr class="table-success fw-bold fs-6">
                    <td>TOTAL LIABILITIES & EQUITY</td>
                    <td class="text-end text-success"><?= formatCurrency($totalLiabilities + $totalEquity) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
