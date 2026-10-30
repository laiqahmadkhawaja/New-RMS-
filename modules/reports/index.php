<?php
// modules/reports/index.php - Business Analytics, Sales, Inventory & Profit Reports (PRD 20)
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('reports.view');

$db = getDB();
$branchId = currentBranchId();

$dateFrom = cleanInput(get('date_from', date('Y-m-01')));
$dateTo = cleanInput(get('date_to', date('Y-m-d')));
$reportTab = cleanInput(get('tab', 'sales_summary'));

// CSV Export trigger
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_' . $reportTab . '_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');

    if ($reportTab === 'sales_summary') {
        fputcsv($output, ['Order Number', 'Date', 'Channel', 'Customer', 'Subtotal', 'Discount', 'Tax', 'Service', 'Delivery', 'Total', 'Payment Status']);
        $exportData = $db->query("
            SELECT o.order_number, o.created_at, ot.name as channel, c.name as customer, 
                   o.subtotal, o.discount_amount, o.tax_amount, o.service_charge_amount, o.delivery_fee, o.grand_total, o.payment_status 
            FROM orders o 
            JOIN order_types ot ON o.order_type_id = ot.id 
            LEFT JOIN customers c ON o.customer_id = c.id 
            WHERE o.branch_id = $branchId AND DATE(o.created_at) BETWEEN '$dateFrom' AND '$dateTo'
        ")->fetchAll();
        foreach ($exportData as $row) {
            fputcsv($output, $row);
        }
    }
    exit;
}

// 1. Sales Summary metrics
$summary = $db->query("
    SELECT 
        COUNT(id) as total_orders,
        COALESCE(SUM(subtotal), 0) as gross_sales,
        COALESCE(SUM(discount_amount), 0) as total_discounts,
        COALESCE(SUM(tax_amount), 0) as total_tax,
        COALESCE(SUM(service_charge_amount), 0) as total_service,
        COALESCE(SUM(delivery_fee), 0) as total_delivery,
        COALESCE(SUM(grand_total), 0) as grand_total,
        COALESCE(SUM(paid_amount), 0) as total_collected 
    FROM orders 
    WHERE branch_id = $branchId AND DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND order_status != 'cancelled'
")->fetch();

// 2. Sales by Product
$productSales = $db->query("
    SELECT m.name as item_name, m.code, c.name as category_name,
           SUM(oi.quantity) as qty_sold,
           SUM(oi.subtotal) as total_revenue,
           m.cost_estimate,
           (SUM(oi.quantity) * m.cost_estimate) as estimated_cogs,
           (SUM(oi.subtotal) - (SUM(oi.quantity) * m.cost_estimate)) as gross_margin 
    FROM order_items oi 
    JOIN menu_items m ON oi.menu_item_id = m.id 
    JOIN menu_categories c ON m.category_id = c.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.branch_id = $branchId AND DATE(o.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND o.order_status != 'cancelled'
    GROUP BY m.id, m.name, m.code, c.name, m.cost_estimate 
    ORDER BY total_revenue DESC
")->fetchAll();

// 3. Category revenue
$categorySales = $db->query("
    SELECT c.name as category_name, 
           COUNT(DISTINCT o.id) as orders_count, 
           SUM(oi.quantity) as total_items, 
           SUM(oi.subtotal) as category_revenue 
    FROM order_items oi 
    JOIN menu_items m ON oi.menu_item_id = m.id 
    JOIN menu_categories c ON m.category_id = c.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.branch_id = $branchId AND DATE(o.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND o.order_status != 'cancelled'
    GROUP BY c.id, c.name 
    ORDER BY category_revenue DESC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow text-danger me-2"></i> Sales & Profit Analytics</h3>
        <p class="text-muted small mb-0">Detailed operational reports, product profitability, and food cost analysis</p>
    </div>

    <!-- Export & Print Actions -->
    <div class="d-flex gap-2">
        <a href="?tab=<?= $reportTab ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&export_csv=1" class="btn btn-outline-success btn-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm fw-semibold">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-rms p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($reportTab) ?>">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="col-12 col-md-4">
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold">
                <i class="bi bi-funnel"></i> Apply Date Range
            </button>
        </div>
    </form>
</div>

<!-- Report Tabs -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $reportTab === 'sales_summary' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=sales_summary&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
            Sales Performance Summary
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportTab === 'by_product' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=by_product&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
            Product Profitability & Margins
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportTab === 'by_category' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=by_category&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
            Category Revenue Breakdown
        </a>
    </li>
</ul>

<!-- ================= 1. SALES SUMMARY ================= -->
<?php if ($reportTab === 'sales_summary'): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm">
                <small class="text-muted fw-bold text-uppercase">Gross Menu Sales</small>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($summary['gross_sales']) ?></h4>
                <small class="text-muted"><?= $summary['total_orders'] ?> Orders Ring</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm">
                <small class="text-muted fw-bold text-uppercase">Discounts Granted</small>
                <h4 class="fw-bold mb-0 text-danger">-<?= formatCurrency($summary['total_discounts']) ?></h4>
                <small class="text-muted">Item & whole-bill promos</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm">
                <small class="text-muted fw-bold text-uppercase">Taxes & Charges</small>
                <h4 class="fw-bold mb-0 text-primary"><?= formatCurrency((float)$summary['total_tax'] + (float)$summary['total_service'] + (float)$summary['total_delivery']) ?></h4>
                <small class="text-muted">Tax, Service & Delivery</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm">
                <small class="text-muted fw-bold text-uppercase">Net Cash Collected</small>
                <h4 class="fw-bold mb-0 text-success"><?= formatCurrency($summary['total_collected']) ?></h4>
                <small class="text-muted">Received in tills & banks</small>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ================= 2. PRODUCT PROFITABILITY ================= -->
<?php if ($reportTab === 'by_product'): ?>
    <div class="card-rms overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th>Dish / Menu Item</th>
                        <th>Category</th>
                        <th class="text-center">Qty Sold</th>
                        <th class="text-end">Revenue Generated</th>
                        <th class="text-end">Ingredient COGS</th>
                        <th class="text-end">Gross Profit</th>
                        <th class="text-end">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productSales)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No sales recorded for this date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($productSales as $ps): 
                            $rev = (float)$ps['total_revenue'];
                            $margin = (float)$ps['gross_margin'];
                            $mPercent = $rev > 0 ? round(($margin / $rev) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($ps['item_name']) ?></span>
                                    <div class="small text-muted font-monospace"><?= htmlspecialchars($ps['code'] ?? '') ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ps['category_name']) ?></span></td>
                                <td class="text-center fw-bold fs-6"><?= $ps['qty_sold'] ?></td>
                                <td class="text-end fw-bold"><?= formatCurrency($ps['total_revenue']) ?></td>
                                <td class="text-end text-muted"><?= formatCurrency($ps['estimated_cogs']) ?></td>
                                <td class="text-end fw-bold text-success"><?= formatCurrency($margin) ?></td>
                                <td class="text-end">
                                    <span class="badge bg-success-subtle text-success fs-6"><?= $mPercent ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ================= 3. CATEGORY REVENUE ================= -->
<?php if ($reportTab === 'by_category'): ?>
    <div class="card-rms overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th>Category</th>
                        <th class="text-center">Orders Count</th>
                        <th class="text-center">Total Portions Sold</th>
                        <th class="text-end">Total Category Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorySales)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No category sales recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorySales as $cs): ?>
                            <tr>
                                <td class="fw-bold text-dark fs-6"><?= htmlspecialchars($cs['category_name']) ?></td>
                                <td class="text-center"><?= $cs['orders_count'] ?> orders</td>
                                <td class="text-center fw-bold"><?= $cs['total_items'] ?> items</td>
                                <td class="text-end fw-bold fs-6 text-danger"><?= formatCurrency($cs['category_revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
