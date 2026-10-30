<?php
// modules/purchasing/suppliers.php - Supplier Directory & Balances
$pageTitle = 'Suppliers Directory';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('purchasing.manage');

$db = getDB();
$suppliers = $db->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = s.id) as orders_count 
    FROM suppliers s 
    WHERE s.is_active = 1 
    ORDER BY s.name ASC
")->fetchAll();

$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();
$totalPayable = array_sum(array_column($suppliers, 'current_balance'));
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-people-fill text-danger me-2"></i> Supplier Master & Ledgers</h3>
        <p class="text-muted small mb-0">Total Accounts Payable: <b class="text-danger fs-6"><?= formatCurrency($totalPayable) ?></b></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/purchasing/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Purchases
        </a>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddSupplier()">
            <i class="bi bi-plus-circle me-1"></i> Add Supplier
        </button>
    </div>
</div>

<!-- Supplier List -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email / Address</th>
                    <th>Purchase Orders</th>
                    <th class="text-end">Current Balance</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No suppliers registered.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): 
                        $bal = (float)$s['current_balance'];
                    ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
                            <td class="fw-semibold text-danger"><?= htmlspecialchars($s['phone']) ?></td>
                            <td class="small text-muted">
                                <div><?= htmlspecialchars($s['email'] ?? '') ?></div>
                                <div><?= htmlspecialchars($s['address'] ?? '') ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= $s['orders_count'] ?> orders</span></td>
                            <td class="text-end fw-bold fs-6 <?= $bal > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= formatCurrency($bal) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick='openEditSupplier(<?= json_encode($s) ?>)' title="Edit Details">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if ($bal > 0): ?>
                                        <button class="btn btn-outline-success fw-semibold" onclick="openPaySupplier(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>', <?= $bal ?>)" title="Pay Supplier">
                                            <i class="bi bi-cash me-1"></i> Pay
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

<!-- 1. SLIDE PANEL: ADD / EDIT SUPPLIER -->
<div id="supplierSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="supPanelTitle">Add New Supplier</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('supplierSlidePanel')"></button>
    </div>
    <form id="supplierForm" onsubmit="submitSupplier(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="supplier_id" id="supIdInput" value="0">

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Supplier / Vendor Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="supNameInput" class="form-control" placeholder="e.g. Metro Wholesale, Fresh Farms..." required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Contact Person</label>
            <input type="text" name="contact_person" id="supPersonInput" class="form-control" placeholder="e.g. Mr. Rashid">
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Phone Number <span class="text-danger">*</span></label>
                <input type="text" name="phone" id="supPhoneInput" class="form-control" placeholder="+92 300 1234567" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Email</label>
                <input type="email" name="email" id="supEmailInput" class="form-control" placeholder="vendor@email.com">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Office / Warehouse Address</label>
            <textarea name="address" id="supAddressInput" class="form-control" rows="2" placeholder="Full address..."></textarea>
        </div>

        <div class="mb-3" id="openBalDiv">
            <label class="form-label small fw-semibold text-muted text-uppercase">Opening Balance (Rs.)</label>
            <input type="number" name="opening_balance" id="supBalInput" class="form-control" value="0.00" step="0.01">
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Supplier
        </button>
    </form>
</div>

<!-- 2. SLIDE PANEL: DISBURSE SUPPLIER PAYMENT -->
<div id="paySupplierSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-success">Make Supplier Payment</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('paySupplierSlidePanel')"></button>
    </div>
    <form id="paySupplierForm" onsubmit="submitPaySupplier(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="supplier_id" id="paySupId">
        <div class="card p-3 bg-light border mb-3">
            <div class="fw-bold fs-6 text-dark" id="paySupName"></div>
            <div class="small text-muted">Outstanding Payable: <b class="text-danger" id="paySupBalance"></b></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Payment Amount (Rs.) <span class="text-danger">*</span></label>
            <input type="number" name="amount" id="payAmountInput" class="form-control fw-bold" step="0.01" required>
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
            <label class="form-label small fw-semibold text-muted text-uppercase">Payment Reference / Cheque #</label>
            <input type="text" name="notes" class="form-control" placeholder="Cheque number, bank transfer ID...">
        </div>

        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
            <i class="bi bi-check2-all me-1"></i> Post Payment to Ledger
        </button>
    </form>
</div>

<script>
function openAddSupplier() {
    document.getElementById('supPanelTitle').innerText = 'Add New Supplier';
    document.getElementById('supIdInput').value = '0';
    document.getElementById('supNameInput').value = '';
    document.getElementById('supPersonInput').value = '';
    document.getElementById('supPhoneInput').value = '';
    document.getElementById('supEmailInput').value = '';
    document.getElementById('supAddressInput').value = '';
    document.getElementById('openBalDiv').style.display = 'block';
    RMS.openSlidePanel('supplierSlidePanel');
}

function openEditSupplier(s) {
    document.getElementById('supPanelTitle').innerText = 'Edit Supplier: ' + s.name;
    document.getElementById('supIdInput').value = s.id;
    document.getElementById('supNameInput').value = s.name;
    document.getElementById('supPersonInput').value = s.contact_person || '';
    document.getElementById('supPhoneInput').value = s.phone;
    document.getElementById('supEmailInput').value = s.email || '';
    document.getElementById('supAddressInput').value = s.address || '';
    document.getElementById('openBalDiv').style.display = 'none';
    RMS.openSlidePanel('supplierSlidePanel');
}

async function submitSupplier(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('supplierForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/purchasing.php?action=save_supplier', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}

function openPaySupplier(id, name, bal) {
    document.getElementById('paySupId').value = id;
    document.getElementById('paySupName').innerText = name;
    document.getElementById('paySupBalance').innerText = RMS.formatMoney(bal);
    document.getElementById('payAmountInput').value = bal;
    document.getElementById('payAmountInput').max = bal;
    RMS.openSlidePanel('paySupplierSlidePanel');
}

async function submitPaySupplier(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('paySupplierForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/purchasing.php?action=pay_supplier', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
