<?php
// modules/menu/modifiers.php - Modifier Groups & Add-ons Manager
$pageTitle = 'Modifiers & Add-ons';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('menu.manage');

$db = getDB();

// Handle new group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_group'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $name = cleanInput(post('group_name'));
        $min = (int)post('min_selection', 0);
        $max = (int)post('max_selection', 1);
        $isRequired = !empty(post('is_required')) ? 1 : 0;
        $categoryIds = array_map('intval', (array)post('assigned_categories', []));

        if (!empty($name)) {
            $db->prepare("INSERT INTO modifier_groups (name, min_selection, max_selection, is_required) VALUES (?, ?, ?, ?)")->execute([$name, $min, $max, $isRequired]);
            $newGroupId = (int)$db->lastInsertId();

            if (!empty($categoryIds)) {
                $cmStmt = $db->prepare("INSERT IGNORE INTO menu_category_modifier_groups (category_id, modifier_group_id, sort_order) VALUES (?, ?, ?)");
                foreach ($categoryIds as $sort => $cId) {
                    $cmStmt->execute([$cId, $newGroupId, $sort + 1]);
                }
            }

            setFlash('success', "Modifier group '$name' created.");
            header('Location: ' . BASE_URL . 'modules/menu/modifiers.php');
            exit;
        }
    }
}

// Handle updating category associations for an existing group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_group_categories'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $groupId = (int)post('group_id');
        $categoryIds = array_map('intval', (array)post('assigned_categories', []));

        if ($groupId) {
            $db->prepare("DELETE FROM menu_category_modifier_groups WHERE modifier_group_id = ?")->execute([$groupId]);
            if (!empty($categoryIds)) {
                $cmStmt = $db->prepare("INSERT IGNORE INTO menu_category_modifier_groups (category_id, modifier_group_id, sort_order) VALUES (?, ?, ?)");
                foreach ($categoryIds as $sort => $cId) {
                    $cmStmt->execute([$cId, $groupId, $sort + 1]);
                }
            }
            setFlash('success', "Category associations updated.");
            header('Location: ' . BASE_URL . 'modules/menu/modifiers.php');
            exit;
        }
    }
}

// Handle add-on item creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_modifier'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $groupId = (int)post('group_id');
        $modName = cleanInput(post('modifier_name'));
        $price = (float)post('price', 0);
        $cost = (float)post('cost_estimate', 0);
        if ($groupId && !empty($modName)) {
            $db->prepare("INSERT INTO modifiers (group_id, name, price, cost_estimate) VALUES (?, ?, ?, ?)")->execute([$groupId, $modName, $price, $cost]);
            setFlash('success', "Add-on option '$modName' added.");
            header('Location: ' . BASE_URL . 'modules/menu/modifiers.php');
            exit;
        }
    }
}

$groups = $db->query("SELECT * FROM modifier_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$allModifiers = $db->query("SELECT * FROM modifiers WHERE is_active = 1 ORDER BY group_id, price ASC")->fetchAll();
$modsByGroup = [];
foreach ($allModifiers as $m) {
    $modsByGroup[$m['group_id']][] = $m;
}

$allCategories = $db->query("SELECT id, name FROM menu_categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$catModRows = $db->query("
    SELECT cmg.modifier_group_id, c.name, c.id as category_id 
    FROM menu_category_modifier_groups cmg 
    JOIN menu_categories c ON cmg.category_id = c.id 
    ORDER BY cmg.sort_order ASC
")->fetchAll();
$catsByGroup = [];
foreach ($catModRows as $row) {
    $catsByGroup[$row['modifier_group_id']][] = $row;
}
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-plus-slash-minus text-danger me-2"></i> Modifiers & Add-on Groups</h3>
        <p class="text-muted small mb-0">Create customizable extras, toppings, dips, and compulsory options</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/menu/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Catalog
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Groups & Items List -->
    <div class="col-12 col-lg-8">
        <?php if (empty($groups)): ?>
            <div class="card-rms p-5 text-center text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <div class="mt-2 fw-semibold">No Modifier Groups Created</div>
                <small>Use the form on the right to create your first add-on group.</small>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($groups as $g): 
                    $options = $modsByGroup[$g['id']] ?? [];
                ?>
                    <div class="col-12">
                        <div class="card-rms p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($g['name']) ?></h5>
                                    <small class="text-muted">
                                        <?= $g['is_required'] ? '<span class="text-danger fw-bold">Required</span> | ' : 'Optional | ' ?>
                                        Min: <?= $g['min_selection'] ?>, Max: <?= $g['max_selection'] ?>
                                    </small>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary"><?= count($options) ?> Options</span>
                            </div>

                            <!-- Assigned Categories Header & Quick Editor -->
                            <?php 
                                $assignedCats = $catsByGroup[$g['id']] ?? [];
                                $assignedCatIds = array_column($assignedCats, 'category_id');
                            ?>
                            <div class="mb-3 d-flex align-items-center flex-wrap gap-1">
                                <small class="text-muted fw-semibold me-1">Default for:</small>
                                <?php if (empty($assignedCats)): ?>
                                    <span class="badge bg-light text-muted border">None (assigned per item)</span>
                                <?php else: ?>
                                    <?php foreach ($assignedCats as $cg): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-tag me-1"></i><?= htmlspecialchars($cg['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <button class="btn btn-link btn-sm text-decoration-none py-0 px-1 ms-auto text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editCats_<?= $g['id'] ?>">
                                    <i class="bi bi-pencil-square me-1"></i>Edit Categories
                                </button>
                            </div>

                            <!-- Inline category editor drawer/collapse -->
                            <div class="collapse mb-3" id="editCats_<?= $g['id'] ?>">
                                <div class="card p-3 bg-light border">
                                    <form method="POST" action="">
                                        <?= csrfField(); ?>
                                        <input type="hidden" name="action_update_group_categories" value="1">
                                        <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                        <label class="form-label small fw-bold text-muted mb-2">Check categories that automatically inherit this group:</label>
                                        <div class="row g-2 mb-2">
                                            <?php foreach ($allCategories as $cat): ?>
                                                <div class="col-6 col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="assigned_categories[]" value="<?= $cat['id'] ?>" id="cat_<?= $g['id'] ?>_<?= $cat['id'] ?>" <?= in_array($cat['id'], $assignedCatIds) ? 'checked' : '' ?>>
                                                        <label class="form-check-label small" for="cat_<?= $g['id'] ?>_<?= $cat['id'] ?>">
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-dark fw-semibold">
                                            <i class="bi bi-check2 me-1"></i>Save Category Links
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Options List -->
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Option / Topping</th>
                                            <th class="text-end">Customer Price</th>
                                            <th class="text-end">Cost Estimate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($options)): ?>
                                            <tr><td colspan="3" class="text-center text-muted small py-2">No options added to this group yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($options as $opt): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?= htmlspecialchars($opt['name']) ?></td>
                                                    <td class="text-end text-danger fw-bold"><?= formatCurrency($opt['price']) ?></td>
                                                    <td class="text-end text-muted"><?= formatCurrency($opt['cost_estimate']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Quick inline add option -->
                            <form method="POST" action="" class="row g-2 align-items-center">
                                <?= csrfField(); ?>
                                <input type="hidden" name="action_create_modifier" value="1">
                                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                <div class="col-6 col-md-5">
                                    <input type="text" name="modifier_name" class="form-control form-control-sm" placeholder="New option name (e.g. Extra Cheese)" required>
                                </div>
                                <div class="col-3 col-md-3">
                                    <input type="number" name="price" class="form-control form-control-sm" placeholder="Price (Rs.)" step="0.01" required>
                                </div>
                                <div class="col-3 col-md-2">
                                    <input type="number" name="cost_estimate" class="form-control form-control-sm" placeholder="Cost" step="0.01">
                                </div>
                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100 fw-semibold">
                                        <i class="bi bi-plus-lg"></i> Add
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Create Group Form (Dedicated inline panel, NO modals) -->
    <div class="col-12 col-lg-4">
        <div class="card-rms p-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">New Modifier Group</h5>
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_create_group" value="1">

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Group Title <span class="text-danger">*</span></label>
                    <input type="text" name="group_name" class="form-control" placeholder="e.g. Crust Choice, Choice of Dip..." required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Min Choices</label>
                        <input type="number" name="min_selection" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Max Choices</label>
                        <input type="number" name="max_selection" class="form-control" value="1" min="1">
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_required" id="isReqSwitch">
                    <label class="form-check-label fw-semibold" for="isReqSwitch">Selection is Compulsory</label>
                </div>

                <div class="mb-3 border-top pt-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-2">Assign as Default for Categories</label>
                    <div class="bg-light p-2 rounded border" style="max-height: 160px; overflow-y: auto;">
                        <?php foreach ($allCategories as $cat): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="assigned_categories[]" value="<?= $cat['id'] ?>" id="new_cat_<?= $cat['id'] ?>">
                                <label class="form-check-label small" for="new_cat_<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted d-block mt-1">Dishes in these categories will offer this add-on group.</small>
                </div>

                <button type="submit" class="btn btn-danger w-100 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Create Group
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
