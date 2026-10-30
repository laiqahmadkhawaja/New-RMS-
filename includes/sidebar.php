<?php
// includes/sidebar.php - Navigation Sidebar
require_once __DIR__ . '/auth.php';

$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));

function isActive($patterns): string {
    global $currentScript;
    if (!is_array($patterns)) {
        $patterns = [$patterns];
    }
    foreach ($patterns as $p) {
        if (strpos($currentScript, $p) !== false) {
            return 'active';
        }
    }
    return '';
}
?>
<nav id="sidebar" class="d-flex flex-column flex-shrink-0 text-white">
    <!-- Brand Logo / Header -->
    <div class="d-flex align-items-center gap-2 p-3 border-bottom border-secondary border-opacity-25 flex-shrink-0">
        <div class="bg-danger text-white p-2 rounded-3 lh-1">
            <i class="bi bi-fire fs-5"></i>
        </div>
        <div class="lh-1 overflow-hidden">
            <div class="fw-bold text-white text-truncate" style="font-size: 0.95rem;"><?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></div>
            <small class="text-secondary" style="font-size: 0.72rem;">Operations & ERP</small>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="flex-grow-1 overflow-y-auto py-2 sidebar-nav">
        <ul class="nav nav-pills flex-column mb-auto">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>dashboard.php" class="nav-link <?= isActive(['dashboard.php', 'Restaurant/index.php']) ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>

            <!-- Section: Restaurant Operations -->
            <div class="sidebar-heading">Operations</div>

            <?php if (userHasPermission('pos.view')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/pos/index.php" class="nav-link <?= isActive('modules/pos/') ?>">
                        <i class="bi bi-calculator"></i> POS Terminal
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('orders.view')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/orders/index.php" class="nav-link <?= isActive('modules/orders/') ?>">
                        <i class="bi bi-receipt"></i> Orders & Invoices
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('tables.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/tables/index.php" class="nav-link <?= isActive('modules/tables/') ?>">
                        <i class="bi bi-grid-fill"></i> Table Management
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('kitchen.kds')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="nav-link <?= isActive('modules/kitchen/') ?>">
                        <i class="bi bi-tv"></i> Kitchen Display (KDS)
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('delivery.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/delivery/index.php" class="nav-link <?= isActive('modules/delivery/') ?>">
                        <i class="bi bi-bicycle"></i> Delivery & Riders
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('reservations.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/reservations/index.php" class="nav-link <?= isActive('modules/reservations/') ?>">
                        <i class="bi bi-calendar-check"></i> Bookings & Waiting
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: Menu & Recipes -->
            <div class="sidebar-heading">Menu & Recipes</div>

            <?php if (userHasPermission('menu.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/menu/index.php" class="nav-link <?= isActive('modules/menu/') ?>">
                        <i class="bi bi-menu-button-wide"></i> Menu & Variants
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('recipes.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/recipes/index.php" class="nav-link <?= isActive('modules/recipes/') ?>">
                        <i class="bi bi-journals"></i> Recipes & Costing
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: Inventory & Purchasing -->
            <div class="sidebar-heading">Inventory & Supply</div>

            <?php if (userHasPermission('inventory.view')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/inventory/index.php" class="nav-link <?= isActive('modules/inventory/index.php') ?>">
                        <i class="bi bi-boxes"></i> Ingredients Stock
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/inventory/ledger.php" class="nav-link <?= isActive('modules/inventory/ledger.php') ?>">
                        <i class="bi bi-journal-text"></i> Stock Movement Ledger
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/inventory/wastage.php" class="nav-link <?= isActive('modules/inventory/wastage.php') ?>">
                        <i class="bi bi-trash3"></i> Wastage Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/inventory/transfers.php" class="nav-link <?= isActive('modules/inventory/transfers.php') ?>">
                        <i class="bi bi-arrow-left-right"></i> Branch Transfers
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('purchasing.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/purchasing/index.php" class="nav-link <?= isActive('modules/purchasing/') ?>">
                        <i class="bi bi-truck"></i> Purchases & Suppliers
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: Customers & Finance -->
            <div class="sidebar-heading">Finance & CRM</div>

            <?php if (userHasPermission('customers.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/customers/index.php" class="nav-link <?= isActive('modules/customers/') ?>">
                        <i class="bi bi-people"></i> Customers & Loyalty
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('expenses.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/expenses/index.php" class="nav-link <?= isActive('modules/expenses/') ?>">
                        <i class="bi bi-cash-coin"></i> Operating Expenses
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('shifts.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/shifts/index.php" class="nav-link <?= isActive('modules/shifts/') ?>">
                        <i class="bi bi-wallet2"></i> Cashier Registers & Shifts
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('accounting.view')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/accounting/index.php" class="nav-link <?= isActive(['modules/accounting/index.php', 'modules/accounting/journal_create.php', 'modules/accounting/ledger.php']) ?>">
                        <i class="bi bi-bank"></i> Double-Entry Accounting
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/accounting/reports.php" class="nav-link <?= isActive('modules/accounting/reports.php') ?>">
                        <i class="bi bi-file-earmark-bar-graph"></i> Financial Statements
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: Staff & Shifts -->
            <div class="sidebar-heading">Workforce</div>

            <?php if (userHasPermission('employees.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/employees/index.php" class="nav-link <?= isActive('modules/employees/index.php') ?>">
                        <i class="bi bi-person-badge"></i> Staff Directory
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/employees/attendance.php" class="nav-link <?= isActive('modules/employees/attendance.php') ?>">
                        <i class="bi bi-clock-history"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/employees/payroll.php" class="nav-link <?= isActive('modules/employees/payroll.php') ?>">
                        <i class="bi bi-cash"></i> Payroll & Wages
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: Reports -->
            <div class="sidebar-heading">Analytics</div>

            <?php if (userHasPermission('reports.view')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/reports/index.php" class="nav-link <?= isActive('modules/reports/') ?>">
                        <i class="bi bi-graph-up-arrow"></i> Sales & Profit Reports
                    </a>
                </li>
            <?php endif; ?>

            <!-- Section: System & Settings -->
            <div class="sidebar-heading">Administration</div>

            <?php if (userHasPermission('settings.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/branches/index.php" class="nav-link <?= isActive('modules/branches/') ?>">
                        <i class="bi bi-buildings"></i> Branches
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/settings/index.php" class="nav-link <?= isActive('modules/settings/') ?>">
                        <i class="bi bi-sliders"></i> System Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/printing/index.php" class="nav-link <?= isActive('modules/printing/') ?>">
                        <i class="bi bi-printer"></i> Printers & Hardware
                    </a>
                </li>
            <?php endif; ?>

            <?php if (userHasPermission('users.manage')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/users/index.php" class="nav-link <?= isActive('modules/users/') ?>">
                        <i class="bi bi-shield-lock"></i> Users & RBAC Roles
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/audit/index.php" class="nav-link <?= isActive('modules/audit/') ?>">
                        <i class="bi bi-activity"></i> Audit Trails
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/backup/index.php" class="nav-link <?= isActive('modules/backup/') ?>">
                        <i class="bi bi-database-down"></i> Backup & Recovery
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- User Mini Profile Footer -->
    <div class="p-3 border-top border-secondary border-opacity-25 small text-secondary flex-shrink-0">
        <div>RMS v<?= APP_VERSION ?></div>
    </div>
</nav>
