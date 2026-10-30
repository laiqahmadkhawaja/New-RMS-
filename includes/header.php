<?php
// includes/header.php - Top Navigation & Responsive Shell
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireAuth();
$currentUser = currentUser();
$currentBranchId = currentBranchId();
$activeShift = getActiveCashShift($currentBranchId);

// Check low stock count for quick alert pill
$db = getDB();
$lowStockCount = (int)$db->query("SELECT count(*) FROM ingredients WHERE current_stock <= min_stock_alert AND is_active = 1")->fetchColumn();
$activeOrdersCount = (int)$db->query("SELECT count(*) FROM orders WHERE order_status NOT IN ('completed', 'cancelled') AND branch_id = $currentBranchId")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Restaurant Management System') ?> - <?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></title>
    
    <!-- Local CSS dependencies (Localhost First, No CDN required) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
    
    <!-- Local JS dependencies -->
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/chart.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
</head>
<body class="app-body">
<div class="d-flex app-wrapper">
    <!-- Collapsible Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div id="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-light btn-sm border" id="sidebarToggle" title="Toggle Sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-md-flex align-items-center gap-2">
                    <span class="badge bg-danger-subtle text-danger fw-semibold px-2 py-1">
                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($currentUser['branch_name'] ?? 'Main') ?>
                    </span>
                    <?php if ($activeShift): ?>
                        <a href="<?= BASE_URL ?>modules/shifts/index.php" class="text-decoration-none" title="Manage Active Shift">
                            <span class="badge bg-success-subtle text-success fw-semibold px-2 py-1">
                                <i class="bi bi-cash-stack me-1"></i>Shift: <?= htmlspecialchars($activeShift['shift_number']) ?>
                            </span>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>modules/shifts/index.php?open=1" class="text-decoration-none" title="Click to Open Cash Register Shift">
                            <span class="badge bg-warning-subtle text-warning fw-semibold px-2 py-1">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>No Active Shift (Click to Open)
                            </span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Navbar Actions -->
            <div class="d-flex align-items-center gap-2">
                <?php if (userHasPermission('pos.view')): ?>
                    <a href="<?= BASE_URL ?>modules/pos/index.php" class="btn btn-danger btn-sm fw-semibold shadow-sm px-3">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> POS Terminal
                    </a>
                <?php endif; ?>

                <?php if (userHasPermission('kitchen.kds')): ?>
                    <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="btn btn-outline-dark btn-sm fw-semibold">
                        <i class="bi bi-tv me-1"></i> KDS
                        <?php if ($activeOrdersCount > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $activeOrdersCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <!-- Low Stock Alert Icon (Inline pill badge prevents overlapping the User Dropdown button) -->
                <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-light btn-sm border d-flex align-items-center gap-1" title="Low Stock Alerts">
                    <i class="bi bi-bell fs-6"></i>
                    <?php if ($lowStockCount > 0): ?>
                        <span class="badge bg-danger rounded-pill px-2" style="font-size:0.7rem;">
                            <?= $lowStockCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5 text-primary"></i>
                        <div class="text-start d-none d-sm-block lh-1">
                            <div class="fw-semibold small"><?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?></div>
                            <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($currentUser['role_display'] ?? '') ?></small>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><h6 class="dropdown-header">Logged in as <?= htmlspecialchars($currentUser['username'] ?? '') ?></h6></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/shifts/index.php"><i class="bi bi-wallet2 me-2"></i> Register Shifts</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/settings/index.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Message Notification Banner -->
        <?php $flashHtml = renderFlash(); if (!empty($flashHtml)): ?>
            <div class="container-fluid px-4 pt-3 mb-n2">
                <?= $flashHtml ?>
            </div>
        <?php endif; ?>

        <!-- Main Page Dynamic Body -->
        <main class="flex-grow-1 p-4">
