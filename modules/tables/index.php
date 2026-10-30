<?php
// modules/tables/index.php - Visual Table Management & Floor Plan
$pageTitle = 'Table Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('tables.manage');

$db = getDB();
$branchId = currentBranchId();
$areas = $db->query("SELECT * FROM dining_areas WHERE branch_id = $branchId AND is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$tables = $db->query("
    SELECT t.*, da.name as area_name, o.order_number, o.grand_total as order_total, o.created_at as order_time 
    FROM restaurant_tables t 
    JOIN dining_areas da ON t.dining_area_id = da.id 
    LEFT JOIN orders o ON t.current_order_id = o.id 
    WHERE t.branch_id = $branchId AND t.is_active = 1 
    ORDER BY t.dining_area_id, t.sort_order ASC
")->fetchAll();
?>

<!-- Table Management Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-grid-fill text-danger me-2"></i> Table Layout & Floor Management</h3>
        <p class="text-muted small mb-0">Monitor table occupancy, transfer active orders, and configure dining zones</p>
    </div>

    <!-- Actions (No modals - uses dedicated slide-in panels) -->
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" onclick="RMS.openSlidePanel('transferTablePanel')">
            <i class="bi bi-arrow-left-right me-1"></i> Transfer Table
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" onclick="RMS.openSlidePanel('addAreaPanel')">
            <i class="bi bi-plus-lg me-1"></i> New Area
        </button>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="RMS.openSlidePanel('addTablePanel')">
            <i class="bi bi-plus-circle me-1"></i> New Table
        </button>
    </div>
</div>

<!-- Area Filter Tabs -->
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <button class="nav-link active bg-danger text-white py-1 px-3 fw-semibold area-filter-btn" data-area="all" onclick="filterArea('all', this)">
            All Dining Areas
        </button>
    </li>
    <?php foreach ($areas as $a): ?>
        <li class="nav-item">
            <button class="nav-link btn-light border py-1 px-3 fw-semibold area-filter-btn" data-area="<?= $a['id'] ?>" onclick="filterArea(<?= $a['id'] ?>, this)">
                <?= htmlspecialchars($a['name']) ?>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Interactive Tables Grid -->
<div class="row g-3" id="tablesContainer">
    <?php foreach ($tables as $tb): ?>
        <div class="col-12 col-sm-6 col-md-4 col-xl-3 table-item-col" data-area="<?= $tb['dining_area_id'] ?>">
            <div class="table-card table-<?= $tb['status'] ?> shadow-sm h-100 position-relative">
                <!-- Status Badge -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary-subtle text-secondary small"><?= htmlspecialchars($tb['area_name']) ?></span>
                    <span class="badge rounded-pill <?php
                        echo match($tb['status']) {
                            'available' => 'bg-success',
                            'occupied' => 'bg-danger',
                            'reserved' => 'bg-info text-dark',
                            'cleaning' => 'bg-warning text-dark',
                            default => 'bg-secondary'
                        };
                    ?>"><?= ucfirst($tb['status']) ?></span>
                </div>

                <!-- Table Center Info -->
                <div class="my-2">
                    <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($tb['table_number']) ?></h3>
                    <small class="text-muted"><i class="bi bi-people-fill me-1"></i><?= $tb['capacity'] ?> Seats (<?= ucfirst($tb['shape']) ?>)</small>
                </div>

                <!-- Active Order Info if Occupied -->
                <?php if ($tb['order_total']): ?>
                    <div class="bg-white p-2 rounded border mb-2">
                        <div class="fw-bold text-danger"><?= htmlspecialchars($tb['order_number']) ?></div>
                        <div class="small fw-semibold"><?= formatCurrency($tb['order_total']) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Quick Action Buttons -->
                <div class="d-flex gap-1 justify-content-center mt-3 pt-2 border-top">
                    <?php if ($tb['status'] === 'available'): ?>
                        <a href="<?= BASE_URL ?>modules/pos/index.php?table_id=<?= $tb['id'] ?>" class="btn btn-sm btn-danger w-100 fw-semibold">
                            <i class="bi bi-cart-plus me-1"></i> Open Order
                        </a>
                    <?php elseif ($tb['status'] === 'occupied'): ?>
                        <a href="<?= BASE_URL ?>modules/pos/index.php" class="btn btn-sm btn-dark w-100 fw-semibold">
                            <i class="bi bi-eye me-1"></i> View Tab
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="markTableCleaning(<?= $tb['id'] ?>)" title="Mark for Cleaning">
                            <i class="bi bi-brush"></i>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-success w-100 fw-semibold" onclick="markTableAvailable(<?= $tb['id'] ?>)">
                            <i class="bi bi-check-circle me-1"></i> Mark Available
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ================= SLIDE PANELS (NO MODALS) ================= -->

<!-- 1. SLIDE PANEL: TRANSFER ORDER BETWEEN TABLES -->
<div id="transferTablePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Transfer Active Table Order</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('transferTablePanel')"></button>
    </div>
    <form id="transferTableForm" onsubmit="submitTransferTable(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label fw-semibold small text-muted text-uppercase">Source Table (Occupied)</label>
            <select name="from_table_id" class="form-select" required>
                <option value="">-- Choose Occupied Table --</option>
                <?php foreach ($tables as $t): ?>
                    <?php if ($t['status'] === 'occupied'): ?>
                        <option value="<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['table_number']) ?> (<?= htmlspecialchars($t['area_name']) ?> - Order #<?= $t['order_number'] ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small text-muted text-uppercase">Destination Table (Available)</label>
            <select name="to_table_id" class="form-select" required>
                <option value="">-- Choose Target Table --</option>
                <?php foreach ($tables as $t): ?>
                    <?php if ($t['status'] === 'available'): ?>
                        <option value="<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['table_number']) ?> (<?= htmlspecialchars($t['area_name']) ?> - <?= $t['capacity'] ?> Seats)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle-fill me-1"></i> All items, kitchen tickets, and payment history will safely transfer to the new table.
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-arrow-left-right me-1"></i> Complete Table Transfer
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: ADD NEW TABLE -->
<div id="addTablePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Create Dining Table</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addTablePanel')"></button>
    </div>
    <form id="createTableForm" onsubmit="submitCreateTable(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Dining Area / Section</label>
            <select name="dining_area_id" class="form-select" required>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Table Number / Identifier</label>
            <input type="text" name="table_number" class="form-control" placeholder="e.g. T-15 or VIP-2" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Seating Capacity</label>
                <input type="number" name="capacity" class="form-control" value="4" min="1" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Table Shape</label>
                <select name="shape" class="form-select">
                    <option value="square">Square</option>
                    <option value="rectangle">Rectangle</option>
                    <option value="round">Round</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Service Charge (%)</label>
            <input type="number" name="service_charge_percent" class="form-control" value="2.50" step="0.1" min="0">
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-plus-circle me-1"></i> Save Table
        </button>
    </form>
</div>

<!-- 3. SLIDE PANEL: ADD NEW AREA -->
<div id="addAreaPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Create Dining Area</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addAreaPanel')"></button>
    </div>
    <form id="createAreaForm" onsubmit="submitCreateArea(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Area Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Mezzanine Floor, Terrace..." required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Brief note about this dining zone..."></textarea>
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-plus-lg me-1"></i> Save Dining Area
        </button>
    </form>
</div>

<script>
function filterArea(areaId, btn) {
    document.querySelectorAll('.area-filter-btn').forEach(b => {
        b.classList.remove('bg-danger', 'text-white', 'active');
        b.classList.add('btn-light');
    });
    btn.classList.add('bg-danger', 'text-white', 'active');
    btn.classList.remove('btn-light');

    document.querySelectorAll('.table-item-col').forEach(col => {
        if (areaId === 'all' || col.getAttribute('data-area') == areaId) {
            col.style.display = 'block';
        } else {
            col.style.display = 'none';
        }
    });
}

async function markTableAvailable(id) {
    const fd = new FormData();
    fd.append('table_id', id);
    fd.append('status', 'available');
    await RMS.api('<?= BASE_URL ?>ajax/tables.php?action=set_status', { method: 'POST', body: fd });
    location.reload();
}

async function markTableCleaning(id) {
    const fd = new FormData();
    fd.append('table_id', id);
    fd.append('status', 'cleaning');
    await RMS.api('<?= BASE_URL ?>ajax/tables.php?action=set_status', { method: 'POST', body: fd });
    location.reload();
}

async function submitTransferTable(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('transferTableForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/tables.php?action=transfer_table', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } catch(err) {}
}

async function submitCreateTable(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('createTableForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/tables.php?action=create_table', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } catch(err) {}
}

async function submitCreateArea(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('createAreaForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/tables.php?action=create_area', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
