<?php
// modules/inventory/ledger.php - Immutable Stock Movement Ledger (PRD 10.3)
$pageTitle = 'Stock Movement Ledger';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('inventory.view');

$db = getDB();
$branchId = currentBranchId();

$ingFilter = (int)get('ingredient_id', 0);
$typeFilter = cleanInput(get('movement_type', ''));

$sql = "
    SELECT sm.*, i.name as ingredient_name, i.code as ingredient_code, u.code as unit_code, usr.full_name as user_name 
    FROM stock_movements sm 
    JOIN ingredients i ON sm.ingredient_id = i.id 
    JOIN units u ON i.unit_id = u.id 
    JOIN users usr ON sm.user_id = usr.id 
    WHERE sm.branch_id = ? 
";
$params = [$branchId];

if ($ingFilter) {
    $sql .= " AND sm.ingredient_id = ?";
    $params[] = $ingFilter;
}
if (!empty($typeFilter)) {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY sm.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

$ingredients = $db->query("SELECT id, name, code FROM ingredients WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-journal-text text-danger me-2"></i> Stock Movement Ledger</h3>
        <p class="text-muted small mb-0">Immutable historical audit trail of all receipts, kitchen consumptions, wastage, and adjustments</p>
    </div>
    <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Stock Overview
    </a>
</div>

<!-- Filters -->
<div class="card-rms p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label small fw-semibold text-muted">Ingredient</label>
            <select name="ingredient_id" class="form-select form-select-sm">
                <option value="0">All Ingredients</option>
                <?php foreach ($ingredients as $ing): ?>
                    <option value="<?= $ing['id'] ?>" <?= $ingFilter == $ing['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ing['name']) ?> (<?= $ing['code'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5">
            <label class="form-label small fw-semibold text-muted">Movement Type</label>
            <select name="movement_type" class="form-select form-select-sm">
                <option value="">All Movement Types</option>
                <option value="purchase_in" <?= $typeFilter === 'purchase_in' ? 'selected' : '' ?>>Purchase In (Stock Received)</option>
                <option value="sale_consumption" <?= $typeFilter === 'sale_consumption' ? 'selected' : '' ?>>Sale Recipe Consumption</option>
                <option value="wastage" <?= $typeFilter === 'wastage' ? 'selected' : '' ?>>Wastage & Spoilage</option>
                <option value="adjustment_in" <?= $typeFilter === 'adjustment_in' ? 'selected' : '' ?>>Adjustment In</option>
                <option value="adjustment_out" <?= $typeFilter === 'adjustment_out' ? 'selected' : '' ?>>Adjustment Out</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold">
                <i class="bi bi-filter"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Ledger Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light text-uppercase fw-bold">
                <tr>
                    <th>Date & Time</th>
                    <th>Ingredient</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th class="text-end">Qty In / Out</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Value</th>
                    <th class="text-end">Balance After</th>
                    <th>Staff User</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No stock ledger transactions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): 
                        $qty = (float)$m['quantity'];
                        $isPositive = $qty > 0;
                    ?>
                        <tr>
                            <td class="text-muted"><?= date('d-M-Y H:i', strtotime($m['created_at'])) ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($m['ingredient_name']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?php
                                    echo match($m['movement_type']) {
                                        'purchase_in' => 'bg-success',
                                        'sale_consumption' => 'bg-primary-subtle text-primary border',
                                        'wastage' => 'bg-danger text-white',
                                        default => 'bg-secondary'
                                    };
                                ?> text-capitalize"><?= str_replace('_', ' ', $m['movement_type']) ?></span>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($m['notes'] ?? '-') ?></td>
                            <td class="text-end fw-bold <?= $isPositive ? 'text-success' : 'text-danger' ?>">
                                <?= $isPositive ? '+' : '' ?><?= number_format($qty, 4) ?> <?= $m['unit_code'] ?>
                            </td>
                            <td class="text-end"><?= formatCurrency($m['unit_cost']) ?></td>
                            <td class="text-end fw-semibold"><?= formatCurrency($m['total_cost']) ?></td>
                            <td class="text-end fw-bold text-dark"><?= number_format($m['balance_after'], 4) ?> <?= $m['unit_code'] ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($m['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
