<?php
// modules/accounting/journal_create.php - Dedicated Manual Journal Voucher Entry (PRD 17.3 & 17.5)
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/accounting_engine.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';

requirePermission('accounting.journals');

$pageTitle = 'Post Journal Voucher';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Security token expired.');
    } else {
        $entryDate = cleanInput(post('entry_date', date('Y-m-d')));
        $description = cleanInput(post('description'));
        $refType = cleanInput(post('reference_type', 'manual_journal'));

        $accIds = post('account_ids', []);
        $debits = post('debits', []);
        $credits = post('credits', []);
        $lineNotes = post('line_notes', []);

        $lines = [];
        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($accIds as $k => $accId) {
            $accId = (int)$accId;
            $deb = (float)($debits[$k] ?? 0);
            $crd = (float)($credits[$k] ?? 0);
            $note = cleanInput($lineNotes[$k] ?? '');

            if ($accId > 0 && ($deb > 0 || $crd > 0)) {
                $lines[] = [
                    'account_id' => $accId,
                    'debit' => $deb,
                    'credit' => $crd,
                    'notes' => $note
                ];
                $totalDebit += $deb;
                $totalCredit += $crd;
            }
        }

        if (count($lines) < 2) {
            setFlash('error', 'A double-entry journal requires at least two accounts.');
        } elseif (abs($totalDebit - $totalCredit) > 0.01) {
            setFlash('error', "Journal is unbalanced! Total Debits (" . formatCurrency($totalDebit) . ") must equal Total Credits (" . formatCurrency($totalCredit) . ").");
        } else {
            try {
                $db->beginTransaction();

                $entryId = AccountingEngine::createJournalEntry(
                    $branchId,
                    $refType,
                    null,
                    $description,
                    $lines,
                    $userId,
                    $entryDate
                );

                $db->commit();
                logAudit('manual_journal_posted', 'journal_entries', $entryId, null, ['description' => $description, 'amount' => $totalDebit]);

                setFlash('success', "Balanced Journal Voucher posted to General Ledger successfully!");
                echo "<script>window.location.href = '" . BASE_URL . "modules/accounting/index.php';</script>";
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', $e->getMessage());
            }
        }
    }
}

$accounts = $db->query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY code ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Create Manual Journal Voucher</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>modules/accounting/index.php">Accounting</a></li>
                <li class="breadcrumb-item active">New Journal Entry</li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>modules/accounting/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Accounting
    </a>
</div>

<form method="POST" action="" id="journalForm">
    <?= csrfField(); ?>
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Voucher Details</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Voucher Date <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Transaction Reference</label>
                        <input type="text" name="reference_type" class="form-control" value="manual_adjustment">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Narration / Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Explain the purpose of this accounting entry..." required></textarea>
                    </div>
                </div>
            </div>

            <!-- Debit & Credit Lines -->
            <div class="card-rms p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0">Journal Entry Lines</h5>
                    <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" onclick="addJournalLine()">
                        <i class="bi bi-plus-lg me-1"></i> Add Account Line
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="journalLinesTable">
                        <thead class="table-light small fw-bold text-uppercase">
                            <tr>
                                <th style="width: 45%;">Account Title</th>
                                <th style="width: 20%;" class="text-end">Debit (Rs.)</th>
                                <th style="width: 20%;" class="text-end">Credit (Rs.)</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody id="journalLinesBody"></tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td class="text-end">Totals:</td>
                                <td class="text-end text-primary" id="totDebitDisplay">0.00</td>
                                <td class="text-end text-success" id="totCreditDisplay">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Balancing Safeguard Panel -->
        <div class="col-12 col-lg-4">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Safeguards & Status</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Debits:</span>
                    <span class="fw-bold text-primary" id="panelDebit">Rs. 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Total Credits:</span>
                    <span class="fw-bold text-success" id="panelCredit">Rs. 0.00</span>
                </div>

                <div class="p-3 rounded text-center mb-3" id="balanceStatusBox" style="background: #fee2e2;">
                    <div class="fw-bold text-danger" id="balanceStatusText">UNBALANCED</div>
                    <small class="text-muted" id="balanceDiffText">Difference: Rs. 0.00</small>
                </div>

                <div class="small text-muted mb-3">
                    <i class="bi bi-shield-check text-success me-1"></i> Antigravity Safeguard: The system enforces that the sum of all debits must exactly equal the sum of all credits before posting.
                </div>
            </div>

            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow" id="btnSubmitJournal" disabled>
                <i class="bi bi-check2-circle me-1"></i> Post Journal Entry
            </button>
        </div>
    </div>
</form>

<script>
const accountsList = <?= json_encode($accounts) ?>;

function addJournalLine(defaultAcc = '', defaultDeb = 0, defaultCrd = 0) {
    const tbody = document.getElementById('journalLinesBody');
    const tr = document.createElement('tr');
    tr.className = 'journal-row';
    tr.innerHTML = `
        <td>
            <select name="account_ids[]" class="form-select form-select-sm j-acc" required>
                <option value="">-- Choose GL Account --</option>
                ${accountsList.map(a => `<option value="${a.id}" ${defaultAcc == a.id ? 'selected' : ''}>${a.code} - ${a.name} (${a.account_type.toUpperCase()})</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="number" name="debits[]" class="form-control form-control-sm text-end j-deb" step="0.01" value="${defaultDeb > 0 ? defaultDeb : ''}" placeholder="0.00" oninput="onDebitInput(this)">
        </td>
        <td>
            <input type="number" name="credits[]" class="form-control form-control-sm text-end j-crd" step="0.01" value="${defaultCrd > 0 ? defaultCrd : ''}" placeholder="0.00" oninput="onCreditInput(this)">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('tr').remove(); checkBalancing();">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    checkBalancing();
}

function onDebitInput(input) {
    const row = input.closest('tr');
    if (parseFloat(input.value) > 0) {
        row.querySelector('.j-crd').value = '';
    }
    checkBalancing();
}

function onCreditInput(input) {
    const row = input.closest('tr');
    if (parseFloat(input.value) > 0) {
        row.querySelector('.j-deb').value = '';
    }
    checkBalancing();
}

function checkBalancing() {
    let totDeb = 0.00;
    let totCrd = 0.00;

    document.querySelectorAll('.journal-row').forEach(row => {
        totDeb += parseFloat(row.querySelector('.j-deb').value) || 0;
        totCrd += parseFloat(row.querySelector('.j-crd').value) || 0;
    });

    document.getElementById('totDebitDisplay').innerText = totDeb.toFixed(2);
    document.getElementById('totCreditDisplay').innerText = totCrd.toFixed(2);
    document.getElementById('panelDebit').innerText = RMS.formatMoney(totDeb);
    document.getElementById('panelCredit').innerText = RMS.formatMoney(totCrd);

    const diff = Math.abs(totDeb - totCrd);
    const box = document.getElementById('balanceStatusBox');
    const text = document.getElementById('balanceStatusText');
    const diffText = document.getElementById('balanceDiffText');
    const btn = document.getElementById('btnSubmitJournal');

    if (diff < 0.01 && totDeb > 0) {
        box.style.background = '#dcfce7';
        text.innerText = 'BALANCED';
        text.className = 'fw-bold text-success';
        diffText.innerText = 'Debits match Credits!';
        btn.disabled = false;
    } else {
        box.style.background = '#fee2e2';
        text.innerText = 'UNBALANCED';
        text.className = 'fw-bold text-danger';
        diffText.innerText = 'Difference: ' + RMS.formatMoney(diff);
        btn.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Add 2 default empty rows for debit & credit
    addJournalLine(1); // Cash in Register
    addJournalLine(9); // Sales Revenue
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
