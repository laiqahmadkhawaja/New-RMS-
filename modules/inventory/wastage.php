<?php
// modules/inventory/wastage.php - Stock Wastage & Spoilage Write-Off
$pageTitle = 'Stock Wastage';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('inventory.adjust');

$db = getDB();
$branchId = currentBranchId();

$wastageList = $db->query("
    SELECT sw.*, i.name as ingredient_name, i.code as ingredient_code, u.code as unit_code, usr.full_name as staff_name 
    FROM stock_wastage sw 
    JOIN ingredients i ON sw.ingredient_id = i.id 
    JOIN units u ON sw.unit_id = u.id 
    JOIN users usr ON sw.recorded_by = usr.id 
    WHERE sw.branch_id = $branchId 
    ORDER BY sw.id DESC LIMIT 100
")->fetchAll();

$ingredients = $db->query("SELECT i.*, u.code as unit_code FROM ingredients i JOIN units u ON i.unit_id = u.id WHERE i.is_active = 1 ORDER BY i.name ASC")->fetchAll();
$totalWastedValue = array_sum(array_column($wastageList, 'total_cost'));
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-trash3 text-danger me-2"></i> Ingredient Wastage & Spoilage Log</h3>
        <p class="text-muted small mb-0">Total Wasted Inventory: <b class="text-danger fs-6"><?= formatCurrency($totalWastedValue) ?></b> (Automatically posted to accounting loss)</p>
    </div>

    <!-- Actions (No modals) -->
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Stock Overview
        </a>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="RMS.openSlidePanel('logWastagePanel')">
            <i class="bi bi-plus-circle me-1"></i> Log Wastage Event
        </button>
    </div>
</div>

<!-- Wastage Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Date & Time</th>
                    <th>Ingredient</th>
                    <th>Quantity Wasted</th>
                    <th>Unit Cost</th>
                    <th>Total Loss Value</th>
                    <th>Reason / Cause</th>
                    <th>Staff Member</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wastageList)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No ingredient wastage recorded. Excellent kitchen control!</td></tr>
                <?php else: ?>
                    <?php foreach ($wastageList as $w): ?>
                        <tr>
                            <td class="text-muted small"><?= date('d M, Y H:i', strtotime($w['created_at'])) ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($w['ingredient_name']) ?></span>
                                <div class="small text-muted font-monospace"><?= htmlspecialchars($w['ingredient_code']) ?></div>
                            </td>
                            <td class="fw-bold text-danger">
                                <?= (float)$w['quantity'] ?> <?= htmlspecialchars($w['unit_code']) ?>
                            </td>
                            <td><?= formatCurrency($w['cost_per_unit']) ?></td>
                            <td class="fw-bold text-danger"><?= formatCurrency($w['total_cost']) ?></td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger mb-1"><?= htmlspecialchars($w['reason']) ?></span>
                                <?php if (!empty($w['notes'])): ?>
                                    <div class="small text-muted fst-italic"><?= htmlspecialchars($w['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($w['staff_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= SLIDE PANEL: LOG WASTAGE (NO MODAL) ================= -->
<div id="logWastagePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-danger">Record Stock Wastage</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('logWastagePanel')"></button>
    </div>
    <form id="wastageForm" onsubmit="submitWastage(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Wasted Ingredient <span class="text-danger">*</span></label>
            <select name="ingredient_id" id="wasteIngSelect" class="form-select" onchange="onWasteIngChange(this)" required>
                <option value="">-- Choose Ingredient --</option>
                <?php foreach ($ingredients as $i): ?>
                    <option value="<?= $i['id'] ?>" data-unit="<?= $i['unit_id'] ?>" data-unitcode="<?= $i['unit_code'] ?>" data-cost="<?= $i['cost_per_unit'] ?>">
                        <?= htmlspecialchars($i['name']) ?> (<?= formatCurrency($i['cost_per_unit']) ?>/<?= $i['unit_code'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" name="unit_id" id="wasteUnitId">

        <div class="row g-2 mb-3">
            <div class="col-8">
                <label class="form-label small fw-semibold text-muted text-uppercase">Quantity Wasted <span class="text-danger">*</span></label>
                <input type="number" name="quantity" id="wasteQtyInput" class="form-control fw-bold" step="0.0001" placeholder="0.00" oninput="calcWasteLoss()" required>
            </div>
            <div class="col-4">
                <label class="form-label small fw-semibold text-muted text-uppercase">Unit</label>
                <input type="text" id="wasteUnitCodeDisplay" class="form-control" readonly placeholder="-">
            </div>
        </div>

        <div class="card p-3 bg-light border mb-3 text-center">
            <small class="text-muted fw-semibold">Estimated Write-Off Loss</small>
            <h4 class="fw-bold text-danger mb-0" id="wasteLossDisplay">Rs. 0.00</h4>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Reason / Cause <span class="text-danger">*</span></label>
            <select name="reason" class="form-select" required>
                <option value="Spoiled / Bad Quality">Spoiled / Bad Quality</option>
                <option value="Expired Shelf Life">Expired Shelf Life</option>
                <option value="Kitchen Preparation Mistake">Kitchen Preparation Mistake</option>
                <option value="Accidental Drop / Spill">Accidental Drop / Spill</option>
                <option value="Overcooked / Burnt">Overcooked / Burnt</option>
                <option value="Packaging Defect">Packaging Defect</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Additional Explanatory Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Explain circumstances for supervisor review..."></textarea>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-trash3 me-1"></i> Post Wastage Write-Off
        </button>
    </form>
</div>

<script>
function onWasteIngChange(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('wasteUnitId').value = opt.getAttribute('data-unit') || '';
    document.getElementById('wasteUnitCodeDisplay').value = opt.getAttribute('data-unitcode') || '';
    calcWasteLoss();
}

function calcWasteLoss() {
    const select = document.getElementById('wasteIngSelect');
    const opt = select.options[select.selectedIndex];
    const cost = parseFloat(opt.getAttribute('data-cost')) || 0;
    const qty = parseFloat(document.getElementById('wasteQtyInput').value) || 0;
    const total = cost * qty;
    document.getElementById('wasteLossDisplay').innerText = RMS.formatMoney(total);
}

async function submitWastage(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('wastageForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/inventory.php?action=record_wastage', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
