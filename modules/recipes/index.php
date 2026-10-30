<?php
// modules/recipes/index.php - Recipe Management & Food Costing Overview
$pageTitle = 'Recipes & Costing';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('recipes.manage');

$db = getDB();

$recipes = $db->query("
    SELECT r.*, m.name as item_name, m.base_price, mv.name as variant_name, mv.final_price,
           (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id) as ingredients_count 
    FROM recipes r 
    JOIN menu_items m ON r.menu_item_id = m.id 
    LEFT JOIN menu_variants mv ON r.menu_variant_id = mv.id 
    WHERE r.is_active = 1 
    ORDER BY m.name ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-journals text-danger me-2"></i> Recipes & Food Costing</h3>
        <p class="text-muted small mb-0">Link menu items to ingredient consumption, calculate yield, prep loss, and food cost %</p>
    </div>
    <a href="<?= BASE_URL ?>modules/recipes/recipe_form.php" class="btn btn-danger btn-sm fw-semibold">
        <i class="bi bi-plus-circle me-1"></i> Formulate New Recipe
    </a>
</div>

<!-- Recipe List Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Menu Item / Variant</th>
                    <th>Yield Qty</th>
                    <th>Prep Loss</th>
                    <th>Ingredients</th>
                    <th>Total Recipe Cost</th>
                    <th>Selling Price</th>
                    <th>Food Cost %</th>
                    <th>Gross Margin</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recipes)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-2"></i>
                            <div class="mt-2">No recipes formulated yet.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recipes as $r): 
                        $sellingPrice = (float)($r['final_price'] ?: $r['base_price']);
                        $cost = (float)$r['total_cost'];
                        $foodCostPercent = $sellingPrice > 0 ? round(($cost / $sellingPrice) * 100, 1) : 0;
                        $margin = $sellingPrice - $cost;
                    ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($r['item_name']) ?></span>
                                <?= $r['variant_name'] ? '<span class="badge bg-secondary-subtle text-secondary ms-1">' . htmlspecialchars($r['variant_name']) . '</span>' : '' ?>
                                <div class="small text-muted">Version <?= $r['version'] ?></div>
                            </td>
                            <td><?= (float)$r['yield_quantity'] ?> portion(s)</td>
                            <td><?= (float)$r['prep_loss_percent'] ?>%</td>
                            <td><span class="badge bg-light text-dark border"><?= $r['ingredients_count'] ?> items</span></td>
                            <td class="fw-bold text-dark"><?= formatCurrency($cost) ?></td>
                            <td class="fw-semibold"><?= formatCurrency($sellingPrice) ?></td>
                            <td>
                                <span class="badge <?php
                                    echo match(true) {
                                        $foodCostPercent <= 30 => 'bg-success',
                                        $foodCostPercent <= 40 => 'bg-warning text-dark',
                                        default => 'bg-danger'
                                    };
                                ?> fs-6"><?= $foodCostPercent ?>%</span>
                            </td>
                            <td>
                                <span class="fw-bold text-success"><?= formatCurrency($margin) ?></span>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>modules/recipes/recipe_form.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil-square"></i> Edit Recipe
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
