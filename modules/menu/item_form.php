<?php
// modules/menu/item_form.php - Dedicated Menu Item Create/Edit Page (PRD 25.3)
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';

requirePermission('menu.manage');

$db = getDB();
$branchId = currentBranchId();
$itemId = (int)($_GET['id'] ?? 0);
$item = null;
$existingVariants = [];

if ($itemId) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) die("Item not found.");
    $existingVariants = $db->query("SELECT * FROM menu_variants WHERE menu_item_id = $itemId AND is_active = 1")->fetchAll();
}

$pageTitle = $itemId ? 'Edit Menu Item' : 'New Menu Item';
require_once __DIR__ . '/../../includes/header.php';

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Security token expired.');
    } else {
        $name = cleanInput(post('name'));
        $code = cleanInput(post('code'));
        $categoryId = (int)post('category_id');
        $stationId = !empty(post('kitchen_station_id')) ? (int)post('kitchen_station_id') : null;
        $basePrice = (float)post('base_price');
        $costEstimate = (float)post('cost_estimate');
        $taxRate = (float)post('tax_rate');
        $prepTime = (int)post('prep_time_minutes');
        $description = cleanInput(post('description'));
        $isVeg = !empty(post('is_veg')) ? 1 : 0;
        $isSpicy = !empty(post('is_spicy')) ? 1 : 0;
        $isPopular = !empty(post('is_popular')) ? 1 : 0;
        $isAvailable = !empty(post('is_available')) ? 1 : 0;

        // Modifier group assignments (checkboxes → array of group IDs)
        $selectedModGroups = array_map('intval', (array)post('modifier_groups', []));

        if (empty($name) || !$categoryId) {
            setFlash('error', 'Dish name and category are required.');
        } else {
            try {
                $db->beginTransaction();

                if ($itemId) {
                    $up = $db->prepare("
                        UPDATE menu_items SET 
                            category_id = ?, kitchen_station_id = ?, name = ?, code = ?, 
                            description = ?, base_price = ?, cost_estimate = ?, tax_rate = ?, 
                            prep_time_minutes = ?, is_veg = ?, is_spicy = ?, is_popular = ?, 
                            is_available = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $up->execute([
                        $categoryId, $stationId, $name, $code, $description,
                        $basePrice, $costEstimate, $taxRate, $prepTime,
                        $isVeg, $isSpicy, $isPopular, $isAvailable, $itemId
                    ]);
                    logAudit('update_menu_item', 'menu_items', $itemId, null, ['name' => $name, 'price' => $basePrice]);
                } else {
                    $ins = $db->prepare("
                        INSERT INTO menu_items (
                            category_id, kitchen_station_id, name, code, description, 
                            base_price, cost_estimate, tax_rate, prep_time_minutes, 
                            is_veg, is_spicy, is_popular, is_available
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([
                        $categoryId, $stationId, $name, $code, $description,
                        $basePrice, $costEstimate, $taxRate, $prepTime,
                        $isVeg, $isSpicy, $isPopular, $isAvailable
                    ]);
                    $itemId = (int)$db->lastInsertId();
                    logAudit('create_menu_item', 'menu_items', $itemId, null, ['name' => $name, 'price' => $basePrice]);
                }

                // Handle Variants
                $variantNames = post('variant_names', []);
                $variantPrices = post('variant_prices', []);
                $variantSkus = post('variant_skus', []);

                $db->prepare("DELETE FROM menu_variants WHERE menu_item_id = ?")->execute([$itemId]);
                $varStmt = $db->prepare("
                    INSERT INTO menu_variants (menu_item_id, name, sku, final_price, is_default, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");

                foreach ($variantNames as $k => $vName) {
                    $vName = trim($vName);
                    if (!empty($vName)) {
                        $vPrice = (float)($variantPrices[$k] ?? $basePrice);
                        $vSku = trim($variantSkus[$k] ?? '');
                        $isDef = ($k === 0) ? 1 : 0;
                        $varStmt->execute([$itemId, $vName, $vSku, $vPrice, $isDef]);
                    }
                }

                // Handle Item-Level Modifier Group Assignments
                // Always replace: delete current, insert selected ones
                $db->prepare("DELETE FROM menu_item_modifier_groups WHERE menu_item_id = ?")->execute([$itemId]);
                if (!empty($selectedModGroups)) {
                    $mgStmt = $db->prepare("INSERT IGNORE INTO menu_item_modifier_groups (menu_item_id, modifier_group_id, sort_order) VALUES (?, ?, ?)");
                    foreach ($selectedModGroups as $sort => $gId) {
                        $mgStmt->execute([$itemId, $gId, $sort]);
                    }
                }

                $db->commit();
                setFlash('success', "Menu Item '{$name}' saved successfully!");
                echo "<script>window.location.href = '" . BASE_URL . "modules/menu/index.php';</script>";
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', "Database error: " . $e->getMessage());
            }
        }
    }
}

$categories = $db->query("SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$stations = $db->query("SELECT * FROM kitchen_stations WHERE branch_id = $branchId AND is_active = 1")->fetchAll();

// Load all modifier groups for the assignment panel
$allModGroups = $db->query("SELECT * FROM modifier_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

// Get existing item-level modifier group assignments (for edit mode)
$itemModGroupIds = [];
if ($itemId) {
    $rows = $db->prepare("SELECT modifier_group_id FROM menu_item_modifier_groups WHERE menu_item_id = ? ORDER BY sort_order ASC");
    $rows->execute([$itemId]);
    $itemModGroupIds = array_column($rows->fetchAll(), 'modifier_group_id');
}

// Get category-level defaults (for showing hints)
$catModGroupIdsByCategory = [];
$catRows = $db->query("SELECT category_id, modifier_group_id FROM menu_category_modifier_groups ORDER BY sort_order ASC")->fetchAll();
foreach ($catRows as $cr) {
    $catModGroupIdsByCategory[$cr['category_id']][] = (int)$cr['modifier_group_id'];
}
?>

<!-- Header Breadcrumb -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><?= $itemId ? 'Edit Menu Item' : 'Create New Menu Item' ?></h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>modules/menu/index.php">Menu Catalog</a></li>
                <li class="breadcrumb-item active"><?= $itemId ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>modules/menu/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Catalog
    </a>
</div>

<form method="POST" action="">
    <?= csrfField(); ?>
    <div class="row g-4">
        <!-- Left: Item Core Details -->
        <div class="col-12 col-lg-8">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Dish General Information</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Dish / Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Crispy Zinger Crunch" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Internal Item Code</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. ITM-BRG-01" value="<?= htmlspecialchars($item['code'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Menu Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (!empty($item) && $item['category_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Assigned Kitchen Station</label>
                        <select name="kitchen_station_id" class="form-select">
                            <option value="">-- Station Default --</option>
                            <?php foreach ($stations as $st): ?>
                                <option value="<?= $st['id'] ?>" <?= (!empty($item) && $item['kitchen_station_id'] == $st['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($st['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Description / Customer Menu Notes</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe delicious ingredients, preparation style, or allergens..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Variants & Portions (PRD 8.3) -->
            <div class="card-rms p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold mb-0">Sizes & Variants</h5>
                        <small class="text-muted">e.g. Regular, Medium, Large or Single, Double</small>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" onclick="addVariantRow()">
                        <i class="bi bi-plus-lg me-1"></i> Add Variant
                    </button>
                </div>

                <div id="variantsContainer">
                    <?php if (empty($existingVariants)): ?>
                        <div class="text-muted small fst-italic py-2" id="noVariantsMsg">No variants configured. Base price applies. Click "Add Variant" to configure sizes.</div>
                    <?php else: ?>
                        <?php foreach ($existingVariants as $ev): ?>
                            <div class="row g-2 mb-2 align-items-center variant-row">
                                <div class="col-5">
                                    <input type="text" name="variant_names[]" class="form-control form-control-sm" placeholder="Variant name (e.g. Large)" value="<?= htmlspecialchars($ev['name']) ?>">
                                </div>
                                <div class="col-4">
                                    <input type="number" name="variant_prices[]" class="form-control form-control-sm" placeholder="Final Price" step="0.01" value="<?= (float)$ev['final_price'] ?>">
                                </div>
                                <div class="col-2">
                                    <input type="text" name="variant_skus[]" class="form-control form-control-sm" placeholder="SKU" value="<?= htmlspecialchars($ev['sku'] ?? '') ?>">
                                </div>
                                <div class="col-1 text-center">
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('.variant-row').remove()">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add-ons & Modifier Groups (Item-Level Assignment) -->
            <div class="card-rms p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="bi bi-plus-slash-minus text-danger me-2"></i>Add-ons & Modifier Groups</h5>
                        <small class="text-muted">Choose which add-on groups appear when a cashier selects this item at POS. If none selected, category defaults apply automatically.</small>
                    </div>
                    <a href="<?= BASE_URL ?>modules/menu/modifiers.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-gear me-1"></i> Manage Groups
                    </a>
                </div>

                <?php if (empty($allModGroups)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No modifier groups exist yet. <a href="<?= BASE_URL ?>modules/menu/modifiers.php" target="_blank">Create add-on groups first</a>, then come back to assign them.
                    </div>
                <?php else: ?>
                    <!-- Current category defaults hint (live via JS) -->
                    <div id="categoryDefaultsHint" class="alert alert-info small py-2 mb-3 d-none">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Category defaults:</strong> <span id="categoryDefaultsList"></span>
                        <br><span class="text-muted">These apply automatically unless you make a custom selection below.</span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($allModGroups as $mg): ?>
                            <?php
                                $isItemChecked = in_array($mg['id'], $itemModGroupIds);
                            ?>
                            <div class="col-12 col-md-6">
                                <label class="d-flex align-items-start gap-2 p-3 border rounded cursor-pointer mod-group-card <?= $isItemChecked ? 'border-danger bg-danger-subtle' : 'border-light bg-light' ?>"
                                       style="cursor:pointer;" for="modGroup_<?= $mg['id'] ?>">
                                    <input class="form-check-input flex-shrink-0 mt-1 mod-group-checkbox"
                                           type="checkbox"
                                           name="modifier_groups[]"
                                           value="<?= $mg['id'] ?>"
                                           id="modGroup_<?= $mg['id'] ?>"
                                           <?= $isItemChecked ? 'checked' : '' ?>
                                           onchange="toggleModCard(this)">
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($mg['name']) ?></div>
                                        <?php if (!empty($mg['description'])): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($mg['description']) ?></small>
                                        <?php endif; ?>
                                        <div class="mt-1">
                                            <?= $mg['is_required'] ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle small">Required</span>' : '<span class="badge bg-secondary-subtle text-secondary small">Optional</span>' ?>
                                            <span class="badge bg-light text-muted border small ms-1">Max: <?= $mg['max_selection'] ?></span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-lightbulb me-1 text-warning"></i>
                            <strong>Tip:</strong> Checked groups apply specifically to this item. If none are checked, the item inherits its category defaults.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Pricing & Flags -->
        <div class="col-12 col-lg-4">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Pricing & Costing</h5>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Base Selling Price (Rs.) <span class="text-danger">*</span></label>
                    <input type="number" name="base_price" class="form-control form-control-lg fw-bold text-danger" step="0.01" value="<?= (float)($item['base_price'] ?? 0) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Estimated Ingredient Cost (Rs.)</label>
                    <input type="number" name="cost_estimate" class="form-control" step="0.01" value="<?= (float)($item['cost_estimate'] ?? 0) ?>">
                    <small class="text-muted">Calculated automatically if recipe is configured.</small>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Sales Tax (%)</label>
                        <input type="number" name="tax_rate" class="form-control" step="0.1" value="<?= (float)($item['tax_rate'] ?? 5.0) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Target Prep (Min)</label>
                        <input type="number" name="prep_time_minutes" class="form-control" value="<?= (int)($item['prep_time_minutes'] ?? 15) ?>">
                    </div>
                </div>
            </div>

            <!-- Tags & Status -->
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Item Attributes</h5>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_available" id="isAvailable" <?= (!isset($item) || $item['is_available']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isAvailable">Available for Sale</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_popular" id="isPopular" <?= (!empty($item['is_popular'])) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isPopular">Featured / Popular Item</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_veg" id="isVeg" <?= (!empty($item['is_veg'])) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isVeg">Vegetarian Dish</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_spicy" id="isSpicy" <?= (!empty($item['is_spicy'])) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isSpicy">Spicy Dish</label>
                </div>
            </div>

            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow">
                <i class="bi bi-check2-circle me-1"></i> Save Menu Item
            </button>
        </div>
    </div>
</form>

<script>
function addVariantRow() {
    const noMsg = document.getElementById('noVariantsMsg');
    if (noMsg) noMsg.remove();

    const container = document.getElementById('variantsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center variant-row';
    row.innerHTML = `
        <div class="col-5">
            <input type="text" name="variant_names[]" class="form-control form-control-sm" placeholder="Variant name (e.g. Large)" required>
        </div>
        <div class="col-4">
            <input type="number" name="variant_prices[]" class="form-control form-control-sm" placeholder="Final Price" step="0.01" required>
        </div>
        <div class="col-2">
            <input type="text" name="variant_skus[]" class="form-control form-control-sm" placeholder="SKU">
        </div>
        <div class="col-1 text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('.variant-row').remove()">
                <i class="bi bi-trash3"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

// Category-level modifier group defaults (for the live hint)
const categoryModDefaults = <?= json_encode($catModGroupIdsByCategory) ?>;
const modGroupNames = <?= json_encode(array_column($allModGroups, 'name', 'id')) ?>;

function toggleModCard(checkbox) {
    const card = checkbox.closest('.mod-group-card');
    if (checkbox.checked) {
        card.classList.add('border-danger', 'bg-danger-subtle');
        card.classList.remove('border-light', 'bg-light');
    } else {
        card.classList.remove('border-danger', 'bg-danger-subtle');
        card.classList.add('border-light', 'bg-light');
    }
}

// Show category defaults hint when category changes
const catSelect = document.querySelector('select[name="category_id"]');
function updateCategoryHint() {
    const catId = parseInt(catSelect ? catSelect.value : 0);
    const hint = document.getElementById('categoryDefaultsHint');
    const list = document.getElementById('categoryDefaultsList');
    if (!hint || !list) return;
    if (!catId || !categoryModDefaults[catId] || categoryModDefaults[catId].length === 0) {
        hint.classList.add('d-none');
        return;
    }
    const names = categoryModDefaults[catId].map(id => modGroupNames[id] || 'Group #' + id);
    list.textContent = names.join(', ');
    hint.classList.remove('d-none');
}
if (catSelect) {
    catSelect.addEventListener('change', updateCategoryHint);
    updateCategoryHint(); // Run on page load for edit mode
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
