<?php
// modules/orders/index.php - Order History, Search & Refund Management
$pageTitle = 'Order Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('orders.view');

$db = getDB();
$branchId = currentBranchId();

// Search & Filter Parameters (PRD 6.3)
$search = cleanInput(get('search', ''));
$status = cleanInput(get('status', ''));
$payStatus = cleanInput(get('payment_status', ''));
$orderType = cleanInput(get('order_type', ''));
$dateFrom = cleanInput(get('date_from', date('Y-m-01')));
$dateTo = cleanInput(get('date_to', date('Y-m-d')));

$sql = "
    SELECT o.*, ot.name as order_type_name, t.table_number, c.name as customer_name, c.phone as customer_phone, u.full_name as cashier_name 
    FROM orders o 
    JOIN order_types ot ON o.order_type_id = ot.id 
    LEFT JOIN restaurant_tables t ON o.table_id = t.id 
    LEFT JOIN customers c ON o.customer_id = c.id 
    JOIN users u ON o.cashier_id = u.id 
    WHERE o.branch_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
";
$params = [$branchId, $dateFrom, $dateTo];

if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($status)) {
    $sql .= " AND o.order_status = ?";
    $params[] = $status;
}
if (!empty($payStatus)) {
    $sql .= " AND o.payment_status = ?";
    $params[] = $payStatus;
}
if (!empty($orderType)) {
    $sql .= " AND o.order_type_id = ?";
    $params[] = $orderType;
}

$sql .= " ORDER BY o.id DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$orderTypes = $db->query("SELECT * FROM order_types WHERE is_active = 1")->fetchAll();
$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();
?>

<!-- Orders Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-receipt text-danger me-2"></i> Orders & Invoices</h3>
        <p class="text-muted small mb-0">Search historical orders, inspect items, print receipts, and issue refunds</p>
    </div>
    <a href="<?= BASE_URL ?>modules/pos/index.php" class="btn btn-danger btn-sm fw-semibold">
        <i class="bi bi-cart-plus me-1"></i> New POS Order
    </a>
</div>

<!-- Filters Card -->
<div class="card-rms p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, Customer..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-muted">Order Channel</label>
            <select name="order_type" class="form-select form-select-sm">
                <option value="">All Channels</option>
                <?php foreach ($orderTypes as $ot): ?>
                    <option value="<?= $ot['id'] ?>" <?= $orderType == $ot['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ot['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-muted">Payment Status</label>
            <select name="payment_status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="paid" <?= $payStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="unpaid" <?= $payStatus === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="partially_paid" <?= $payStatus === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                <option value="refunded" <?= $payStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="col-12 col-md-1">
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold">
                <i class="bi bi-funnel"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Order #</th>
                    <th>Date & Time</th>
                    <th>Type / Destination</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Kitchen</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2"></i>
                            <div class="mt-2">No orders matched your search criteria.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($o['order_number']) ?></td>
                            <td class="small text-muted"><?= date('d M, Y h:i A', strtotime($o['created_at'])) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($o['order_type_name']) ?></span>
                                <?= $o['table_number'] ? '<span class="badge bg-danger-subtle text-danger ms-1">T-' . htmlspecialchars($o['table_number']) . '</span>' : '' ?>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= htmlspecialchars($o['customer_name'] ?? 'Walk-in') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($o['customer_phone'] ?? '') ?></small>
                            </td>
                            <td class="fw-bold"><?= formatCurrency($o['grand_total']) ?></td>
                            <td>
                                <span class="badge <?php
                                    echo match($o['payment_status']) {
                                        'paid' => 'bg-success',
                                        'partially_paid' => 'bg-warning text-dark',
                                        'refunded' => 'bg-secondary',
                                        default => 'bg-danger'
                                    };
                                ?> text-capitalize"><?= str_replace('_', ' ', $o['payment_status']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border text-capitalize"><?= $o['kitchen_status'] ?></span>
                            </td>
                            <td>
                                <span class="badge <?php
                                    echo match($o['order_status']) {
                                        'completed' => 'bg-success-subtle text-success',
                                        'cancelled' => 'bg-danger-subtle text-danger',
                                        default => 'bg-primary-subtle text-primary'
                                    };
                                ?> text-capitalize"><?= str_replace('_', ' ', $o['order_status']) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick="viewOrderDetails(<?= $o['id'] ?>)" title="Inspect Items">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>print/receipt.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Thermal Receipt">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>print/invoice.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="A4 Invoice">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                    <?php if ($o['order_status'] !== 'cancelled' && userHasPermission('orders.cancel')): ?>
                                        <button class="btn btn-outline-danger" onclick="openCancelOrder(<?= $o['id'] ?>, '<?= $o['order_number'] ?>')" title="Cancel Order">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($o['paid_amount'] > 0 && userHasPermission('orders.refund')): ?>
                                        <button class="btn btn-outline-warning text-dark" onclick="openRefund(<?= $o['id'] ?>, '<?= $o['order_number'] ?>', <?= $o['paid_amount'] ?>)" title="Refund Order">
                                            <i class="bi bi-arrow-counterclockwise"></i>
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

<!-- 1. SLIDE PANEL: ORDER DETAILS VIEW -->
<div id="orderDetailsPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="detOrderNumber">Order Details</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('orderDetailsPanel')"></button>
    </div>
    <div class="p-3 flex-grow-1 overflow-y-auto" id="detOrderContent">
        <div class="text-center py-5"><div class="spinner-border text-danger"></div></div>
    </div>
</div>

<!-- 2. SLIDE PANEL: CANCEL ORDER -->
<div id="cancelOrderPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-danger">Cancel Order</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('cancelOrderPanel')"></button>
    </div>
    <form id="cancelOrderForm" onsubmit="submitCancelOrder(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="order_id" id="cancelOrderId">
        <div class="alert alert-danger py-2 small mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Cancelling order <b id="cancelOrderNumDisplay"></b> will release any occupied tables and mark the order as void in the audit log.
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Cancellation Reason</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="Provide mandatory reason for audit record..." required></textarea>
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-x-circle me-1"></i> Confirm Cancellation
        </button>
    </form>
</div>

<!-- 3. SLIDE PANEL: PROCESS REFUND -->
<div id="refundPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-dark">Process Customer Refund</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('refundPanel')"></button>
    </div>
    <form id="refundForm" onsubmit="submitRefund(event)" class="p-3 flex-grow-1 overflow-y-auto">
        <input type="hidden" name="order_id" id="refundOrderId">
        <div class="card p-3 bg-light border mb-3">
            <div class="small text-muted">Order Reference: <b id="refundOrderNumDisplay"></b></div>
            <div class="small text-muted">Max Refundable: <b class="text-danger" id="refundMaxDisplay"></b></div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Refund Amount</label>
            <input type="number" name="amount" id="refundAmountInput" class="form-control fw-bold" step="0.01" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Refund Payment Method</label>
            <select name="refund_method_id" class="form-select">
                <?php foreach ($paymentMethods as $pm): ?>
                    <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Reason for Refund</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Customer cancelled, food issue..." required>
        </div>

        <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Post Refund to Accounts
        </button>
    </form>
</div>

<script>
async function viewOrderDetails(orderId) {
    RMS.openSlidePanel('orderDetailsPanel');
    const container = document.getElementById('detOrderContent');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-danger"></div></div>';

    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=get_order_details&order_id=' + orderId);
        const o = res.order;
        document.getElementById('detOrderNumber').innerText = 'Order #' + o.order_number;

        container.innerHTML = `
            <div class="card p-3 bg-light border-0 mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Date:</span>
                    <span class="fw-semibold small">${o.created_at}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Status:</span>
                    <span class="badge bg-danger-subtle text-danger text-uppercase">${o.order_status}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Payment:</span>
                    <span class="badge bg-success-subtle text-success text-uppercase">${o.payment_status}</span>
                </div>
            </div>

            <h6 class="fw-bold mb-2">Order Items</h6>
            <div class="list-group mb-3">
                ${(o.items || []).map(it => `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">${it.quantity}x ${it.item_name} ${it.variant_name ? '('+it.variant_name+')' : ''}</div>
                            ${(it.modifiers || []).map(m => `<small class="text-danger d-block">+ ${m.modifier_name}</small>`).join('')}
                        </div>
                        <span class="fw-bold">${RMS.formatMoney(it.subtotal)}</span>
                    </div>
                `).join('')}
            </div>

            <div class="border-top pt-2">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Subtotal:</span>
                    <span class="fw-semibold">${RMS.formatMoney(o.subtotal)}</span>
                </div>
                ${o.discount_amount > 0 ? `
                    <div class="d-flex justify-content-between small text-danger mb-1">
                        <span>Discount:</span>
                        <span>-${RMS.formatMoney(o.discount_amount)}</span>
                    </div>
                ` : ''}
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Tax (${o.tax_percent}%):</span>
                    <span class="fw-semibold">${RMS.formatMoney(o.tax_amount)}</span>
                </div>
                <div class="d-flex justify-content-between fs-5 fw-bold text-danger border-top pt-2">
                    <span>Grand Total:</span>
                    <span>${RMS.formatMoney(o.grand_total)}</span>
                </div>
            </div>
        `;
    } catch(err) {}
}

function openCancelOrder(id, orderNum) {
    document.getElementById('cancelOrderId').value = id;
    document.getElementById('cancelOrderNumDisplay').innerText = orderNum;
    RMS.openSlidePanel('cancelOrderPanel');
}

async function submitCancelOrder(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('cancelOrderForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/orders.php?action=cancel_order', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } catch(err) {}
}

function openRefund(id, orderNum, paidAmt) {
    document.getElementById('refundOrderId').value = id;
    document.getElementById('refundOrderNumDisplay').innerText = orderNum;
    document.getElementById('refundMaxDisplay').innerText = RMS.formatMoney(paidAmt);
    document.getElementById('refundAmountInput').value = paidAmt;
    document.getElementById('refundAmountInput').max = paidAmt;
    RMS.openSlidePanel('refundPanel');
}

async function submitRefund(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('refundForm'));
    try {
        const res = await RMS.api('<?= BASE_URL ?>ajax/orders.php?action=process_refund', { method: 'POST', body: fd });
        RMS.toast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } catch(err) {}
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
