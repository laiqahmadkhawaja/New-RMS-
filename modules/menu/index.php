<?php
// modules/menu/index.php - Menu Items & Categories Catalog
$pageTitle = 'Menu Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('menu.manage');

$db = getDB();

// Handle quick availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_item_id'])) {
    $itemId = (int)$_POST['toggle_item_id'];
    $newStatus = (int)$_POST['is_available'];
    $db->prepare("UPDATE menu_items SET is_available = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $itemId]);
    setFlash('success', 'Item availability updated successfully.');
    header('Location: ' . BASE_URL . 'modules/menu/index.php');
    exit;
}

$categories = $db->query("SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();
$selectedCat = get('category_id', '');

$sql = "
    SELECT m.*, c.name as category_name, ks.name as station_name,
           (SELECT COUNT(*) FROM menu_variants WHERE menu_item_id = m.id AND is_active = 1) as variants_count,
           (SELECT COUNT(*) FROM recipes WHERE menu_item_id = m.id AND is_active = 1) as has_recipe
    FROM menu_items m 
    JOIN menu_categories c ON m.category_id = c.id 
    LEFT JOIN kitchen_stations ks ON m.kitchen_station_id = ks.id 
    WHERE m.is_active = 1 
";
if (!empty($selectedCat)) {
    $sql .= " AND m.category_id = " . (int)$selectedCat;
}
$sql .= " ORDER BY c.sort_order, m.is_popular DESC, m.name ASC";
$items = $db->query($sql)->fetchAll();
?>

<!-- Menu Management Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-menu-button-wide text-danger me-2"></i> Menu & Catalog Management</h3>
        <p class="text-muted small mb-0">Configure food items, sizes, variants, pricing, and category stations</p>
    </div>

    <!-- Actions (Dedicated form pages per PRD 25.3) -->
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/menu/categories.php" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-folder me-1"></i> Manage Categories
        </a>
        <a href="<?= BASE_URL ?>modules/menu/modifiers.php" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-plus-slash-minus me-1"></i> Add-ons & Modifiers
        </a>
        <a href="<?= BASE_URL ?>modules/menu/item_form.php" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-plus-circle me-1"></i> Add Menu Item
        </a>
    </div>
</div>

<!-- Category Tabs Filter -->
<div class="d-flex gap-2 overflow-x-auto pb-2 mb-4">
    <a href="?category_id=" class="btn btn-sm <?= empty($selectedCat) ? 'btn-danger' : 'btn-light border' ?> fw-semibold text-nowrap">
        All Categories
    </a>
    <?php foreach ($categories as $cat): ?>
        <a href="?category_id=<?= $cat['id'] ?>" class="btn btn-sm <?= $selectedCat == $cat['id'] ? 'btn-danger' : 'btn-light border' ?> fw-semibold text-nowrap">
            <i class="bi <?= $cat['icon_class'] ?> me-1"></i> <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Menu Items Grid / Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Dish / Item</th>
                    <th>Category</th>
                    <th>Kitchen Station</th>
                    <th>Selling Price</th>
                    <th>Est. Cost</th>
                    <th>Gross Margin</th>
                    <th>Recipe</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-basket fs-2"></i>
                            <div class="mt-2">No menu items found in this category.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $it): 
                        $margin = (float)$it['base_price'] - (float)$it['cost_estimate'];
                        $marginPercent = (float)$it['base_price'] > 0 ? round(($margin / (float)$it['base_price']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-light p-2 rounded border text-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-cup-hot text-danger fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($it['name']) ?></span>
                                        <?= $it['variants_count'] > 0 ? '<span class="badge bg-secondary-subtle text-secondary ms-1">' . $it['variants_count'] . ' sizes</span>' : '' ?>
                                        <div class="small text-muted"><?= htmlspecialchars($it['code'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($it['category_name']) ?></span></td>
                            <td><small class="text-muted"><i class="bi bi-tv me-1"></i><?= htmlspecialchars($it['station_name'] ?? 'Default') ?></small></td>
                            <td class="fw-bold text-dark"><?= formatCurrency($it['base_price']) ?></td>
                            <td class="text-muted small"><?= formatCurrency($it['cost_estimate']) ?></td>
                            <td>
                                <span class="badge bg-success-subtle text-success"><?= $marginPercent ?>% (<?= formatCurrency($margin) ?>)</span>
                            </td>
                            <td>
                                <?php if ($it['has_recipe']): ?>
                                    <span class="badge bg-primary-subtle text-primary"><i class="bi bi-check2"></i> Linked</span>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>modules/recipes/recipe_form.php?menu_item_id=<?= $it['id'] ?>" class="badge bg-warning-subtle text-warning text-decoration-none">
                                        <i class="bi bi-plus"></i> Add Recipe
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="toggle_item_id" value="<?= $it['id'] ?>">
                                    <input type="hidden" name="is_available" value="<?= $it['is_available'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= $it['is_available'] ? 'btn-outline-success' : 'btn-outline-danger' ?> py-0 px-2 small">
                                        <?= $it['is_available'] ? 'Available' : 'Out of Stock' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>modules/menu/item_form.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit Item">
                                    <i class="bi bi-pencil-square"></i> Edit
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
