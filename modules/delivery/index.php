<?php
// modules/delivery/index.php - Delivery Dispatch & Rider Cash Reconciliation (PRD 13)
$pageTitle = 'Delivery Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('delivery.manage');

$db = getDB();
$branchId = currentBranchId();

$deliveries = $db->query("
    SELECT da.*, o.order_number, o.grand_total, o.payment_status, c.name as customer_name, 
           c.phone as customer_phone, c.address as delivery_address, r.name as rider_name, 
           r.phone as rider_phone, r.vehicle_number, dz.name as zone_name 
    FROM delivery_assignments da 
    JOIN orders o ON da.order_id = o.id 
    LEFT JOIN customers c ON o.customer_id = c.id 
    JOIN riders r ON da.rider_id = r.id 
    LEFT JOIN delivery_zones dz ON da.zone_id = dz.id 
    WHERE o.branch_id = $branchId 
    ORDER BY da.id DESC LIMIT 50
")->fetchAll();

$riders = $db->query("SELECT * FROM riders WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
$zones = $db->query("SELECT * FROM delivery_zones WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-bicycle text-danger me-2"></i> Delivery & Rider Management</h3>
        <p class="text-muted small mb-0">Track live orders out for delivery, monitor driver assignments, and reconcile COD cash</p>
    </div>

    <!-- Actions (No modals) -->
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" onclick="RMS.openSlidePanel('addZonePanel')">
            <i class="bi bi-geo-alt me-1"></i> New Zone
        </button>
        <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" onclick="RMS.openSlidePanel('addRiderPanel')">
            <i class="bi bi-person-plus me-1"></i> Register Rider
        </button>
        <a href="<?= BASE_URL ?>modules/pos/index.php?type=delivery" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-cart-plus me-1"></i> New Delivery Order
        </a>
    </div>
</div>

<!-- Riders Status Grid -->
<div class="row g-3 mb-4">
    <?php foreach ($riders as $rd): ?>
        <div class="col-12 col-sm-6 col-md-4 col-xl-3">
            <div class="card p-3 border shadow-sm d-flex flex-row align-items-center gap-3">
                <div class="p-2 rounded-circle bg-light text-danger fs-3">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($rd['name']) ?></h6>
                    <small class="text-muted d-block"><?= htmlspecialchars($rd['vehicle_number']) ?> (<?= htmlspecialchars($rd['phone']) ?>)</small>
                    <span class="badge <?= $rd['status'] === 'available' ? 'bg-success' : 'bg-warning text-dark' ?> text-capitalize mt-1">
                        <?= $rd['status'] ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Live Delivery Orders Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Order #</th>
                    <th>Customer & Address</th>
                    <th>Zone</th>
                    <th>Assigned Rider</th>
                    <th>Bill Amount</th>
                    <th>Delivery Status</th>
                    <th>COD Handover</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deliveries)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No home delivery orders dispatched today.</td></tr>
                <?php else: ?>
                    <?php foreach ($deliveries as $d): ?>
                        <tr>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($d['order_number']) ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($d['customer_name'] ?? 'Guest') ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($d['customer_phone'] ?? '') ?></small>
                                <small class="text-muted text-truncate d-block" style="max-width: 200px;"><?= htmlspecialchars($d['delivery_address'] ?? 'No Address') ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['zone_name'] ?? 'Default') ?></span></td>
                            <td>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($d['rider_name']) ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($d['rider_phone']) ?></small>
                            </td>
                            <td class="fw-bold fs-6"><?= formatCurrency($d['grand_total']) ?></td>
                            <td>
                                <span class="badge <?php
                                    echo match($d['status']) {
                                        'assigned' => 'bg-info text-dark',
                                        'picked_up' => 'bg-warning text-dark',
                                        'delivered' => 'bg-success',
                                        default => 'bg-danger'
                                    };
                                ?> text-capitalize"><?= str_replace('_', ' ', $d['status']) ?></span>
                            </td>
                            <td>
                                <?php if ($d['cash_handed_over']): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i> Settled (<?= formatCurrency($d['cash_collected']) ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pending Return</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($d['status'] === 'assigned'): ?>
                                        <button class="btn btn-warning text-dark fw-semibold" onclick="updateDeliveryStatus(<?= $d['id'] ?>, 'picked_up')">
                                            Picked Up
                                        </button>
                                    <?php elseif ($d['status'] === 'picked_up'): ?>
                                        <button class="btn btn-success fw-semibold" onclick="updateDeliveryStatus(<?= $d['id'] ?>, 'delivered')">
                                            Mark Delivered
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($d['status'] === 'delivered' && !$d['cash_handed_over']): ?>
                                        <button class="btn btn-outline-success" onclick="openHandoverModal(<?= $d['id'] ?>, '<?= $d['order_number'] ?>', <?= $d['grand_total'] ?>)" title="Reconcile Cash Handover">
                                            <i class="bi bi-cash-coin me-1"></i> Cash Return
                                        </button>
                                    <?php endif; ?>
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

<!-- 1. SLIDE PANEL: REGISTER RIDER -->
<div id="addRiderPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Register Delivery Rider</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addRiderPanel')"></button>
    </div>
    <form id="riderForm" onsubmit="submitRider(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Rider Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Kamran Shafi" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Mobile Phone Number <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control" placeholder="0300-1234567" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Motorcycle / Vehicle Registration</label>
            <input type="text" name="vehicle_number" class="form-control" placeholder="e.g. LEA-24-9081">
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Rider
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: ADD ZONE -->
<div id="addZonePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Create Delivery Zone</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addZonePanel')"></button>
    </div>
    <form id="zoneForm" onsubmit="submitZone(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Zone Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. DHA Phase 6" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Delivery Fee (Rs.) <span class="text-danger">*</span></label>
            <input type="number" name="delivery_fee" class="form-control fw-bold" step="0.01" value="150.00" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Estimated Time (Mins)</label>
            <input type="number" name="estimated_minutes" class="form-control" value="35">
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Zone
        </button>
    </form>
</div>

<!-- 3. SLIDE PANEL: COD CASH HANDOVER RECONCILIATION -->
<div id="handoverPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-success">Rider Cash Reconciliation</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('handoverPanel')"></button>
    </div>
    <form id="handoverForm" onsubmit="submitHandover(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="assignment_id" id="handoverAssId">
        <div class="card p-3 bg-light border mb-3">
            <div class="small text-muted">Order: <b id="handoverOrderNum"></b></div>
            <div class="small text-muted">Bill Amount Expected: <b class="text-danger" id="handoverBillAmt"></b></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Actual Cash Received from Rider <span class="text-danger">*</span></label>
            <input type="number" name="cash_collected" id="handoverCashInput" class="form-control form-control-lg fw-bold" step="0.01" required>
        </div>

        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
            <i class="bi bi-check2-all me-1"></i> Confirm Cash Handover
        </button>
    </form>
</div>

<script>
async function updateDeliveryStatus(id, st) {
    const fd = new FormData();
    fd.append('assignment_id', id);
    fd.append('status', st);
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/delivery.php?action=update_delivery_status', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch(err) {}
}

function openHandoverModal(id, num, amt) {
    document.getElementById('handoverAssId').value = id;
    document.getElementById('handoverOrderNum').innerText = num;
    document.getElementById('handoverBillAmt').innerText = RMS.formatMoney(amt);
    document.getElementById('handoverCashInput').value = amt;
    RMS.openSlidePanel('handoverPanel');
}

async function submitHandover(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('handoverForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/delivery.php?action=handover_cash', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch(err) {}
}

async function submitRider(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('riderForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/delivery.php?action=create_rider', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch(err) {}
}

async function submitZone(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('zoneForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/delivery.php?action=create_zone', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
