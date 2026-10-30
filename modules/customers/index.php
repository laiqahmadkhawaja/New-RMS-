<?php
// modules/customers/index.php - Customer Directory & Loyalty CRM
$pageTitle = 'Customer Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('customers.manage');

$db = getDB();
$customers = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) as orders_count,
           (SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE customer_id = c.id) as total_spend 
    FROM customers c 
    WHERE c.is_active = 1 
    ORDER BY c.id DESC
")->fetchAll();

$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();
$totalReceivables = array_sum(array_column($customers, 'current_balance'));
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-people text-danger me-2"></i> Customer Directory & Loyalty</h3>
        <p class="text-muted small mb-0">Total Outstanding Accounts Receivable (Customers): <b class="text-danger fs-6"><?= formatCurrency($totalReceivables) ?></b></p>
    </div>
    <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddCustomer()">
        <i class="bi bi-person-plus me-1"></i> Add Customer
    </button>
</div>

<!-- Customer Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Address / Delivery Notes</th>
                    <th>Loyalty Points</th>
                    <th>Orders / Spend</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No customers registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): 
                        $bal = (float)$c['current_balance'];
                    ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($c['email'] ?? '') ?></small>
                            </td>
                            <td class="fw-semibold text-danger"><?= htmlspecialchars($c['phone']) ?></td>
                            <td class="small text-muted">
                                <div><?= htmlspecialchars($c['address'] ?? '-') ?></div>
                                <?php if (!empty($c['delivery_notes'])): ?>
                                    <div class="fst-italic text-secondary">Note: <?= htmlspecialchars($c['delivery_notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-warning-subtle text-dark fs-6">
                                    <i class="bi bi-star-fill text-warning me-1"></i><?= $c['loyalty_points'] ?> pts
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold"><?= formatCurrency($c['total_spend']) ?></div>
                                <small class="text-muted"><?= $c['orders_count'] ?> orders</small>
                            </td>
                            <td class="text-end fw-bold fs-6 <?= $bal > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= formatCurrency($bal) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick='openEditCustomer(<?= json_encode($c) ?>)' title="Edit Profile">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>modules/customers/ledger.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline-dark" title="View Statement">
                                        <i class="bi bi-file-text"></i>
                                    </a>
                                    <?php if ($bal > 0): ?>
                                        <button class="btn btn-outline-success fw-semibold" onclick="openReceivePayment(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>', <?= $bal ?>)" title="Receive Payment">
                                            <i class="bi bi-cash-stack"></i> Settle
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

<!-- 1. SLIDE PANEL: ADD / EDIT CUSTOMER -->
<div id="customerSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="custPanelTitle">Add New Customer</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('customerSlidePanel')"></button>
    </div>
    <form id="customerForm" onsubmit="submitCustomer(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="customer_id" id="custIdInput" value="0">

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="custNameInput" class="form-control" placeholder="e.g. Laiq Ahmad" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Phone Number <span class="text-danger">*</span></label>
                <input type="text" name="phone" id="custPhoneInput" class="form-control" placeholder="0300-1234567" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Email</label>
                <input type="email" name="email" id="custEmailInput" class="form-control" placeholder="customer@email.com">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Delivery Address</label>
            <textarea name="address" id="custAddressInput" class="form-control" rows="2" placeholder="Street, house number, area..."></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Rider / Delivery Instructions</label>
            <textarea name="delivery_notes" id="custNotesInput" class="form-control" rows="2" placeholder="e.g. Ring bell twice, gate code..."></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Authorized Credit Limit (Rs.)</label>
            <input type="number" name="credit_limit" id="custCreditLimitInput" class="form-control" value="0.00" step="0.01">
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Customer
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: RECEIVE PAYMENT -->
<div id="receivePaySlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-success">Receive Customer Credit Payment</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('receivePaySlidePanel')"></button>
    </div>
    <form id="receivePayForm" onsubmit="submitReceivePayment(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="customer_id" id="recCustId">
        <div class="card p-3 bg-light border mb-3">
            <div class="fw-bold fs-6 text-dark" id="recCustName"></div>
            <div class="small text-muted">Outstanding Balance: <b class="text-danger" id="recCustBalance"></b></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Amount Received (Rs.) <span class="text-danger">*</span></label>
            <input type="number" name="amount" id="recAmountInput" class="form-control fw-bold" step="0.01" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Payment Method</label>
            <select name="payment_method_id" class="form-select">
                <?php foreach ($paymentMethods as $pm): ?>
                    <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Notes / Receipt Ref</label>
            <input type="text" name="notes" class="form-control" placeholder="Cash collection, bank transfer slip...">
        </div>

        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
            <i class="bi bi-check2-all me-1"></i> Post Payment Settlement
        </button>
    </form>
</div>

<script>
function openAddCustomer() {
    document.getElementById('custPanelTitle').innerText = 'Add New Customer';
    document.getElementById('custIdInput').value = '0';
    document.getElementById('custNameInput').value = '';
    document.getElementById('custPhoneInput').value = '';
    document.getElementById('custEmailInput').value = '';
    document.getElementById('custAddressInput').value = '';
    document.getElementById('custNotesInput').value = '';
    document.getElementById('custCreditLimitInput').value = '0.00';
    RMS.openSlidePanel('customerSlidePanel');
}

function openEditCustomer(c) {
    document.getElementById('custPanelTitle').innerText = 'Edit Customer: ' + c.name;
    document.getElementById('custIdInput').value = c.id;
    document.getElementById('custNameInput').value = c.name;
    document.getElementById('custPhoneInput').value = c.phone;
    document.getElementById('custEmailInput').value = c.email || '';
    document.getElementById('custAddressInput').value = c.address || '';
    document.getElementById('custNotesInput').value = c.delivery_notes || '';
    document.getElementById('custCreditLimitInput').value = c.credit_limit;
    RMS.openSlidePanel('customerSlidePanel');
}

async function submitCustomer(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('customerForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/customers.php?action=save_customer', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}

function openReceivePayment(id, name, bal) {
    document.getElementById('recCustId').value = id;
    document.getElementById('recCustName').innerText = name;
    document.getElementById('recCustBalance').innerText = RMS.formatMoney(bal);
    document.getElementById('recAmountInput').value = bal;
    document.getElementById('recAmountInput').max = bal;
    RMS.openSlidePanel('receivePaySlidePanel');
}

async function submitReceivePayment(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('receivePayForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/customers.php?action=receive_payment', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
