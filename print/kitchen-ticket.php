<?php
// print/kitchen-ticket.php - Kitchen Order Ticket (KOT)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$ticketId = (int)($_GET['ticket_id'] ?? 0);
$orderId = (int)($_GET['order_id'] ?? 0);
$stationId = (int)($_GET['station_id'] ?? 0);
$db = getDB();

if ($ticketId) {
    $stmt = $db->prepare("
        SELECT kt.*, o.order_number, o.created_at as order_time, ot.name as order_type_name, 
               t.table_number, ks.name as station_name 
        FROM kitchen_tickets kt 
        JOIN orders o ON kt.order_id = o.id 
        JOIN order_types ot ON o.order_type_id = ot.id 
        LEFT JOIN restaurant_tables t ON o.table_id = t.id 
        JOIN kitchen_stations ks ON kt.kitchen_station_id = ks.id 
        WHERE kt.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) die("Ticket not found.");
    $orderId = (int)$ticket['order_id'];
    $stationId = (int)$ticket['kitchen_station_id'];
} else {
    $stmt = $db->prepare("
        SELECT o.*, ot.name as order_type_name, t.table_number 
        FROM orders o 
        JOIN order_types ot ON o.order_type_id = ot.id 
        LEFT JOIN restaurant_tables t ON o.table_id = t.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $ticket = $stmt->fetch();
    $ticket['ticket_number'] = 'KOT-' . $ticket['order_number'];
    $ticket['station_name'] = 'All Stations';
}

$items = $db->query("
    SELECT oi.* 
    FROM order_items oi 
    WHERE oi.order_id = $orderId 
    " . ($stationId ? "AND (oi.kitchen_station_id = $stationId OR oi.kitchen_station_id IS NULL)" : "") . "
")->fetchAll();

foreach ($items as &$it) {
    $it['modifiers'] = $db->query("SELECT * FROM order_item_modifiers WHERE order_item_id = " . $it['id'])->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KOT #<?= htmlspecialchars($ticket['ticket_number']) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            color: #000;
            background: #fff;
            width: 76mm;
            margin: 0;
            padding: 8px;
        }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .dashed-line { border-bottom: 2px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 0; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="no-print" style="margin-bottom: 8px; text-align: center;">
    <button onclick="window.print()" style="padding: 6px 16px; cursor: pointer;">🖨️ Print KOT</button>
</div>

<div class="text-center">
    <h2 style="margin: 0;">*** KITCHEN ORDER ***</h2>
    <div class="fw-bold" style="font-size: 16px;"><?= htmlspecialchars($ticket['station_name'] ?? 'STATION') ?></div>
</div>

<div class="dashed-line"></div>

<div>
    <div style="font-size: 16px; font-weight: bold;">
        Order: #<?= htmlspecialchars($ticket['order_number']) ?>
    </div>
    <div style="font-size: 18px; font-weight: bold; margin: 4px 0;">
        <?= htmlspecialchars($ticket['order_type_name']) ?>
        <?= !empty($ticket['table_number']) ? ' | TABLE ' . htmlspecialchars($ticket['table_number']) : '' ?>
    </div>
    <div>Time: <?= date('h:i:s A') ?></div>
</div>

<div class="dashed-line"></div>

<table>
    <thead>
        <tr>
            <th align="left">QTY</th>
            <th align="left">ITEM DESCRIPTION</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td valign="top" style="font-size: 18px; font-weight: bold; width: 35px;"><?= $it['quantity'] ?>x</td>
                <td valign="top">
                    <div style="font-size: 16px; font-weight: bold;">
                        <?= htmlspecialchars($it['item_name']) ?>
                        <?= $it['variant_name'] ? ' (' . htmlspecialchars($it['variant_name']) . ')' : '' ?>
                    </div>
                    <?php if (!empty($it['modifiers'])): ?>
                        <?php foreach ($it['modifiers'] as $m): ?>
                            <div style="font-size: 13px; font-weight: bold;">>> <?= htmlspecialchars($m['modifier_name']) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($it['special_instructions'])): ?>
                        <div style="font-size: 12px; font-style: italic; background: #eee; padding: 2px 4px; margin-top: 2px;">
                            NOTE: <?= htmlspecialchars($it['special_instructions']) ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr><td colspan="2"><div style="border-bottom: 1px dotted #888; margin: 4px 0;"></div></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="dashed-line"></div>
<div class="text-center" style="font-size: 12px;">-- END OF TICKET --</div>

</body>
</html>
