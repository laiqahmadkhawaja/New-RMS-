<?php
// modules/shifts/index.php - Cashier Registers & Shift Reconciliation (PRD 18.3 & 18.4)
$pageTitle = 'Cashier Shifts';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('shifts.manage');

$db = getDB();
$branchId = currentBranchId();

$shifts = $db->query("
    SELECT cs.*, cr.name as register_name, u.full_name as cashier_name 
    FROM cash_shifts cs 
    JOIN cash_registers cr ON cs.register_id = cr.id 
    JOIN users u ON cs.user_id = u.id 
    WHERE cs.branch_id = $branchId 
    ORDER BY cs.id DESC LIMIT 50
")->fetchAll();

$registers = $db->query("SELECT * FROM cash_registers WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
$activeShift = getActiveCashShift($branchId);
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-wallet2 text-danger me-2"></i> Cashier Registers & Shifts</h3>
        <p class="text-muted small mb-0">Shift opening cash float, live sales tracking, and end-of-shift cash drawer reconciliation</p>
    </div>

    <!-- Actions (No modals) -->
    <div class="d-flex gap-2">
        <?php if ($activeShift): ?>
            <button type="button" class="btn btn-warning text-dark btn-sm fw-semibold" onclick="openCloseShiftModal(<?= $activeShift['id'] ?>, '<?= $activeShift['shift_number'] ?>', <?= $activeShift['opening_cash'] ?>)">
                <i class="bi bi-door-closed me-1"></i> Close Active Shift
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="RMS.openSlidePanel('openShiftPanel')">
                <i class="bi bi-plus-circle me-1"></i> Open Register Shift
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Active Shift Banner if Open -->
<?php if ($activeShift): 
    $shiftId = $activeShift['id'];
    $curCashSales = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE shift_id = $shiftId AND payment_method_id = 1")->fetchColumn();
    $curDigitalSales = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE shift_id = $shiftId AND payment_method_id != 1")->fetchColumn();
    $expectedNow = (float)$activeShift['opening_cash'] + $curCashSales;
?>
    <div class="card p-4 border-0 shadow-sm bg-danger-subtle text-danger mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-danger-subtle pb-2">
            <div>
                <span class="badge bg-danger text-white">LIVE ACTIVE SHIFT</span>
                <h4 class="fw-bold mb-0 mt-1"><?= htmlspecialchars($activeShift['shift_number']) ?></h4>
                <small>Opened on <?= date('d M, Y h:i A', strtotime($activeShift['opened_at'])) ?></small>
            </div>
            <button type="button" class="btn btn-danger fw-semibold px-4" onclick="openCloseShiftModal(<?= $activeShift['id'] ?>, '<?= $activeShift['shift_number'] ?>', <?= $activeShift['opening_cash'] ?>)">
                <i class="bi bi-door-closed me-1"></i> Finalize & Close Register
            </button>
        </div>

        <div class="row g-3 text-dark">
            <div class="col-6 col-md-3">
                <small class="text-muted fw-bold text-uppercase">Opening Cash Float</small>
                <div class="fw-bold fs-5"><?= formatCurrency($activeShift['opening_cash']) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted fw-bold text-uppercase">Cash Sales Received</small>
                <div class="fw-bold fs-5 text-success"><?= formatCurrency($curCashSales) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted fw-bold text-uppercase">Digital / Card Sales</small>
                <div class="fw-bold fs-5"><?= formatCurrency($curDigitalSales) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted fw-bold text-uppercase">Expected Cash in Drawer</small>
                <div class="fw-bold fs-5 text-danger"><?= formatCurrency($expectedNow) ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Shift History Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light text-uppercase fw-bold">
                <tr>
                    <th>Shift #</th>
                    <th>Register</th>
                    <th>Cashier</th>
                    <th>Opened</th>
                    <th>Closed</th>
                    <th class="text-end">Opening</th>
                    <th class="text-end">Cash Sales</th>
                    <th class="text-end">Expected</th>
                    <th class="text-end">Actual Count</th>
                    <th class="text-end">Difference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shifts)): ?>
                    <tr><td colspan="11" class="text-center py-5 text-muted">No cashier shift records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($shifts as $s): 
                        $diff = (float)($s['cash_difference'] ?? 0);
                    ?>
                        <tr>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($s['shift_number']) ?></td>
                            <td><?= htmlspecialchars($s['register_name']) ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($s['cashier_name']) ?></td>
                            <td class="text-muted"><?= date('d-M H:i', strtotime($s['opened_at'])) ?></td>
                            <td class="text-muted"><?= $s['closed_at'] ? date('d-M H:i', strtotime($s['closed_at'])) : '-' ?></td>
                            <td class="text-end"><?= formatCurrency($s['opening_cash']) ?></td>
                            <td class="text-end text-success"><?= formatCurrency($s['total_cash_sales']) ?></td>
                            <td class="text-end"><?= $s['closing_cash_expected'] ? formatCurrency($s['closing_cash_expected']) : '-' ?></td>
                            <td class="text-end fw-bold"><?= $s['closing_cash_actual'] ? formatCurrency($s['closing_cash_actual']) : '-' ?></td>
                            <td class="text-end fw-bold <?= $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : 'text-muted') ?>">
                                <?= $s['status'] === 'closed' ? formatCurrency($diff) : '-' ?>
                            </td>
                            <td>
                                <span class="badge <?= $s['status'] === 'open' ? 'bg-success' : 'bg-secondary' ?> text-capitalize"><?= $s['status'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= SLIDE PANELS (NO MODALS) ================= -->

<!-- 1. SLIDE PANEL: OPEN SHIFT -->
<div id="openShiftPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-danger">Open Cash Register Shift</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('openShiftPanel')"></button>
    </div>
    <form id="openShiftForm" onsubmit="submitOpenShift(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Assigned Register Terminal <span class="text-danger">*</span></label>
            <select name="register_id" class="form-select" required>
                <?php foreach ($registers as $reg): ?>
                    <option value="<?= $reg['id'] ?>"><?= htmlspecialchars($reg['name']) ?> (<?= $reg['code'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Opening Cash Float Amount (Rs.) <span class="text-danger">*</span></label>
            <input type="number" name="opening_cash" class="form-control form-control-lg fw-bold text-danger" step="0.01" value="5000.00" required>
            <small class="text-muted">Count starting cash in the till before ringing orders.</small>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Opening Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Morning / Evening shift notes..."></textarea>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-door-open me-1"></i> Start Shift Session
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: CLOSE SHIFT RECONCILIATION -->
<div id="closeShiftPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-dark">Shift Closing & Cash Count</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('closeShiftPanel')"></button>
    </div>
    <form id="closeShiftForm" onsubmit="submitCloseShift(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="shift_id" id="closeShiftId">

        <div class="card p-3 bg-light border mb-3">
            <div class="small text-muted">Closing Shift: <b id="closeShiftNumDisplay"></b></div>
            <div class="small text-muted">Opening Float: <b id="closeOpenCashDisplay"></b></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Actual Physical Cash Counted (Rs.) <span class="text-danger">*</span></label>
            <input type="number" name="actual_cash" id="closeActualCash" class="form-control form-control-lg fw-bold text-dark" step="0.01" placeholder="0.00" required>
            <small class="text-muted">Enter exact physical cash counted in drawer.</small>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Discrepancy Explanation (If any)</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Reason for shortage or surplus..."></textarea>
        </div>

        <button type="submit" class="btn btn-dark w-100 py-2 fw-bold">
            <i class="bi bi-lock-fill me-1"></i> Reconcile & Close Shift
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open') === '1' || urlParams.get('action') === 'open') {
        RMS.openSlidePanel('openShiftPanel');
    }
});

async function submitOpenShift(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('openShiftForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/shifts.php?action=open_shift', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}

function openCloseShiftModal(id, num, opening) {
    document.getElementById('closeShiftId').value = id;
    document.getElementById('closeShiftNumDisplay').innerText = num;
    document.getElementById('closeOpenCashDisplay').innerText = RMS.formatMoney(opening);
    RMS.openSlidePanel('closeShiftPanel');
}

async function submitCloseShift(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('closeShiftForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/shifts.php?action=close_shift', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
