<?php
// dashboard.php - Executive Operations & Financial Dashboard
$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$branchId = currentBranchId();

// Filter date range (default to today)
$filter = get('period', 'today');
$startDate = date('Y-m-d');
$endDate = date('Y-m-d');

if ($filter === 'yesterday') {
    $startDate = date('Y-m-d', strtotime('-1 day'));
    $endDate = $startDate;
} elseif ($filter === 'week') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
} elseif ($filter === 'month') {
    $startDate = date('Y-m-01');
} elseif ($filter === 'custom') {
    $startDate = get('start_date', date('Y-m-d'));
    $endDate = get('end_date', date('Y-m-d'));
}

// 1. Sales Metrics
$salesQuery = "
    SELECT 
        COUNT(id) as total_orders,
        COALESCE(SUM(grand_total), 0) as gross_sales,
        COALESCE(SUM(paid_amount), 0) as total_paid,
        COALESCE(SUM(discount_amount), 0) as total_discounts,
        COALESCE(SUM(tax_amount), 0) as total_tax,
        COALESCE(SUM(service_charge_amount), 0) as total_service,
        COALESCE(SUM(delivery_fee), 0) as total_delivery,
        COALESCE(AVG(grand_total), 0) as avg_order_value,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_orders,
        SUM(CASE WHEN kitchen_status = 'preparing' THEN 1 ELSE 0 END) as in_prep_orders,
        SUM(CASE WHEN order_type_id = 3 AND delivery_status != 'delivered' THEN 1 ELSE 0 END) as pending_delivery
    FROM orders 
    WHERE branch_id = ? AND DATE(created_at) BETWEEN ? AND ?
";
$stmt = $db->prepare($salesQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$sales = $stmt->fetch();

// 2. Refunds
$refundQuery = "SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE branch_id = ? AND DATE(created_at) BETWEEN ? AND ?";
$stmt = $db->prepare($refundQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$totalRefunds = (float)$stmt->fetchColumn();

// 3. Payments breakdown (Cash vs Card/Digital)
$payQuery = "
    SELECT pm.code, COALESCE(SUM(p.amount), 0) as amt 
    FROM payments p 
    JOIN payment_methods pm ON p.payment_method_id = pm.id 
    WHERE p.branch_id = ? AND DATE(p.created_at) BETWEEN ? AND ? 
    GROUP BY pm.code
";
$stmt = $db->prepare($payQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$paymentMethodsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$cashReceived = (float)($paymentMethodsData['CASH'] ?? 0);
$cardDigitalReceived = (float)($paymentMethodsData['CARD'] ?? 0) + (float)($paymentMethodsData['WALLET'] ?? 0);

// 4. Expenses
$expQuery = "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE branch_id = ? AND expense_date BETWEEN ? AND ?";
$stmt = $db->prepare($expQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$totalExpenses = (float)$stmt->fetchColumn();

// 5. Estimated COGS from Stock Movements
$cogsQuery = "
    SELECT COALESCE(SUM(total_cost), 0) 
    FROM stock_movements 
    WHERE branch_id = ? AND movement_type = 'sale_consumption' AND DATE(created_at) BETWEEN ? AND ?
";
$stmt = $db->prepare($cogsQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$totalCogs = (float)$stmt->fetchColumn();

// 6. Net Sales & Gross Profit Calculation
$netSales = (float)$sales['gross_sales'] - $totalRefunds;
$grossProfit = $netSales - $totalCogs;

// 7. Inventory alerts
$lowStock = (int)$db->query("SELECT count(*) FROM ingredients WHERE current_stock > 0 AND current_stock <= min_stock_alert AND is_active = 1")->fetchColumn();
$outOfStock = (int)$db->query("SELECT count(*) FROM ingredients WHERE current_stock <= 0 AND is_active = 1")->fetchColumn();

// 8. Receivables & Payables
$arBalance = (float)$db->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE current_balance > 0")->fetchColumn();
$apBalance = (float)$db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE current_balance > 0")->fetchColumn();

// 9. Best-selling items for chart
$topItemsQuery = "
    SELECT m.name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_rev 
    FROM order_items oi 
    JOIN menu_items m ON oi.menu_item_id = m.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.branch_id = ? AND DATE(o.created_at) BETWEEN ? AND ? 
    GROUP BY m.id, m.name 
    ORDER BY total_qty DESC LIMIT 5
";
$stmt = $db->prepare($topItemsQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$topItems = $stmt->fetchAll();

// 10. Sales by order type
$orderTypeQuery = "
    SELECT ot.name, COUNT(o.id) as orders_count, COALESCE(SUM(o.grand_total), 0) as total_amount 
    FROM order_types ot 
    LEFT JOIN orders o ON ot.id = o.order_type_id AND o.branch_id = ? AND DATE(o.created_at) BETWEEN ? AND ? 
    GROUP BY ot.id, ot.name
";
$stmt = $db->prepare($orderTypeQuery);
$stmt->execute([$branchId, $startDate, $endDate]);
$orderTypesBreakdown = $stmt->fetchAll();
?>

<!-- Dashboard Header & Period Filter -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Operational & Financial Dashboard</h3>
        <p class="text-muted small mb-0">Live real-time metrics for <?= htmlspecialchars($currentUser['branch_name'] ?? currentBranchName()) ?></p>
    </div>

    <!-- Filter Buttons -->
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group shadow-sm" role="group">
            <a href="?period=today" class="btn btn-sm <?= $filter === 'today' ? 'btn-danger' : 'btn-outline-secondary' ?>">Today</a>
            <a href="?period=yesterday" class="btn btn-sm <?= $filter === 'yesterday' ? 'btn-danger' : 'btn-outline-secondary' ?>">Yesterday</a>
            <a href="?period=week" class="btn btn-sm <?= $filter === 'week' ? 'btn-danger' : 'btn-outline-secondary' ?>">This Week</a>
            <a href="?period=month" class="btn btn-sm <?= $filter === 'month' ? 'btn-danger' : 'btn-outline-secondary' ?>">This Month</a>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts (PRD 3.4) -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3 col-xl-auto">
        <a href="<?= BASE_URL ?>modules/pos/index.php" class="btn btn-danger w-100 py-2 shadow-sm fw-semibold">
            <i class="bi bi-cart-plus me-1"></i> Open POS
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl-auto">
        <a href="<?= BASE_URL ?>modules/pos/index.php?type=takeaway" class="btn btn-dark w-100 py-2 shadow-sm fw-semibold">
            <i class="bi bi-bag-check me-1"></i> New Takeaway
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl-auto">
        <a href="<?= BASE_URL ?>modules/pos/index.php?type=delivery" class="btn btn-primary w-100 py-2 shadow-sm fw-semibold">
            <i class="bi bi-bicycle me-1"></i> New Delivery
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl-auto">
        <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="btn btn-warning text-dark w-100 py-2 shadow-sm fw-semibold">
            <i class="bi bi-tv me-1"></i> Kitchen KDS
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl-auto">
        <a href="<?= BASE_URL ?>modules/purchasing/create.php" class="btn btn-outline-secondary w-100 py-2 shadow-sm fw-semibold">
            <i class="bi bi-box-arrow-in-down me-1"></i> Receive Stock
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl-auto">
        <a href="<?= BASE_URL ?>modules/expenses/create.php" class="btn btn-outline-secondary w-100 py-2 shadow-sm fw-semibold">
            <i class="bi bi-cash-stack me-1"></i> Add Expense
        </a>
    </div>
</div>

<!-- Primary KPI Cards Grid (PRD 3.1) -->
<div class="row g-3 mb-4">
    <!-- Total Gross Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger-subtle text-danger">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Gross Sales</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($sales['gross_sales']) ?></h4>
                <small class="text-muted"><?= (int)$sales['total_orders'] ?> Total Orders</small>
            </div>
        </div>
    </div>

    <!-- Net Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-success-subtle text-success">
                <i class="bi bi-graph-up"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Net Sales (Less Returns)</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($netSales) ?></h4>
                <small class="text-danger">Refunds: <?= formatCurrency($totalRefunds) ?></small>
            </div>
        </div>
    </div>

    <!-- Est. Gross Profit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary-subtle text-primary">
                <i class="bi bi-pie-chart-fill"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Est. Gross Profit</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($grossProfit) ?></h4>
                <small class="text-muted">Food COGS: <?= formatCurrency($totalCogs) ?></small>
            </div>
        </div>
    </div>

    <!-- Average Order Value (AOV) -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-info-subtle text-info">
                <i class="bi bi-cart4"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Average Order Value</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($sales['avg_order_value']) ?></h4>
                <small class="text-muted">Discounts: <?= formatCurrency($sales['total_discounts']) ?></small>
            </div>
        </div>
    </div>

    <!-- Cash Received -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-success-subtle text-success">
                <i class="bi bi-cash"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Cash Collections</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($cashReceived) ?></h4>
                <small class="text-muted">In Physical Registers</small>
            </div>
        </div>
    </div>

    <!-- Card & Mobile Payments -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-dark-subtle text-dark">
                <i class="bi bi-credit-card-2-front"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Digital / Card Payments</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($cardDigitalReceived) ?></h4>
                <small class="text-muted">POS & Mobile Wallets</small>
            </div>
        </div>
    </div>

    <!-- Operating Expenses -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning-subtle text-warning">
                <i class="bi bi-wallet"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Operating Expenses</div>
                <h4 class="fw-bold mb-0 text-dark"><?= formatCurrency($totalExpenses) ?></h4>
                <small class="text-muted">Daily Direct Costs</small>
            </div>
        </div>
    </div>

    <!-- Low & Out of Stock -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Stock Warning</div>
                <h4 class="fw-bold mb-0 text-danger"><?= $lowStock + $outOfStock ?> Items</h4>
                <small class="text-muted"><?= $lowStock ?> Low / <?= $outOfStock ?> Out of stock</small>
            </div>
        </div>
    </div>
</div>

<!-- Operational Status Badges -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-white shadow-sm d-flex flex-row align-items-center justify-content-between">
            <div>
                <small class="text-muted">Orders In Kitchen</small>
                <h5 class="fw-bold mb-0 text-warning"><?= (int)$sales['in_prep_orders'] ?></h5>
            </div>
            <i class="bi bi-fire fs-3 text-warning"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-white shadow-sm d-flex flex-row align-items-center justify-content-between">
            <div>
                <small class="text-muted">Pending Delivery</small>
                <h5 class="fw-bold mb-0 text-primary"><?= (int)$sales['pending_delivery'] ?></h5>
            </div>
            <i class="bi bi-bicycle fs-3 text-primary"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-white shadow-sm d-flex flex-row align-items-center justify-content-between">
            <div>
                <small class="text-muted">Unpaid Bills</small>
                <h5 class="fw-bold mb-0 text-danger"><?= (int)$sales['unpaid_orders'] ?></h5>
            </div>
            <i class="bi bi-hourglass-split fs-3 text-danger"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-white shadow-sm d-flex flex-row align-items-center justify-content-between">
            <div>
                <small class="text-muted">Cancelled Orders</small>
                <h5 class="fw-bold mb-0 text-secondary"><?= (int)$sales['cancelled_orders'] ?></h5>
            </div>
            <i class="bi bi-x-circle fs-3 text-secondary"></i>
        </div>
    </div>
</div>

<!-- Charts Section (PRD 3.3) -->
<div class="row g-4 mb-4">
    <!-- Chart: Sales by Order Type -->
    <div class="col-12 col-lg-6">
        <div class="card-rms p-3">
            <h5 class="fw-bold mb-3">Revenue by Order Channel</h5>
            <div style="height: 280px;">
                <canvas id="orderTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart: Top Selling Dishes -->
    <div class="col-12 col-lg-6">
        <div class="card-rms p-3">
            <h5 class="fw-bold mb-3">Top Selling Menu Items</h5>
            <div style="height: 280px;">
                <canvas id="topItemsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Active Tables & Low Stock Warnings -->
<div class="row g-4">
    <!-- Live Occupied Tables Overview -->
    <div class="col-12 col-lg-7">
        <div class="card-rms p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Dining Tables Status</h5>
                <a href="<?= BASE_URL ?>modules/tables/index.php" class="btn btn-outline-danger btn-sm">Manage Tables</a>
            </div>
            <?php
            $tables = $db->query("
                SELECT t.*, da.name as area_name, o.order_number, o.grand_total as order_amt 
                FROM restaurant_tables t 
                JOIN dining_areas da ON t.dining_area_id = da.id 
                LEFT JOIN orders o ON t.current_order_id = o.id 
                WHERE t.branch_id = $branchId AND t.is_active = 1 
                ORDER BY t.sort_order ASC LIMIT 8
            ")->fetchAll();
            ?>
            <div class="row g-2">
                <?php foreach ($tables as $t): ?>
                    <div class="col-6 col-sm-3">
                        <div class="table-card table-<?= $t['status'] ?> p-2 text-center">
                            <span class="badge bg-secondary mb-1"><?= htmlspecialchars($t['area_name']) ?></span>
                            <div class="fw-bold fs-5"><?= htmlspecialchars($t['table_number']) ?></div>
                            <small class="d-block text-capitalize"><?= str_replace('_', ' ', $t['status']) ?></small>
                            <?php if ($t['order_amt']): ?>
                                <small class="text-danger fw-bold"><?= formatCurrency($t['order_amt']) ?></small>
                            <?php else: ?>
                                <small class="text-muted"><?= $t['capacity'] ?> Seats</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Urgent Low Stock Alerts -->
    <div class="col-12 col-lg-5">
        <div class="card-rms p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-bell-fill me-1"></i> Stock Reorder Alerts</h5>
                <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">Full Inventory</a>
            </div>
            <?php
            $alertItems = $db->query("
                SELECT i.*, u.code as unit_code 
                FROM ingredients i 
                JOIN units u ON i.unit_id = u.id 
                WHERE i.current_stock <= i.min_stock_alert AND i.is_active = 1 
                ORDER BY i.current_stock ASC LIMIT 5
            ")->fetchAll();
            ?>
            <?php if (empty($alertItems)): ?>
                <div class="alert alert-success py-3 mb-0 small">
                    <i class="bi bi-check-circle-fill me-1"></i> All ingredients are currently above minimum threshold.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>Ingredient</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertItems as $ai): ?>
                                <tr>
                                    <td class="fw-semibold text-truncate"><?= htmlspecialchars($ai['name']) ?></td>
                                    <td class="text-end text-danger fw-bold"><?= (float)$ai['current_stock'] ?> <?= htmlspecialchars($ai['unit_code']) ?></td>
                                    <td class="text-end text-muted"><?= (float)$ai['min_stock_alert'] ?> <?= htmlspecialchars($ai['unit_code']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Order Type Doughnut Chart
    const otCtx = document.getElementById('orderTypeChart').getContext('2d');
    const otLabels = <?= json_encode(array_column($orderTypesBreakdown, 'name')) ?>;
    const otData = <?= json_encode(array_column($orderTypesBreakdown, 'total_amount')) ?>;

    new Chart(otCtx, {
        type: 'doughnut',
        data: {
            labels: otLabels,
            datasets: [{
                data: otData.length ? otData : [1],
                backgroundColor: ['#e63946', '#457b9d', '#1d2d44', '#f4a261', '#2a9d8f']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 2. Top Items Bar Chart
    const tiCtx = document.getElementById('topItemsChart').getContext('2d');
    const tiLabels = <?= json_encode(array_column($topItems, 'name')) ?>;
    const tiData = <?= json_encode(array_column($topItems, 'total_qty')) ?>;

    new Chart(tiCtx, {
        type: 'bar',
        data: {
            labels: tiLabels.length ? tiLabels : ['No sales yet'],
            datasets: [{
                label: 'Quantity Sold',
                data: tiData.length ? tiData : [0],
                backgroundColor: '#e63946',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
