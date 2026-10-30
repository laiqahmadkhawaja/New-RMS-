<?php
// print/receipt.php - Thermal Receipt Printing (80mm & 58mm formats)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$orderId = (int)($_GET['order_id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("
    SELECT o.*, o.customer_name as order_cust_name, o.customer_phone as order_cust_phone,
           b.name as branch_name, b.phone as branch_phone, b.address as branch_address, 
           b.receipt_header, b.receipt_footer, ot.name as order_type_name, 
           t.table_number, c.name as customer_name, c.phone as customer_phone, u.full_name as cashier_name 
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

if (!$order) {
    die("Order not found.");
}

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

$width = (int)($_GET['width'] ?? 80);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= htmlspecialchars($order['order_number']) ?></title>
    <style>
        @page {
            margin: 0;
            size: auto;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 8px;
            width: <?= $width === 58 ? '54mm' : '76mm' ?>;
            line-height: 1.3;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .dashed-line { border-bottom: 1px dashed #000; margin: 6px 0; }
        .double-line { border-bottom: 2px solid #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 0; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="no-print" style="margin-bottom: 10px; text-align: center;">
    <button onclick="window.print()" style="padding: 6px 16px; cursor: pointer;">🖨️ Print Receipt</button>
</div>

<div class="text-center">
    <h3 style="margin: 0; text-transform: uppercase;"><?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></h3>
    <div><?= htmlspecialchars($order['branch_address'] ?? '') ?></div>
    <div>Tel: <?= htmlspecialchars($order['branch_phone'] ?? '') ?></div>
    <?php if (!empty($order['receipt_header'])): ?>
        <div style="font-size: 11px;"><?= nl2br(htmlspecialchars($order['receipt_header'])) ?></div>
    <?php endif; ?>
</div>

<div class="dashed-line"></div>

<div>
    <div><b>Inv:</b> <?= htmlspecialchars($order['order_number']) ?></div>
    <div><b>Date:</b> <?= date('d-M-Y h:i A', strtotime($order['created_at'])) ?></div>
    <div><b>Type:</b> <?= htmlspecialchars($order['order_type_name']) ?> <?= $order['table_number'] ? ' (Table: ' . htmlspecialchars($order['table_number']) . ')' : '' ?></div>
    <div><b>Cashier:</b> <?= htmlspecialchars($order['cashier_name']) ?></div>
    <?php 
    $printCustName = !empty($order['order_cust_name']) ? $order['order_cust_name'] : (!empty($order['customer_name']) && $order['customer_id'] > 1 ? $order['customer_name'] : '');
    $printCustPhone = !empty($order['order_cust_phone']) ? $order['order_cust_phone'] : (!empty($order['customer_phone']) && $order['customer_id'] > 1 ? $order['customer_phone'] : '');
    ?>
    <?php if (!empty($printCustName) || !empty($printCustPhone)): ?>
        <div><b>Customer:</b> <?= htmlspecialchars($printCustName) ?><?= !empty($printCustPhone) ? ' (' . htmlspecialchars($printCustPhone) . ')' : '' ?></div>
    <?php endif; ?>
</div>

<div class="dashed-line"></div>

<table>
    <thead>
        <tr>
            <th align="left">Item</th>
            <th align="center">Qty</th>
            <th align="right">Price</th>
            <th align="right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td colspan="4" class="fw-bold">
                    <?= htmlspecialchars($it['item_name']) ?>
                    <?= $it['variant_name'] ? '(' . htmlspecialchars($it['variant_name']) . ')' : '' ?>
                </td>
            </tr>
            <tr>
                <td style="font-size: 11px; padding-left: 8px;">
                    <?php if (!empty($it['modifiers'])): ?>
                        <?php foreach ($it['modifiers'] as $m): ?>
                            <div>+ <?= htmlspecialchars($m['modifier_name']) ?> (<?= formatCurrency($m['price']) ?>)</div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td align="center"><?= $it['quantity'] ?></td>
                <td align="right"><?= number_format($it['unit_price'], 2) ?></td>
                <td align="right"><?= number_format($it['subtotal'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="dashed-line"></div>

<table>
    <tr>
        <td>Subtotal:</td>
        <td align="right"><?= formatCurrency($order['subtotal']) ?></td>
    </tr>
    <?php if ($order['discount_amount'] > 0): ?>
        <tr>
            <td>Discount:</td>
            <td align="right">-<?= formatCurrency($order['discount_amount']) ?></td>
        </tr>
    <?php endif; ?>
    <?php if ($order['tax_amount'] > 0): ?>
        <tr>
            <td>Tax (<?= $order['tax_percent'] ?>%):</td>
            <td align="right"><?= formatCurrency($order['tax_amount']) ?></td>
        </tr>
    <?php endif; ?>
    <?php if ($order['service_charge_amount'] > 0): ?>
        <tr>
            <td>Service Charge (<?= $order['service_charge_percent'] ?>%):</td>
            <td align="right"><?= formatCurrency($order['service_charge_amount']) ?></td>
        </tr>
    <?php endif; ?>
    <?php if ($order['delivery_fee'] > 0): ?>
        <tr>
            <td>Delivery Fee:</td>
            <td align="right"><?= formatCurrency($order['delivery_fee']) ?></td>
        </tr>
    <?php endif; ?>
    <tr class="fw-bold" style="font-size: 15px;">
        <td>TOTAL:</td>
        <td align="right"><?= formatCurrency($order['grand_total']) ?></td>
    </tr>
</table>

<div class="dashed-line"></div>

<!-- Payment Breakdown -->
<table>
    <?php foreach ($payments as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['method_name']) ?>:</td>
            <td align="right"><?= formatCurrency($p['amount']) ?></td>
        </tr>
        <?php if ($p['tendered_amount'] > $p['amount']): ?>
            <tr>
                <td>Tendered:</td>
                <td align="right"><?= formatCurrency($p['tendered_amount']) ?></td>
            </tr>
            <tr>
                <td>Change Returned:</td>
                <td align="right"><?= formatCurrency($p['change_returned']) ?></td>
            </tr>
        <?php endif; ?>
    <?php endforeach; ?>
</table>

<div class="double-line"></div>

<div class="text-center" style="font-size: 11px;">
    <div><?= nl2br(htmlspecialchars($order['receipt_footer'] ?? 'Thank you for your visit!')) ?></div>
    <div style="margin-top: 4px;">Software by Spice & Savor POS</div>
</div>

</body>
</html>
