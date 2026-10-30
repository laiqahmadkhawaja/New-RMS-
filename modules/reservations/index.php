<?php
// modules/reservations/index.php - Table Bookings & Waiting List Queue (PRD 15)
$pageTitle = 'Reservations & Waiting List';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('reservations.manage');

$db = getDB();
$branchId = currentBranchId();

$reservations = $db->query("
    SELECT r.*, t.table_number, da.name as area_name 
    FROM reservations r 
    LEFT JOIN restaurant_tables t ON r.table_id = t.id 
    LEFT JOIN dining_areas da ON r.dining_area_id = da.id 
    WHERE r.branch_id = $branchId 
    ORDER BY r.reservation_date DESC, r.arrival_time DESC LIMIT 50
")->fetchAll();

$waiting = $db->query("
    SELECT * FROM waiting_list 
    WHERE branch_id = $branchId AND status = 'waiting' 
    ORDER BY id ASC
")->fetchAll();

$tables = $db->query("SELECT t.*, da.name as area_name FROM restaurant_tables t JOIN dining_areas da ON t.dining_area_id = da.id WHERE t.branch_id = $branchId AND t.is_active = 1 ORDER BY t.sort_order ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-calendar-check text-danger me-2"></i> Table Reservations & Waiting List</h3>
        <p class="text-muted small mb-0">Manage guest bookings, seating schedules, and walk-in queue</p>
    </div>

    <!-- Actions (No modals) -->
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" onclick="RMS.openSlidePanel('addWaitingPanel')">
            <i class="bi bi-person-lines-fill me-1"></i> Add to Waiting List
        </button>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="RMS.openSlidePanel('addReservationPanel')">
            <i class="bi bi-calendar-plus me-1"></i> Book Table
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Active Waiting Queue -->
    <div class="col-12 col-xl-4">
        <div class="card-rms p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <h5 class="fw-bold mb-0">Walk-in Waiting Queue</h5>
                <span class="badge bg-danger rounded-pill"><?= count($waiting) ?> Waiting</span>
            </div>

            <?php if (empty($waiting)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check2-circle text-success fs-1"></i>
                    <div class="mt-2 fw-semibold">No Guests Waiting</div>
                    <small>Walk-ins can be seated immediately.</small>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($waiting as $w): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($w['customer_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($w['phone']) ?></small>
                                </div>
                                <span class="badge bg-primary fs-6"><?= $w['party_size'] ?> Guests</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($w['created_at'])) ?></small>
                                <button type="button" class="btn btn-sm btn-success fw-semibold" onclick="seatWaitingGuest(<?= $w['id'] ?>)">
                                    <i class="bi bi-check2 me-1"></i> Seat Guest
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reservations Table -->
    <div class="col-12 col-xl-8">
        <div class="card-rms overflow-hidden shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small fw-bold">
                        <tr>
                            <th>Guest Name</th>
                            <th>Contact Phone</th>
                            <th>Booking Date & Time</th>
                            <th>Guests</th>
                            <th>Assigned Table</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No reservations scheduled.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $r): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($r['customer_name']) ?></span>
                                        <?php if (!empty($r['special_requests'])): ?>
                                            <small class="text-muted d-block fst-italic"><?= htmlspecialchars($r['special_requests']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-danger"><?= htmlspecialchars($r['phone']) ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= date('d M, Y', strtotime($r['reservation_date'])) ?></span>
                                        <small class="text-muted d-block"><?= date('h:i A', strtotime($r['arrival_time'])) ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-secondary"><?= $r['guests_count'] ?> People</span></td>
                                    <td>
                                        <?php if ($r['table_number']): ?>
                                            <span class="badge bg-danger-subtle text-danger fs-6">Table <?= htmlspecialchars($r['table_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php
                                            echo match($r['status']) {
                                                'confirmed' => 'bg-primary',
                                                'seated' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        ?> text-capitalize"><?= $r['status'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= SLIDE PANELS (NO MODALS) ================= -->

<!-- 1. SLIDE PANEL: BOOK TABLE RESERVATION -->
<div id="addReservationPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-danger">Book Dining Table</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addReservationPanel')"></button>
    </div>
    <form id="reservationForm" onsubmit="submitReservation(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Guest Full Name <span class="text-danger">*</span></label>
            <input type="text" name="customer_name" class="form-control" placeholder="e.g. Dr. Sarah Tariq" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Contact Mobile <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control" placeholder="0300-1122334" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Booking Date <span class="text-danger">*</span></label>
                <input type="date" name="reservation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Arrival Time <span class="text-danger">*</span></label>
                <input type="time" name="arrival_time" class="form-control" value="19:30" required>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Guest Count</label>
                <input type="number" name="guests_count" class="form-control" value="4" min="1" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Assign Table</label>
                <select name="table_id" class="form-select">
                    <option value="">-- Choose Table --</option>
                    <?php foreach ($tables as $tb): ?>
                        <option value="<?= $tb['id'] ?>"><?= htmlspecialchars($tb['table_number']) ?> (<?= $tb['capacity'] ?> Seats - <?= htmlspecialchars($tb['area_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Special Requests / Seating Notes</label>
            <textarea name="special_requests" class="form-control" rows="2" placeholder="e.g. Birthday decor, high chair needed..."></textarea>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-calendar-check me-1"></i> Confirm Reservation
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: ADD TO WAITING LIST -->
<div id="addWaitingPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Add Walk-in to Waiting List</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addWaitingPanel')"></button>
    </div>
    <form id="waitingForm" onsubmit="submitWaiting(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Customer Name <span class="text-danger">*</span></label>
            <input type="text" name="customer_name" class="form-control" placeholder="Walk-in guest name" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Mobile Number <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control" placeholder="0300-0000000" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Party Size (Number of People) <span class="text-danger">*</span></label>
            <input type="number" name="party_size" class="form-control fw-bold" value="2" min="1" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Seating Preference</label>
            <input type="text" name="notes" class="form-control" placeholder="e.g. Rooftop preferred, inside hall...">
        </div>
        <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold">
            <i class="bi bi-person-lines-fill me-1"></i> Add to Queue
        </button>
    </form>
</div>

<script>
async function submitReservation(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('reservationForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/reservations.php?action=create_reservation', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}

async function submitWaiting(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('waitingForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/reservations.php?action=add_waiting', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}

async function seatWaitingGuest(id) {
    const fd = new FormData();
    fd.append('waiting_id', id);
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/reservations.php?action=seat_waiting', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
