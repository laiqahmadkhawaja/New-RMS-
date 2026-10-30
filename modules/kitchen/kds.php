<?php
// modules/kitchen/kds.php - Real-Time Kitchen Display System (KDS)
$pageTitle = 'Kitchen Display System';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('kitchen.kds');

$db = getDB();
$branchId = currentBranchId();
$stations = $db->query("SELECT * FROM kitchen_stations WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
?>

<!-- KDS Top Navigation & Station Filters -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-tv text-danger me-2"></i> Kitchen Display System (KDS)</h3>
        <p class="text-muted small mb-0">Live order tickets with preparation timers and station routing</p>
    </div>

    <!-- Kitchen Station Filter Pills -->
    <div class="btn-group flex-wrap shadow-sm" role="group">
        <button type="button" class="btn btn-sm btn-danger station-btn active" data-id="" onclick="KDS.filterStation('', this)">
            All Stations
        </button>
        <?php foreach ($stations as $st): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary station-btn" data-id="<?= $st['id'] ?>" onclick="KDS.filterStation(<?= $st['id'] ?>, this)">
                <?= htmlspecialchars($st['name']) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- KDS Live Tickets Grid -->
<div class="row g-3" id="kdsTicketsGrid">
    <div class="col-12 text-center text-muted py-5">
        <div class="spinner-border text-danger mb-2" role="status"></div>
        <div>Connecting to Kitchen Display Feed...</div>
    </div>
</div>

<script>
const KDS = {
    selectedStation: '',
    pollInterval: null,

    init: function() {
        KDS.fetchTickets();
        // Auto-refresh poll every 5 seconds (seamless, no page reloads)
        KDS.pollInterval = setInterval(KDS.fetchTickets, 5000);
    },

    filterStation: function(stationId, btn) {
        document.querySelectorAll('.station-btn').forEach(b => {
            b.classList.remove('btn-danger', 'active');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.add('btn-danger', 'active');
        btn.classList.remove('btn-outline-secondary');
        KDS.selectedStation = stationId;
        KDS.fetchTickets();
    },

    fetchTickets: async function() {
        try {
            const url = '<?= BASE_URL ?>ajax/kitchen.php?action=get_tickets&station_id=' + KDS.selectedStation;
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                KDS.renderTickets(data.orders);
            }
        } catch (e) {
            console.error('KDS poll error', e);
        }
    },

    renderTickets: function(orders) {
        const grid = document.getElementById('kdsTicketsGrid');
        if (!orders || orders.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="bi bi-check2-circle text-success" style="font-size: 3.5rem;"></i>
                    <h4 class="mt-3 fw-bold text-dark">Kitchen is Clear!</h4>
                    <p class="text-muted">No pending orders waiting for preparation right now.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = orders.map(o => {
            const isLate = o.elapsed_minutes >= 15;
            const statusClass = o.kitchen_status === 'ready' ? 'status-ready' : (o.kitchen_status === 'preparing' ? 'status-preparing' : 'status-pending');
            const timerBadgeClass = isLate ? 'bg-danger text-white' : 'bg-light text-dark border';

            return `
                <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                    <div class="card kds-card ${statusClass} shadow-sm h-100 d-flex flex-column">
                        <!-- Ticket Header -->
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold fs-5 text-dark">${o.order_number}</span>
                                <div class="small fw-semibold text-danger">
                                    ${o.order_type_name} ${o.table_number ? ' | Table: ' + o.table_number : ''}
                                </div>
                            </div>
                            <span class="badge ${timerBadgeClass} fs-6 px-2 py-1">
                                <i class="bi bi-clock me-1"></i>${o.elapsed_minutes}m
                            </span>
                        </div>

                        <!-- Ticket Items List -->
                        <div class="p-3 flex-grow-1 overflow-y-auto" style="max-height: 280px;">
                            ${(o.items || []).map(it => `
                                <div class="mb-2 pb-2 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="fw-bold fs-6 text-dark">${it.quantity}x ${it.item_name}</span>
                                        ${it.variant_name ? `<span class="badge bg-secondary-subtle text-secondary small">${it.variant_name}</span>` : ''}
                                    </div>
                                    ${(it.modifiers || []).length > 0 ? `
                                        <div class="small text-danger ps-2">
                                            ${it.modifiers.map(m => '+ ' + m.modifier_name).join(', ')}
                                        </div>
                                    ` : ''}
                                    ${it.special_instructions ? `
                                        <div class="small text-muted bg-warning-subtle p-1 rounded mt-1 fst-italic">
                                            <i class="bi bi-info-circle me-1"></i>${it.special_instructions}
                                        </div>
                                    ` : ''}
                                </div>
                            `).join('')}
                        </div>

                        <!-- Ticket Action Buttons -->
                        <div class="p-2 bg-light border-top d-flex gap-2">
                            <a href="<?= BASE_URL ?>print/kitchen-ticket.php?order_id=${o.order_id}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Reprint Ticket">
                                <i class="bi bi-printer"></i>
                            </a>
                            ${o.kitchen_status === 'pending' ? `
                                <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold flex-grow-1" onclick="KDS.updateStatus(${o.order_id}, 'preparing')">
                                    <i class="bi bi-fire me-1"></i> Start Prep
                                </button>
                            ` : ''}
                            ${o.kitchen_status === 'preparing' ? `
                                <button type="button" class="btn btn-success btn-sm fw-semibold flex-grow-1" onclick="KDS.updateStatus(${o.order_id}, 'ready')">
                                    <i class="bi bi-check-circle me-1"></i> Mark Ready
                                </button>
                            ` : ''}
                            ${o.kitchen_status === 'ready' ? `
                                <button type="button" class="btn btn-primary btn-sm fw-semibold flex-grow-1" onclick="KDS.updateStatus(${o.order_id}, 'completed')">
                                    <i class="bi bi-check2-all me-1"></i> Mark Served
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    updateStatus: async function(orderId, status) {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('status', status);

        try {
            await RMS.api('<?= BASE_URL ?>ajax/kitchen.php?action=update_status', {
                method: 'POST',
                body: formData
            });
            KDS.fetchTickets();
        } catch (e) {}
    }
};

document.addEventListener('DOMContentLoaded', KDS.init);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
