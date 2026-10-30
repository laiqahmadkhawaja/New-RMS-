<?php
// modules/inventory/index.php - Ingredients Master & Stock Levels
$pageTitle = 'Ingredients Stock';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('inventory.view');

$db = getDB();
$branchId = currentBranchId();

$ingredients = $db->query("
    SELECT i.*, u.code as unit_code, u.name as unit_name,
           (i.current_stock * i.cost_per_unit) as valuation 
    FROM ingredients i 
    JOIN units u ON i.unit_id = u.id 
    WHERE i.is_active = 1 
    ORDER BY (i.current_stock <= i.min_stock_alert) DESC, i.name ASC
")->fetchAll();

$units = $db->query("SELECT * FROM units WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$totalValuation = array_sum(array_column($ingredients, 'valuation'));
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-boxes text-danger me-2"></i> Ingredients & Stock Overview</h3>
        <p class="text-muted small mb-0">Total Live Inventory Valuation: <b class="text-dark fs-6"><?= formatCurrency($totalValuation) ?></b></p>
    </div>

    <!-- Actions (Slide-in panels per PRD, no modals) -->
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/inventory/ledger.php" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-journal-text me-1"></i> Stock Ledger
        </a>
        <a href="<?= BASE_URL ?>modules/inventory/wastage.php" class="btn btn-outline-danger btn-sm fw-semibold">
            <i class="bi bi-trash3 me-1"></i> Wastage Log
        </a>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddIngredient()">
            <i class="bi bi-plus-circle me-1"></i> Add Ingredient
        </button>
    </div>
</div>

<!-- Ingredients Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Code / Name</th>
                    <th>Category</th>
                    <th class="text-end">Current Stock</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total Valuation</th>
                    <th>Thresholds</th>
                    <th>Stock Health</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ingredients)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No ingredients registered.</td></tr>
                <?php else: ?>
                    <?php foreach ($ingredients as $ing): 
                        $stock = (float)$ing['current_stock'];
                        $min = (float)$ing['min_stock_alert'];
                        $isLow = $stock <= $min;
                        $isOut = $stock <= 0;
                    ?>
                        <tr class="<?= $isOut ? 'table-danger' : ($isLow ? 'table-warning' : '') ?>">
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($ing['name']) ?></span>
                                <div class="small text-muted font-monospace"><?= htmlspecialchars($ing['code']) ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border text-uppercase small"><?= htmlspecialchars($ing['category']) ?></span></td>
                            <td class="text-end fw-bold fs-6 <?= $isLow ? 'text-danger' : 'text-dark' ?>">
                                <?= number_format($stock, 2) ?> <small class="text-muted fw-normal"><?= htmlspecialchars($ing['unit_code']) ?></small>
                            </td>
                            <td class="text-end"><?= formatCurrency($ing['cost_per_unit']) ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($ing['valuation']) ?></td>
                            <td class="small">
                                <div>Alert: <b class="text-danger"><?= (float)$ing['min_stock_alert'] ?></b> <?= $ing['unit_code'] ?></div>
                                <div class="text-muted">Reorder: <?= (float)$ing['reorder_level'] ?> <?= $ing['unit_code'] ?></div>
                            </td>
                            <td>
                                <?php if ($isOut): ?>
                                    <span class="badge bg-danger">OUT OF STOCK</span>
                                <?php elseif ($isLow): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i> LOW STOCK</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i> Optimal</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick='openEditIngredient(<?= json_encode($ing) ?>)' title="Edit Details">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-outline-dark" onclick="openAdjustStock(<?= $ing['id'] ?>, '<?= htmlspecialchars($ing['name'], ENT_QUOTES) ?>', <?= $stock ?>, '<?= $ing['unit_code'] ?>')" title="Physical Stocktake Count">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= SLIDE PANELS (NO MODALS) ================= -->

<!-- 1. SLIDE PANEL: ADD / EDIT INGREDIENT -->
<div id="ingredientSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="ingPanelTitle">Add New Ingredient</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('ingredientSlidePanel')"></button>
    </div>
    <form id="ingForm" onsubmit="submitIngredient(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="ingredient_id" id="ingIdInput" value="0">

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Ingredient Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="ingNameInput" class="form-control" placeholder="e.g. Boneless Chicken, Cheddar Cheese..." required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Code / SKU <span class="text-danger">*</span></label>
                <input type="text" name="code" id="ingCodeInput" class="form-control" placeholder="e.g. ING-CHK-01" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Category</label>
                <select name="category" id="ingCategoryInput" class="form-select">
                    <option value="raw_meat">Raw Meat & Poultry</option>
                    <option value="dairy">Dairy & Cheese</option>
                    <option value="bakery">Bakery & Breads</option>
                    <option value="produce">Fresh Produce / Veggies</option>
                    <option value="frozen">Frozen Foods</option>
                    <option value="grocery">Oils & Grocery</option>
                    <option value="beverage">Beverages</option>
                    <option value="packaging">Packaging Materials</option>
                </select>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Unit of Measure <span class="text-danger">*</span></label>
                <select name="unit_id" id="ingUnitInput" class="form-select" required>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['code'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Cost Per Unit (Rs.)</label>
                <input type="number" name="cost_per_unit" id="ingCostInput" class="form-control" step="0.0001" placeholder="0.00" required>
            </div>
        </div>

        <div class="mb-3" id="openingStockDiv">
            <label class="form-label small fw-semibold text-muted text-uppercase">Initial Stock Balance</label>
            <input type="number" name="current_stock" id="ingStockInput" class="form-control" step="0.0001" value="0.00">
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Low Stock Alert</label>
                <input type="number" name="min_stock_alert" id="ingAlertInput" class="form-control" step="0.01" value="5.00">
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Reorder Level</label>
                <input type="number" name="reorder_level" id="ingReorderInput" class="form-control" step="0.01" value="10.00">
            </div>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Ingredient
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: PHYSICAL STOCK ADJUSTMENT -->
<div id="adjustSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Physical Stock Count Adjustment</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('adjustSlidePanel')"></button>
    </div>
    <form id="adjustForm" onsubmit="submitAdjustStock(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="ingredient_id" id="adjIngId">
        <div class="card p-3 bg-light border mb-3">
            <div class="fw-bold fs-6 text-dark" id="adjIngName"></div>
            <div class="small text-muted">Current System Stock: <b id="adjCurrentStock"></b></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Actual Counted Stock <span class="text-danger">*</span></label>
            <input type="number" name="actual_count" id="adjActualCount" class="form-control fw-bold" step="0.0001" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Reason for Discrepancy</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Physical inventory variance, damaged batch..." required>
        </div>

        <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold">
            <i class="bi bi-sliders me-1"></i> Post Inventory Adjustment
        </button>
    </form>
</div>

<script>
function openAddIngredient() {
    document.getElementById('ingPanelTitle').innerText = 'Add New Ingredient';
    document.getElementById('ingIdInput').value = '0';
    document.getElementById('ingNameInput').value = '';
    document.getElementById('ingCodeInput').value = '';
    document.getElementById('ingCostInput').value = '0';
    document.getElementById('ingStockInput').value = '0';
    document.getElementById('openingStockDiv').style.display = 'block';
    RMS.openSlidePanel('ingredientSlidePanel');
}

function openEditIngredient(i) {
    document.getElementById('ingPanelTitle').innerText = 'Edit: ' + i.name;
    document.getElementById('ingIdInput').value = i.id;
    document.getElementById('ingNameInput').value = i.name;
    document.getElementById('ingCodeInput').value = i.code;
    document.getElementById('ingCategoryInput').value = i.category;
    document.getElementById('ingUnitInput').value = i.unit_id;
    document.getElementById('ingCostInput').value = i.cost_per_unit;
    document.getElementById('ingAlertInput').value = i.min_stock_alert;
    document.getElementById('ingReorderInput').value = i.reorder_level;
    document.getElementById('openingStockDiv').style.display = 'none';
    RMS.openSlidePanel('ingredientSlidePanel');
}

async function submitIngredient(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('ingForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/inventory.php?action=save_ingredient', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}

function openAdjustStock(id, name, stock, unit) {
    document.getElementById('adjIngId').value = id;
    document.getElementById('adjIngName').innerText = name;
    document.getElementById('adjCurrentStock').innerText = `${stock} ${unit}`;
    document.getElementById('adjActualCount').value = stock;
    RMS.openSlidePanel('adjustSlidePanel');
}

async function submitAdjustStock(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('adjustForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/inventory.php?action=adjust_stock', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
