<?php
// modules/accounting/ledger.php - General Ledger Account Drilldown
$pageTitle = 'Account Ledger';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('accounting.view');

$db = getDB();
$accountId = (int)($_GET['account_id'] ?? 1);

$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch();
if (!$account) {
    echo "<div class='p-4'><div class='alert alert-warning d-flex align-items-center justify-content-between'><span><i class='bi bi-exclamation-triangle me-2'></i>Account not found or invalid account ID specified.</span> <a href='index.php' class='btn btn-sm btn-outline-dark'>Return to Accounting</a></div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$lines = $db->query("
    SELECT jel.*, je.entry_number, je.entry_date, je.description as journal_desc, je.reference_type 
    FROM journal_entry_lines jel 
    JOIN journal_entries je ON jel.journal_entry_id = je.id 
    WHERE jel.account_id = $accountId 
    ORDER BY je.entry_date ASC, jel.id ASC
")->fetchAll();

$allAccounts = $db->query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY code ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-journal-text text-danger me-2"></i> Account Ledger Statement</h3>
        <p class="text-muted small mb-0"><code><?= $account['code'] ?></code> - <?= htmlspecialchars($account['name']) ?> (<?= strtoupper($account['account_type']) ?>)</p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" onchange="window.location.href='?account_id=' + this.value">
            <?php foreach ($allAccounts as $acc): ?>
                <option value="<?= $acc['id'] ?>" <?= $acc['id'] == $accountId ? 'selected' : '' ?>>
                    <?= $acc['code'] ?> - <?= htmlspecialchars($acc['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm text-nowrap">
            <i class="bi bi-printer"></i> Print
        </button>
        <a href="<?= BASE_URL ?>modules/accounting/index.php" class="btn btn-outline-secondary btn-sm text-nowrap">
            <i class="bi bi-arrow-left"></i> Accounts
        </a>
    </div>
</div>

<!-- Balance Summary Card -->
<div class="card-rms p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
    <div>
        <small class="text-muted text-uppercase fw-bold">Account Classification</small>
        <div class="fw-bold text-dark fs-5"><?= ucfirst($account['account_type']) ?> (<?= htmlspecialchars($account['sub_type'] ?? '') ?>)</div>
    </div>
    <div class="text-end">
        <small class="text-muted text-uppercase fw-bold">Current Closing Balance</small>
        <h3 class="fw-bold text-danger mb-0"><?= formatCurrency($account['current_balance']) ?></h3>
    </div>
</div>

<!-- Ledger Transactions Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light text-uppercase fw-bold">
                <tr>
                    <th>Date</th>
                    <th>Voucher #</th>
                    <th>Reference</th>
                    <th>Narration / Details</th>
                    <th class="text-end">Debit (Rs.)</th>
                    <th class="text-end">Credit (Rs.)</th>
                    <th class="text-end">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lines)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No posted transactions in this ledger account.</td></tr>
                <?php else: ?>
                    <?php 
                    $running = 0.00;
                    $isDebitNormal = in_array($account['account_type'], ['asset', 'cogs', 'expense']);
                    foreach ($lines as $l): 
                        $deb = (float)$l['debit'];
                        $crd = (float)$l['credit'];
                        if ($isDebitNormal) {
                            $running += ($deb - $crd);
                        } else {
                            $running += ($crd - $deb);
                        }
                    ?>
                        <tr>
                            <td class="text-muted"><?= date('d-M-Y', strtotime($l['entry_date'])) ?></td>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($l['entry_number']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['reference_type']) ?></span></td>
                            <td class="text-dark">
                                <?= htmlspecialchars($l['journal_desc']) ?>
                                <?= !empty($l['notes']) ? '<small class="text-muted d-block">' . htmlspecialchars($l['notes']) . '</small>' : '' ?>
                            </td>
                            <td class="text-end text-primary fw-semibold"><?= $deb > 0 ? formatCurrency($deb) : '-' ?></td>
                            <td class="text-end text-success fw-semibold"><?= $crd > 0 ? formatCurrency($crd) : '-' ?></td>
                            <td class="text-end fw-bold fs-6"><?= formatCurrency($running) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
