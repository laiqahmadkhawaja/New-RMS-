<?php
// modules/menu/categories.php - Menu Categories Management
$pageTitle = 'Menu Categories';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('menu.manage');

$db = getDB();
$branchId = currentBranchId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Token expired.');
    } else {
        $name = cleanInput(post('name'));
        $code = cleanInput(post('code'));
        $stationId = !empty(post('kitchen_station_id')) ? (int)post('kitchen_station_id') : null;
        $icon = cleanInput(post('icon_class', 'bi-egg-fried'));
        $color = cleanInput(post('color_hex', '#e63946'));
        $sortOrder = (int)post('sort_order', 0);

        if (!empty($name)) {
            $catId = (int)post('category_id', 0);
            if ($catId) {
                $db->prepare("
                    UPDATE menu_categories 
                    SET name = ?, code = ?, kitchen_station_id = ?, icon_class = ?, color_hex = ?, sort_order = ? 
                    WHERE id = ?
                ")->execute([$name, $code, $stationId, $icon, $color, $sortOrder, $catId]);
                setFlash('success', "Category '$name' updated.");
            } else {
                $db->prepare("
                    INSERT INTO menu_categories (name, code, kitchen_station_id, icon_class, color_hex, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$name, $code, $stationId, $icon, $color, $sortOrder]);
                setFlash('success', "Category '$name' created.");
            }
            header('Location: ' . BASE_URL . 'modules/menu/categories.php');
            exit;
        }
    }
}

$categories = $db->query("
    SELECT c.*, ks.name as station_name,
           (SELECT COUNT(*) FROM menu_items WHERE category_id = c.id AND is_active = 1) as items_count 
    FROM menu_categories c 
    LEFT JOIN kitchen_stations ks ON c.kitchen_station_id = ks.id 
    WHERE c.is_active = 1 
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

$stations = $db->query("SELECT * FROM kitchen_stations WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-folder-fill text-danger me-2"></i> Menu Categories</h3>
        <p class="text-muted small mb-0">Organize dishes into POS categories with default kitchen stations and icons</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/menu/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Catalog
        </a>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddCategory()">
            <i class="bi bi-plus-circle me-1"></i> Add Category
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Category Cards / List -->
    <div class="col-12 col-lg-8">
        <div class="card-rms overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small fw-bold">
                        <tr>
                            <th>Icon / Category Name</th>
                            <th>Code</th>
                            <th>Kitchen Station</th>
                            <th>Items Count</th>
                            <th>Sort</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 rounded text-white" style="background-color: <?= htmlspecialchars($c['color_hex']) ?>; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi <?= htmlspecialchars($c['icon_class']) ?>"></i>
                                        </div>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></span>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($c['code'] ?? '-') ?></code></td>
                                <td><small class="text-muted"><?= htmlspecialchars($c['station_name'] ?? 'Not set') ?></small></td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= $c['items_count'] ?> dishes</span></td>
                                <td><?= $c['sort_order'] ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" onclick='editCategory(<?= json_encode($c) ?>)'>
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Add/Edit Panel on Right (Zero Modals per PRD) -->
    <div class="col-12 col-lg-4">
        <div class="card-rms p-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2" id="formTitle">Add New Category</h5>
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="category_id" id="catIdInput" value="0">

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catNameInput" class="form-control" placeholder="e.g. Burgers, Pizza..." required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Code / Slug</label>
                    <input type="text" name="code" id="catCodeInput" class="form-control" placeholder="e.g. CAT-BRG">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Kitchen Station</label>
                    <select name="kitchen_station_id" id="catStationInput" class="form-select">
                        <option value="">-- Station Default --</option>
                        <?php foreach ($stations as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Icon Class</label>
                        <input type="text" name="icon_class" id="catIconInput" class="form-control form-control-sm" value="bi-egg-fried">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Theme Color</label>
                        <input type="color" name="color_hex" id="catColorInput" class="form-control form-control-sm form-control-color w-100" value="#e63946">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Sort Display Order</label>
                    <input type="number" name="sort_order" id="catSortInput" class="form-control form-control-sm" value="0">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger flex-grow-1 fw-semibold">
                        <i class="bi bi-check2 me-1"></i> Save Category
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetCatForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(c) {
    document.getElementById('formTitle').innerText = 'Edit Category: ' + c.name;
    document.getElementById('catIdInput').value = c.id;
    document.getElementById('catNameInput').value = c.name;
    document.getElementById('catCodeInput').value = c.code || '';
    document.getElementById('catStationInput').value = c.kitchen_station_id || '';
    document.getElementById('catIconInput').value = c.icon_class || 'bi-egg-fried';
    document.getElementById('catColorInput').value = c.color_hex || '#e63946';
    document.getElementById('catSortInput').value = c.sort_order || 0;
}

function resetCatForm() {
    document.getElementById('formTitle').innerText = 'Add New Category';
    document.getElementById('catIdInput').value = '0';
    document.getElementById('catNameInput').value = '';
    document.getElementById('catCodeInput').value = '';
    document.getElementById('catStationInput').value = '';
    document.getElementById('catIconInput').value = 'bi-egg-fried';
    document.getElementById('catColorInput').value = '#e63946';
    document.getElementById('catSortInput').value = 0;
}

function openAddCategory() {
    resetCatForm();
    document.getElementById('catNameInput').focus();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
