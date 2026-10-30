<?php
// modules/recipes/recipe_form.php - Dedicated Recipe Formulation & Costing Page
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';

requirePermission('recipes.manage');

$db = getDB();
$recipeId = (int)($_GET['id'] ?? 0);
$preselectedItemId = (int)($_GET['menu_item_id'] ?? 0);
$recipe = null;
$existingItems = [];

if ($recipeId) {
    $stmt = $db->prepare("SELECT * FROM recipes WHERE id = ?");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch();
    if ($recipe) {
        $existingItems = $db->query("SELECT * FROM recipe_ingredients WHERE recipe_id = $recipeId")->fetchAll();
    }
}

$pageTitle = $recipeId ? 'Edit Recipe Formulation' : 'New Recipe Formulation';
require_once __DIR__ . '/../../includes/header.php';

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Security token expired.');
    } else {
        $menuItemId = (int)post('menu_item_id');
        $variantId = !empty(post('menu_variant_id')) ? (int)post('menu_variant_id') : null;
        $yieldQty = (float)post('yield_quantity', 1.0);
        $prepLoss = (float)post('prep_loss_percent', 0.0);
        $instructions = cleanInput(post('instructions'));

        $ingIds = post('ingredient_ids', []);
        $quantities = post('quantities', []);
        $unitIds = post('unit_ids', []);

        if (!$menuItemId || empty($ingIds)) {
            setFlash('error', 'Please select a dish and add at least one ingredient.');
        } else {
            try {
                $db->beginTransaction();

                // Compute total cost from ingredients
                $totalCost = 0.00;
                $ingDetails = [];

                foreach ($ingIds as $k => $iId) {
                    $iId = (int)$iId;
                    $qty = (float)($quantities[$k] ?? 0);
                    $uId = (int)($unitIds[$k] ?? 1);

                    if ($iId > 0 && $qty > 0) {
                        $costStmt = $db->prepare("SELECT cost_per_unit FROM ingredients WHERE id = ?");
                        $costStmt->execute([$iId]);
                        $unitCost = (float)$costStmt->fetchColumn();
                        $lineCost = round($qty * $unitCost, 2);
                        $totalCost += $lineCost;

                        $ingDetails[] = [
                            'ingredient_id' => $iId,
                            'quantity' => $qty,
                            'unit_id' => $uId,
                            'cost_snapshot' => $unitCost
                        ];
                    }
                }

                if ($prepLoss > 0) {
                    $totalCost = round($totalCost * (1 + ($prepLoss / 100)), 2);
                }

                if ($recipeId) {
                    $db->prepare("
                        UPDATE recipes SET 
                            menu_item_id = ?, menu_variant_id = ?, yield_quantity = ?, 
                            prep_loss_percent = ?, instructions = ?, total_cost = ?, version = version + 1, updated_at = NOW() 
                        WHERE id = ?
                    ")->execute([$menuItemId, $variantId, $yieldQty, $prepLoss, $instructions, $totalCost, $recipeId]);
                } else {
                    $db->prepare("
                        INSERT INTO recipes (menu_item_id, menu_variant_id, yield_quantity, prep_loss_percent, instructions, total_cost)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$menuItemId, $variantId, $yieldQty, $prepLoss, $instructions, $totalCost]);
                    $recipeId = (int)$db->lastInsertId();
                }

                // Update cost estimate in menu item
                $db->prepare("UPDATE menu_items SET cost_estimate = ? WHERE id = ?")->execute([$totalCost, $menuItemId]);

                // Insert recipe ingredients
                $db->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$recipeId]);
                $insItem = $db->prepare("
                    INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit_id, cost_snapshot)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($ingDetails as $idtl) {
                    $insItem->execute([$recipeId, $idtl['ingredient_id'], $idtl['quantity'], $idtl['unit_id'], $idtl['cost_snapshot']]);
                }

                $db->commit();
                logAudit('save_recipe', 'recipes', $recipeId, null, ['menu_item_id' => $menuItemId, 'cost' => $totalCost]);
                setFlash('success', 'Recipe formulated and costs updated successfully!');
                echo "<script>window.location.href = '" . BASE_URL . "modules/recipes/index.php';</script>";
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', "Database error: " . $e->getMessage());
            }
        }
    }
}

$menuItems = $db->query("SELECT id, name, base_price FROM menu_items WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allIngredients = $db->query("SELECT i.*, u.code as unit_code FROM ingredients i JOIN units u ON i.unit_id = u.id WHERE i.is_active = 1 ORDER BY i.name ASC")->fetchAll();
$allUnits = $db->query("SELECT * FROM units WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><?= $recipeId ? 'Edit Recipe Formulation' : 'Formulate Dish Recipe' ?></h3>
        <p class="text-muted small mb-0">Map exact raw ingredients consumed per plate for accurate COGS and inventory tracking</p>
    </div>
    <a href="<?= BASE_URL ?>modules/recipes/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Recipes
    </a>
</div>

<form method="POST" action="" id="recipeForm">
    <?= csrfField(); ?>
    <div class="row g-4">
        <!-- Recipe Parameters -->
        <div class="col-12 col-lg-8">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Target Menu Item</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Menu Item <span class="text-danger">*</span></label>
                        <select name="menu_item_id" class="form-select" required>
                            <option value="">-- Choose Menu Item --</option>
                            <?php foreach ($menuItems as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= (($recipe['menu_item_id'] ?? $preselectedItemId) == $m['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['name']) ?> (<?= formatCurrency($m['base_price']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Yield (Portions)</label>
                        <input type="number" name="yield_quantity" class="form-control" value="<?= (float)($recipe['yield_quantity'] ?? 1.0) ?>" step="0.1" min="0.1" required>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Prep Loss %</label>
                        <input type="number" name="prep_loss_percent" id="prepLossInput" class="form-control" value="<?= (float)($recipe['prep_loss_percent'] ?? 0.0) ?>" step="0.1" min="0" oninput="calculateRecipeTotals()">
                    </div>
                </div>
            </div>

            <!-- Ingredients Table -->
            <div class="card-rms p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold mb-0">Raw Ingredients & Consumption</h5>
                        <small class="text-muted">Specify quantity of each raw ingredient used</small>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" onclick="addIngredientRow()">
                        <i class="bi bi-plus-lg me-1"></i> Add Ingredient
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="recipeItemsTable">
                        <thead class="table-light small fw-bold text-uppercase">
                            <tr>
                                <th style="width: 45%;">Raw Ingredient</th>
                                <th style="width: 20%;">Qty Used</th>
                                <th style="width: 15%;">Unit</th>
                                <th style="width: 15%;" class="text-end">Line Cost</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="recipeItemsBody">
                            <?php if (empty($existingItems)): ?>
                                <!-- Empty default row -->
                            <?php else: ?>
                                <?php foreach ($existingItems as $ei): ?>
                                    <tr class="recipe-row">
                                        <td>
                                            <select name="ingredient_ids[]" class="form-select form-select-sm ing-select" onchange="onIngSelectChange(this)" required>
                                                <option value="">-- Choose Ingredient --</option>
                                                <?php foreach ($allIngredients as $ing): ?>
                                                    <option value="<?= $ing['id'] ?>" data-cost="<?= $ing['cost_per_unit'] ?>" data-unit="<?= $ing['unit_id'] ?>" <?= $ei['ingredient_id'] == $ing['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ing['name']) ?> (<?= formatCurrency($ing['cost_per_unit']) ?>/<?= $ing['unit_code'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="quantities[]" class="form-control form-control-sm ing-qty" step="0.0001" value="<?= (float)$ei['quantity'] ?>" oninput="calculateRecipeTotals()" required>
                                        </td>
                                        <td>
                                            <select name="unit_ids[]" class="form-select form-select-sm ing-unit">
                                                <?php foreach ($allUnits as $u): ?>
                                                    <option value="<?= $u['id'] ?>" <?= $ei['unit_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['code']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="text-end fw-bold line-cost-display">Rs. 0.00</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('tr').remove(); calculateRecipeTotals();">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Assembly Instructions -->
            <div class="card-rms p-4">
                <h5 class="fw-bold mb-2">Preparation & Plating Notes</h5>
                <textarea name="instructions" class="form-control" rows="3" placeholder="Step by step kitchen instructions..."><?= htmlspecialchars($recipe['instructions'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Right Costing Summary Panel -->
        <div class="col-12 col-lg-4">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Costing Overview</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Ingredient Cost:</span>
                    <span class="fw-bold text-dark fs-5" id="summaryCostDisplay">Rs. 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-3 text-muted small">
                    <span>Including Prep Loss:</span>
                    <span id="summaryLossDisplay">+0%</span>
                </div>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle-fill me-1"></i> Saving this recipe will update the item's cost estimate and automatically consume ingredients during sales.
                </div>
            </div>

            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow">
                <i class="bi bi-check2-circle me-1"></i> Save Recipe Formulation
            </button>
        </div>
    </div>
</form>

<script>
const ingredientsList = <?= json_encode($allIngredients) ?>;
const unitsList = <?= json_encode($allUnits) ?>;

function addIngredientRow() {
    const tbody = document.getElementById('recipeItemsBody');
    const tr = document.createElement('tr');
    tr.className = 'recipe-row';
    tr.innerHTML = `
        <td>
            <select name="ingredient_ids[]" class="form-select form-select-sm ing-select" onchange="onIngSelectChange(this)" required>
                <option value="">-- Choose Ingredient --</option>
                ${ingredientsList.map(i => `<option value="${i.id}" data-cost="${i.cost_per_unit}" data-unit="${i.unit_id}">${i.name} (Rs. ${parseFloat(i.cost_per_unit).toFixed(2)}/${i.unit_code})</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="number" name="quantities[]" class="form-control form-control-sm ing-qty" step="0.0001" placeholder="e.g. 0.15" oninput="calculateRecipeTotals()" required>
        </td>
        <td>
            <select name="unit_ids[]" class="form-select form-select-sm ing-unit">
                ${unitsList.map(u => `<option value="${u.id}">${u.code}</option>`).join('')}
            </select>
        </td>
        <td class="text-end fw-bold line-cost-display">Rs. 0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('tr').remove(); calculateRecipeTotals();">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function onIngSelectChange(select) {
    const opt = select.options[select.selectedIndex];
    const unitId = opt.getAttribute('data-unit');
    const row = select.closest('tr');
    if (unitId) {
        row.querySelector('.ing-unit').value = unitId;
    }
    calculateRecipeTotals();
}

function calculateRecipeTotals() {
    let total = 0.00;
    document.querySelectorAll('.recipe-row').forEach(row => {
        const select = row.querySelector('.ing-select');
        const opt = select.options[select.selectedIndex];
        const cost = parseFloat(opt.getAttribute('data-cost')) || 0;
        const qty = parseFloat(row.querySelector('.ing-qty').value) || 0;
        const lineTotal = cost * qty;
        row.querySelector('.line-cost-display').innerText = RMS.formatMoney(lineTotal);
        total += lineTotal;
    });

    const prepLoss = parseFloat(document.getElementById('prepLossInput').value) || 0;
    if (prepLoss > 0) {
        total = total * (1 + (prepLoss / 100));
        document.getElementById('summaryLossDisplay').innerText = `+${prepLoss}%`;
    } else {
        document.getElementById('summaryLossDisplay').innerText = `None`;
    }

    document.getElementById('summaryCostDisplay').innerText = RMS.formatMoney(total);
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelectorAll('.recipe-row').length === 0) {
        addIngredientRow();
    } else {
        calculateRecipeTotals();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
