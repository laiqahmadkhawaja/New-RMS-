<?php
// print/invoice.php - Clean Standard A4 Invoice Format
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$orderId = (int)($_GET['order_id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("
    SELECT o.*, o.customer_name as order_cust_name, o.customer_phone as order_cust_phone,
           b.name as branch_name, b.phone as branch_phone, b.email as branch_email, 
           b.address as branch_address, ot.name as order_type_name, 
           t.table_number, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, 
           u.full_name as cashier_name 
    FROM orders o 
    JOIN branches b ON o.branch_id = b.id 
    JOIN order_types ot ON o.order_type_id = ot.id 
    LEFT JOIN restaurant_tables t ON o.table_id = t.id 
    LEFT JOIN customers c ON o.customer_id = c.id 
    JOIN users u ON o.cashier_id = u.id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) die("Order not found.");

$items = $db->query("SELECT * FROM order_items WHERE order_id = $orderId")->fetchAll();
foreach ($items as &$it) {
    $it['modifiers'] = $db->query("SELECT * FROM order_item_modifiers WHERE order_item_id = " . $it['id'])->fetchAll();
}
$payments = $db->query("
    SELECT p.*, pm.name as method_name 
    FROM payments p 
    JOIN payment_methods pm ON p.payment_method_id = pm.id 
    WHERE p.order_id = $orderId
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= htmlspecialchars($order['order_number']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; font-family: sans-serif; padding: 20px; }
        .invoice-box {
            max-width: 850px;
            margin: auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-box { box-shadow: none; padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-danger"><i class="bi bi-printer"></i> Print A4 Invoice</button>
</div>

<div class="invoice-box">
    <!-- Header -->
    <div class="row align-items-center mb-4">
        <div class="col-6">
            <h2 class="fw-bold text-danger mb-1"><?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></h2>
            <div class="text-muted small"><?= htmlspecialchars($order['branch_address']) ?></div>
            <div class="text-muted small">Phone: <?= htmlspecialchars($order['branch_phone']) ?> | Email: <?= htmlspecialchars($order['branch_email'] ?? '') ?></div>
        </div>
        <div class="col-6 text-end">
            <h3 class="text-uppercase fw-bold text-secondary mb-1">INVOICE</h3>
            <div class="fw-bold">Invoice #: <?= htmlspecialchars($order['order_number']) ?></div>
            <div class="text-muted small">Date: <?= date('d M, Y h:i A', strtotime($order['created_at'])) ?></div>
            <div class="text-muted small">Channel: <?= htmlspecialchars($order['order_type_name']) ?></div>
        </div>
    </div>

    <hr>

    <?php 
    $printCustName = !empty($order['order_cust_name']) ? $order['order_cust_name'] : (!empty($order['customer_name']) ? $order['customer_name'] : 'Walk-in Customer');
    $printCustPhone = !empty($order['order_cust_phone']) ? $order['order_cust_phone'] : (!empty($order['customer_phone']) && $order['customer_id'] > 1 ? $order['customer_phone'] : '');
    ?>
    <!-- Bill To -->
    <div class="row mb-4">
        <div class="col-6">
            <span class="text-muted small text-uppercase fw-bold">Billed To:</span>
            <div class="fw-bold fs-6"><?= htmlspecialchars($printCustName) ?></div>
            <?php if (!empty($printCustPhone)): ?>
                <div class="text-muted small">Phone: <?= htmlspecialchars($printCustPhone) ?></div>
            <?php endif; ?>
            <?php if (!empty($order['customer_address'])): ?>
                <div class="text-muted small">Address: <?= htmlspecialchars($order['customer_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted small text-uppercase fw-bold">Order Details:</span>
            <?php if (!empty($order['table_number'])): ?>
                <div>Table: <b><?= htmlspecialchars($order['table_number']) ?></b></div>
            <?php endif; ?>
            <div>Cashier: <b><?= htmlspecialchars($order['cashier_name']) ?></b></div>
            <div>Payment: <span class="badge bg-success-subtle text-success text-uppercase"><?= $order['payment_status'] ?></span></div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="table table-bordered mb-4">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Menu Item & Options</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td>
                        <span class="fw-semibold"><?= htmlspecialchars($it['item_name']) ?></span>
                        <?= $it['variant_name'] ? ' (' . htmlspecialchars($it['variant_name']) . ')' : '' ?>
                        <?php if (!empty($it['modifiers'])): ?>
                            <div class="small text-danger">
                                <?= implode(', ', array_map(fn($m) => '+ ' . $m['modifier_name'] . ' (' . formatCurrency($m['price']) . ')', $it['modifiers'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= $it['quantity'] ?></td>
                    <td class="text-end"><?= formatCurrency($it['unit_price']) ?></td>
                    <td class="text-end"><?= formatCurrency($it['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                <td class="text-end fw-bold"><?= formatCurrency($order['subtotal']) ?></td>
            </tr>
            <?php if ($order['discount_amount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end text-danger">Discount:</td>
                    <td class="text-end text-danger">-<?= formatCurrency($order['discount_amount']) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($order['tax_amount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end">Sales Tax (<?= $order['tax_percent'] ?>%):</td>
                    <td class="text-end"><?= formatCurrency($order['tax_amount']) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($order['service_charge_amount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end">Service Charge (<?= $order['service_charge_percent'] ?>%):</td>
                    <td class="text-end"><?= formatCurrency($order['service_charge_amount']) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($order['delivery_fee'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end">Delivery Fee:</td>
                    <td class="text-end"><?= formatCurrency($order['delivery_fee']) ?></td>
                </tr>
            <?php endif; ?>
            <tr class="table-light fs-5">
                <td colspan="4" class="text-end fw-bold text-dark">Grand Total:</td>
                <td class="text-end fw-bold text-danger"><?= formatCurrency($order['grand_total']) ?></td>
            </tr>
            <tr>
                <td colspan="4" class="text-end fw-semibold">Paid Amount:</td>
                <td class="text-end fw-semibold text-success"><?= formatCurrency($order['paid_amount']) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="row pt-3">
        <div class="col-8">
            <span class="fw-bold small text-muted">Notes / Terms:</span>
            <div class="small text-muted">Goods once sold can only be exchanged or refunded subject to management inspection. Thank you for your business!</div>
        </div>
        <div class="col-4 text-center">
            <div style="border-bottom: 1px solid #aaa; margin-top: 40px;"></div>
            <small class="text-muted">Authorized Signature</small>
        </div>
    </div>
</div>

</body>
</html>
