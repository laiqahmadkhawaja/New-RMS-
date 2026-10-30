<?php
// modules/pos/index.php - Lightning-Fast Restaurant POS Terminal
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePermission('pos.view');

$pageTitle = 'POS Terminal';
$db = getDB();
$branchId = currentBranchId();

// Master data for POS
$orderTypes = $db->query("SELECT * FROM order_types WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$tables = $db->query("SELECT t.*, da.name as area_name FROM restaurant_tables t JOIN dining_areas da ON t.dining_area_id = da.id WHERE t.branch_id = $branchId AND t.is_active = 1 ORDER BY t.sort_order ASC")->fetchAll();
$customers = $db->query("SELECT id, name, phone, loyalty_points, credit_limit FROM customers WHERE is_active = 1 ORDER BY (id = 1) DESC, (name LIKE '%walk-in%') DESC, name ASC LIMIT 100")->fetchAll();
$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$deliveryZones = $db->query("SELECT * FROM delivery_zones WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
$riders = $db->query("SELECT * FROM riders WHERE branch_id = $branchId AND is_active = 1")->fetchAll();

$taxRate = (float)getSetting('default_tax_rate', 5.00);
$serviceCharge = (float)getSetting('default_service_charge', 2.50);

// Resolve initial order type from URL parameter (?type=takeaway, ?type=delivery, etc.)
$typeParam = trim($_GET['type'] ?? '');
$activeOrderType = null;
if (!empty($typeParam)) {
    foreach ($orderTypes as $ot) {
        if (strcasecmp($ot['code'], $typeParam) === 0 || strcasecmp($ot['name'], $typeParam) === 0) {
            $activeOrderType = $ot;
            break;
        }
    }
}
if (!$activeOrderType && !empty($orderTypes)) {
    $activeOrderType = $orderTypes[0]; // defaults to first active order type (Dine-in)
}
$selectedTypeId = (int)$activeOrderType['id'];
$selectedReqTable = (int)$activeOrderType['requires_table'];
$selectedReqDelivery = (int)$activeOrderType['requires_delivery'];
$selectedServiceCharge = $selectedReqTable ? (float)$serviceCharge : 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal - <?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
    <style>
        .pos-full-screen {
            display: flex;
            height: 100vh;
            overflow: hidden;
            background: #f1f5f9;
        }
        .pos-left {
            width: 220px;
            background: #ffffff;
            border-right: 1px solid var(--rms-border);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .pos-center {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: hidden;
        }
        .pos-right {
            width: 440px;
            background: #ffffff;
            border-left: 1px solid var(--rms-border);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .category-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.15s;
        }
        .category-item:hover, .category-item.active {
            background: #fee2e2;
            color: #dc2626;
            font-weight: 600;
            border-left: 4px solid #dc2626;
        }
        .cart-line {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem 0.65rem;
            margin-bottom: 0.5rem;
        }
        /* Payment method label buttons */
        .pay-method-label {
            font-size: 0.85rem;
            color: #374151;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 0.4rem 0.65rem;
            border-radius: 0.4rem;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }
        .pay-method-label:hover {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1d4ed8;
        }
        .pay-method-label.bg-success-subtle {
            background-color: #dcfce7 !important;
            border-color: #16a34a !important;
            color: #14532d !important;
        }
        .pay-method-label.bg-primary-subtle {
            background-color: #e0f2fe !important;
            border-color: #0284c7 !important;
            color: #0369a1 !important;
        }
    </style>
</head>
<body class="m-0 p-0">

<div class="pos-full-screen">
    <!-- 1. LEFT SECTION: CATEGORIES & QUICK ACTIONS (PRD 4.1) -->
    <div class="pos-left">
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
            <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline-secondary btn-sm" title="Back to Dashboard">
                <i class="bi bi-arrow-left"></i> Exit
            </a>
            <button class="btn btn-sm btn-outline-primary" onclick="POS.openHeldOrders()" title="Resume Held Orders">
                <i class="bi bi-clock-history"></i> Tabs
            </button>
        </div>

        <div class="p-2">
            <button class="category-item w-100 rounded text-start active" data-cat="all" onclick="POS.filterCategory('all', this)">
                <i class="bi bi-grid text-danger fs-5"></i> All Dishes
            </button>
            <div id="categoryContainer">
                <!-- Dynamically loaded categories -->
            </div>
        </div>
    </div>

    <!-- 2. CENTER SECTION: SEARCH & PRODUCT GRID -->
    <div class="pos-center">
        <!-- Top Search and Filter Bar -->
        <div class="p-3 bg-white border-bottom d-flex gap-3 align-items-center">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="posSearchInput" class="form-control border-start-0 ps-0" placeholder="Search menu items by name or code (Type here)..." onkeyup="POS.filterSearch(this.value)">
            </div>
            <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" onclick="POS.clearCart()">
                <i class="bi bi-trash3"></i> Clear
            </button>
        </div>

        <!-- Scrollable Product Cards Grid -->
        <div class="flex-grow-1 overflow-y-auto p-3">
            <div class="row g-3" id="posProductGrid">
                <!-- Dynamic Product Cards loaded via JS -->
            </div>
        </div>
    </div>

    <!-- 3. RIGHT SECTION: ACTIVE ORDER CART -->
    <div class="pos-right shadow-sm">
        <!-- Order Header & Channel Selector -->
        <div class="p-3 border-bottom bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0 text-dark" id="displayOrderNum">New Order</h5>
                <span class="badge bg-danger-subtle text-danger fw-semibold px-2 py-1" id="orderStatusBadge">Draft</span>
            </div>

            <!-- Order Types Pills -->
            <div class="btn-group w-100 mb-2" role="group">
                <?php foreach ($orderTypes as $ot): ?>
                    <?php $isTypeActive = ((int)$ot['id'] === $selectedTypeId); ?>
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger fw-semibold order-type-btn <?= $isTypeActive ? 'active' : '' ?>" 
                            data-id="<?= $ot['id'] ?>" 
                            data-code="<?= htmlspecialchars($ot['code']) ?>"
                            data-requires-table="<?= (int)$ot['requires_table'] ?>"
                            data-requires-delivery="<?= (int)$ot['requires_delivery'] ?>"
                            onclick="POS.setOrderType(<?= $ot['id'] ?>, <?= (int)$ot['requires_table'] ?>, <?= (int)$ot['requires_delivery'] ?>, this)">
                        <?= htmlspecialchars($ot['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Context Selectors (Table / Customer) -->
            <div class="row g-2">
                <div class="col-6 <?= $selectedReqTable ? '' : 'd-none' ?>" id="tableSelectWrapper">
                    <select id="posTableSelect" class="form-select form-select-sm" onchange="POS.onTableChange(this.value)">
                        <option value="">-- Select Table --</option>
                        <?php foreach ($tables as $tb): ?>
                            <option value="<?= $tb['id'] ?>" data-status="<?= $tb['status'] ?>">
                                <?= htmlspecialchars($tb['table_number']) ?> (<?= htmlspecialchars($tb['area_name']) ?>) <?= $tb['status'] === 'occupied' ? '🔴' : '🟢' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6" id="customerSelectWrapper">
                    <select id="posCustomerSelect" class="form-select form-select-sm" onchange="POS.onCustomerChange(this.value)">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-phone="<?= htmlspecialchars($c['phone']) ?>" data-credit-limit="<?= (float)$c['credit_limit'] ?>" <?= $c['id'] == 1 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?> <?= $c['id'] != 1 ? '(' . htmlspecialchars($c['phone']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Delivery Zone and Rider (Conditional) -->
            <div id="deliveryDetailsRow" class="row g-2 mt-1 <?= $selectedReqDelivery ? '' : 'd-none' ?>">
                <div class="col-6">
                    <select id="posDeliveryZoneSelect" class="form-select form-select-sm" onchange="POS.onDeliveryZoneChange(this)">
                        <option value="0" data-fee="0">-- Select Zone --</option>
                        <?php foreach ($deliveryZones as $dz): ?>
                            <option value="<?= $dz['id'] ?>" data-fee="<?= $dz['delivery_fee'] ?>"><?= htmlspecialchars($dz['name']) ?> (+Rs. <?= $dz['delivery_fee'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select id="posRiderSelect" class="form-select form-select-sm">
                        <?php foreach ($riders as $rd): ?>
                            <option value="<?= $rd['id'] ?>"><?= htmlspecialchars($rd['name']) ?> (<?= htmlspecialchars($rd['vehicle_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Order Items Scrollable Container -->
        <div class="cart-items-box" id="cartItemsContainer">
            <div class="text-center text-muted py-5" id="cartEmptyState">
                <i class="bi bi-cart-x fs-1 opacity-50"></i>
                <div class="mt-2 fw-semibold">Order is Empty</div>
                <small>Click any menu item to add it to the ticket</small>
            </div>
        </div>

        <!-- Bill Calculations & Totals Panel -->
        <div class="p-3 border-top bg-light">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Subtotal:</span>
                <span class="fw-semibold text-dark" id="billSubtotal">Rs. 0.00</span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Discount:</span>
                <div class="d-flex align-items-center gap-1">
                    <input type="number" id="billDiscountInput" class="form-control form-control-sm text-end py-0 px-1" style="width: 75px; height: 24px;" value="0" min="0" oninput="POS.calculateTotals()">
                    <span class="fw-semibold text-danger" id="billDiscountDisplay">-Rs. 0.00</span>
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Tax (<?= $taxRate ?>%):</span>
                <span class="fw-semibold text-dark" id="billTax">Rs. 0.00</span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Service Charge (<span id="servicePercentLabel"><?= $selectedServiceCharge ?></span>%):</span>
                <span class="fw-semibold text-dark" id="billService">Rs. 0.00</span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-2" id="deliveryFeeRow" style="display: <?= $selectedReqDelivery ? 'flex' : 'none' ?> !important;">
                <span>Delivery Fee:</span>
                <span class="fw-semibold text-dark" id="billDelivery">Rs. 0.00</span>
            </div>
            <div class="d-flex justify-content-between fs-5 fw-bold text-dark border-top pt-2">
                <span>Total Payable:</span>
                <span class="text-danger" id="billGrandTotal">Rs. 0.00</span>
            </div>
        </div>

        <!-- Bottom Action Bar (PRD 4.6) -->
        <div class="p-3 bg-white border-top d-grid gap-2">
            <div class="row g-2">
                <div class="col-6">
                    <button type="button" class="btn btn-outline-warning text-dark w-100 fw-semibold" onclick="POS.sendToKitchen()">
                        <i class="bi bi-fire me-1"></i> Send KOT
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn btn-outline-secondary w-100 fw-semibold" onclick="POS.saveOrderDraft()">
                        <i class="bi bi-pause-circle me-1"></i> Hold Draft
                    </button>
                </div>
            </div>
            <button type="button" class="btn btn-danger btn-lg w-100 fw-bold shadow" onclick="POS.openPaymentDrawer()">
                <i class="bi bi-credit-card-2-front-fill me-1"></i> Proceed to Pay & Bill
            </button>
        </div>
    </div>
</div>

<!-- ================= SLIDE-IN PANELS (NO MODALS PER PRD REQUIREMENT) ================= -->

<!-- 1. CUSTOMIZE ITEM SLIDE-IN PANEL (Variants, Modifiers, Instructions) -->
<div id="customizeItemPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="custItemTitle">Customize Dish</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('customizeItemPanel')"></button>
    </div>
    <div class="p-3 flex-grow-1 overflow-y-auto" id="custItemBody">
        <!-- Variant options -->
        <div id="custVariantsSection" class="mb-3 d-none">
            <label class="form-label fw-bold small text-uppercase text-muted">Select Variant / Size</label>
            <div class="list-group" id="custVariantsList"></div>
        </div>

        <!-- Modifier Groups -->
        <div id="custModifiersSection" class="mb-3">
            <!-- Dynamic Modifier Checkboxes -->
        </div>

        <!-- Special Instructions -->
        <div class="mb-3">
            <label class="form-label fw-bold small text-uppercase text-muted">Cooking Instructions</label>
            <textarea id="custSpecialInstructions" class="form-control" rows="2" placeholder="e.g. Less spicy, no mayo, extra crispy..."></textarea>
        </div>
    </div>
    <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
        <div>
            <small class="text-muted d-block">Dish Price</small>
            <span class="fs-5 fw-bold text-danger" id="custTotalPrice">Rs. 0.00</span>
        </div>
        <button type="button" class="btn btn-danger fw-semibold px-4" onclick="POS.confirmAddCustomizedItem()">
            <i class="bi bi-check2-circle me-1"></i> Add to Order
        </button>
    </div>
</div>

<!-- 2. SPLIT PAYMENT & BILLING SLIDE-IN PANEL (PRD Section 12) -->
<div id="paymentSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Order Billing & Payment</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('paymentSlidePanel')"></button>
    </div>
    <div class="p-3 flex-grow-1 overflow-y-auto">
        <!-- Payable Summary -->
        <div class="card p-3 border-0 bg-danger-subtle text-danger mb-3 text-center">
            <small class="fw-semibold text-uppercase">Total Amount Payable</small>
            <h2 class="fw-bold mb-0" id="payPanelGrandTotal">Rs. 0.00</h2>
        </div>

        <!-- Optional Customer Details for Printing on Bill -->
        <div class="card p-3 bg-light border mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-bold small text-uppercase text-muted mb-0">
                    <i class="bi bi-person-lines-fill text-danger me-1"></i> Customer on Bill (Optional)
                </label>
                <span class="badge bg-secondary-subtle text-secondary small">Optional</span>
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <input type="text" id="billCustomerName" class="form-control form-control-sm" placeholder="Customer Name (Optional)">
                </div>
                <div class="col-6">
                    <input type="text" id="billCustomerPhone" class="form-control form-control-sm" placeholder="Phone Number (Optional)">
                </div>
            </div>
            <small class="text-muted fst-italic mt-1 d-block" style="font-size: 0.72rem;">Optional: Enter to print customer's name & phone directly on the bill.</small>
        </div>

        <!-- Split Payment Method Inputs -->
        <label class="form-label fw-bold small text-uppercase text-muted mb-2">Payment Methods Allocation</label>
        <?php foreach ($paymentMethods as $pm): ?>
            <div class="mb-2" id="payMethodRow_<?= $pm['id'] ?>">
                <button type="button" class="btn w-100 text-start mb-1 fw-semibold pay-method-label d-flex justify-content-between align-items-center"
                        id="payMethodBtn_<?= $pm['id'] ?>"
                        onclick="POS.selectPaymentMethod(<?= $pm['id'] ?>)"
                        title="Click to pay with <?= htmlspecialchars($pm['name']) ?>">
                    <span class="text-truncate me-2">
                        <i class="bi bi-wallet2 me-1 text-muted"></i>
                        <?= htmlspecialchars($pm['name']) ?>
                    </span>
                    <span class="badge bg-light text-muted border method-status-badge flex-shrink-0" id="payBadge_<?= $pm['id'] ?>" style="font-size: 0.7rem;">Select</span>
                </button>
                <input type="number" class="form-control text-end fw-semibold pay-method-input" 
                       id="payInput_<?= $pm['id'] ?>"
                       data-method-id="<?= $pm['id'] ?>" 
                       data-code="<?= strtoupper($pm['code']) ?>" 
                       placeholder="0.00" min="0" step="any"
                       onfocus="this.select()"
                       onclick="this.select()"
                       oninput="POS.calculatePaymentBalance()">
            </div>
            <?php if ($pm['id'] == 4 || strtoupper($pm['code']) === 'CREDIT'): ?>
                <div id="creditDisabledNotice" class="small text-danger mb-2 d-none">
                    <i class="bi bi-slash-circle me-1"></i> Walk-in customer cannot purchase by credit. Select a registered account customer to enable credit.
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Cash Tendered & Change Return -->
        <div class="card p-3 bg-light border mt-3">
            <div class="row g-2 align-items-center mb-2">
                <div class="col-6">
                    <label class="form-label small fw-bold mb-0">Cash Tendered:</label>
                </div>
                <div class="col-6">
                    <input type="number" id="payTenderedInput" class="form-control form-control-sm text-end fw-bold" placeholder="0.00"
                           onfocus="this.select()" onclick="this.select()" oninput="POS.calculateChangeReturn()">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold text-muted small">Change to Return:</span>
                <span class="fs-5 fw-bold text-success" id="payChangeReturn">Rs. 0.00</span>
            </div>
        </div>

        <!-- Payment Remaining Balance -->
        <div class="mt-3 p-2 bg-light rounded text-center">
            <span class="text-muted small">Remaining Balance: </span>
            <span class="fw-bold text-danger fs-5" id="payRemainingBalance">Rs. 0.00</span>
        </div>
    </div>

    <!-- Complete Payment Button -->
    <div class="p-3 border-top bg-light d-grid gap-2">
        <button type="button" class="btn btn-success btn-lg fw-bold" id="btnConfirmPayment" onclick="POS.submitPayment()">
            <i class="bi bi-check2-all me-1"></i> Complete & Print Receipt
        </button>
    </div>
</div>

<!-- 3. HELD / ACTIVE ORDERS TABS SLIDE-IN PANEL -->
<div id="heldOrdersPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Active Tabs & Held Orders</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('heldOrdersPanel')"></button>
    </div>
    <div class="p-3 flex-grow-1 overflow-y-auto" id="heldOrdersList">
        <!-- Dynamically rendered active orders -->
    </div>
</div>

<!-- Hidden IFrame for silent thermal receipt printing -->
<iframe id="printReceiptFrame" style="display:none;"></iframe>

<script>
const POS = {
    menu: { categories: [], items: [], modifier_groups: [] },
    cart: [],
    currentOrder: {
        id: null,
        order_number: 'New Order',
        order_type_id: <?= $selectedTypeId ?>,
        requires_table: <?= $selectedReqTable ?>,
        requires_delivery: <?= $selectedReqDelivery ?>,
        table_id: null,
        customer_id: 1,
        delivery_zone_id: null,
        rider_id: null,
        delivery_fee: 0.00,
        discount_amount: 0.00,
        tax_percent: <?= $taxRate ?>,
        service_charge_percent: <?= $selectedServiceCharge ?>,
        notes: ''
    },
    customizingItem: null,

    init: async function() {
        try {
            const data = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=get_menu');
            POS.menu = data;
            POS.renderCategories();
            POS.renderProducts(POS.menu.items);
            POS.checkCreditAvailability();

            // Sync with URL query parameter (?type=takeaway, ?type=delivery, etc.)
            const urlParams = new URLSearchParams(window.location.search);
            const typeArg = urlParams.get('type');
            if (typeArg) {
                const btn = document.querySelector(`.order-type-btn[data-code="${typeArg.toLowerCase()}"]`);
                if (btn && !btn.classList.contains('active')) {
                    btn.click();
                }
            }
        } catch (e) {
            console.error('POS menu load error', e);
        }
    },

    renderCategories: function() {
        const container = document.getElementById('categoryContainer');
        container.innerHTML = POS.menu.categories.map(c => `
            <button class="category-item w-100 rounded text-start" data-cat="${c.id}" onclick="POS.filterCategory(${c.id}, this)">
                <i class="bi ${c.icon_class || 'bi-egg-fried'} text-secondary fs-5"></i> ${c.name}
            </button>
        `).join('');
    },

    renderProducts: function(items) {
        const grid = document.getElementById('posProductGrid');
        if (!items || items.length === 0) {
            grid.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-search fs-2"></i><div class="mt-2">No matching dishes found.</div></div>';
            return;
        }

        grid.innerHTML = items.map(it => {
            const hasVariants = it.variants && it.variants.length > 0;
            const hasModifiers = it.modifier_groups && it.modifier_groups.length > 0;
            const hasOptions = hasVariants || hasModifiers;
            const price = parseFloat(it.base_price);
            return `
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="pos-product-card" onclick="POS.onProductClicked(${it.id})">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge ${it.is_veg ? 'bg-success' : 'bg-danger'} px-1 py-0 small">${it.is_veg ? 'VEG' : 'NON-VEG'}</span>
                                ${hasOptions ? '<span class="badge bg-secondary-subtle text-secondary small">Options</span>' : ''}
                            </div>
                            <h6 class="fw-bold mb-1 text-dark">${it.name}</h6>
                            <small class="text-muted d-block line-clamp-2" style="font-size: 0.76rem;">${it.description || ''}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="fw-bold text-danger fs-6">${RMS.formatMoney(price)}</span>
                            <button type="button" class="btn btn-sm btn-danger rounded-circle p-1" style="width: 28px; height: 28px; line-height: 1;">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    filterCategory: function(catId, btn) {
        document.querySelectorAll('.category-item').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        if (catId === 'all') {
            POS.renderProducts(POS.menu.items);
        } else {
            const filtered = POS.menu.items.filter(it => it.category_id == catId);
            POS.renderProducts(filtered);
        }
    },

    filterSearch: function(query) {
        query = query.toLowerCase().trim();
        if (!query) {
            POS.renderProducts(POS.menu.items);
            return;
        }
        const filtered = POS.menu.items.filter(it => 
            it.name.toLowerCase().includes(query) || (it.code && it.code.toLowerCase().includes(query))
        );
        POS.renderProducts(filtered);
    },

    onProductClicked: function(itemId) {
        const item = POS.menu.items.find(i => i.id == itemId);
        if (!item) return;

        // If item has variants or item-specific modifier groups, open the customize slide-in drawer
        const hasVariants = item.variants && item.variants.length > 0;
        const hasModifiers = item.modifier_groups && item.modifier_groups.length > 0;

        if (hasVariants || hasModifiers) {
            POS.openCustomizeDrawer(item);
        } else {
            // Direct single-click addition
            POS.addItemToCart({
                menu_item_id: item.id,
                name: item.name,
                menu_variant_id: null,
                variant_name: null,
                unit_price: parseFloat(item.base_price),
                kitchen_station_id: item.kitchen_station_id,
                quantity: 1,
                modifiers: [],
                instructions: ''
            });
        }
    },

    openCustomizeDrawer: function(item) {
        POS.customizingItem = { ...item, selectedVariant: null, selectedModifiers: [] };
        document.getElementById('custItemTitle').innerText = item.name;
        document.getElementById('custSpecialInstructions').value = '';

        // Variants
        const varSec = document.getElementById('custVariantsSection');
        const varList = document.getElementById('custVariantsList');
        if (item.variants && item.variants.length > 0) {
            varSec.classList.remove('d-none');
            varList.innerHTML = item.variants.map((v, idx) => `
                <label class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <input class="form-check-input me-2" type="radio" name="variantChoice" value="${v.id}" data-price="${v.final_price}" data-name="${v.name}" ${idx === 0 ? 'checked' : ''} onchange="POS.updateCustPrice()">
                        <span class="fw-medium">${v.name}</span>
                    </div>
                    <span class="fw-bold text-dark">${RMS.formatMoney(v.final_price)}</span>
                </label>
            `).join('');
            POS.customizingItem.selectedVariant = item.variants[0];
        } else {
            varSec.classList.add('d-none');
        }

        // Modifiers (Only the modifier groups applicable to this specific item)
        const modSec = document.getElementById('custModifiersSection');
        const itemModGroups = item.modifier_groups || [];

        if (itemModGroups.length > 0) {
            modSec.classList.remove('d-none');
            modSec.innerHTML = itemModGroups.map(g => {
                const isSingleChoice = (parseInt(g.max_selection) === 1);
                const inputType = isSingleChoice ? 'radio' : 'checkbox';
                const inputName = `modGroup_${g.id}`;
                return `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-0">${g.name}</label>
                            ${g.is_required ? '<span class="badge bg-danger-subtle text-danger small">Required</span>' : '<span class="badge bg-light text-muted small border">Optional</span>'}
                        </div>
                        <div class="list-group">
                            ${(g.options || []).map((opt, optIdx) => {
                                const isChecked = (isSingleChoice && g.is_required && optIdx === 0);
                                return `
                                    <label class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <input class="form-check-input me-2 cust-mod-check" type="${inputType}" name="${inputName}" value="${opt.id}" data-name="${opt.name}" data-price="${opt.price}" ${isChecked ? 'checked' : ''} onchange="POS.updateCustPrice()">
                                            <span>${opt.name}</span>
                                        </div>
                                        <span class="text-danger fw-semibold">+${RMS.formatMoney(opt.price)}</span>
                                    </label>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            modSec.innerHTML = '';
            modSec.classList.add('d-none');
        }

        POS.updateCustPrice();
        RMS.openSlidePanel('customizeItemPanel');
    },

    updateCustPrice: function() {
        let base = parseFloat(POS.customizingItem.base_price);
        const checkedVar = document.querySelector('input[name="variantChoice"]:checked');
        if (checkedVar) {
            base = parseFloat(checkedVar.getAttribute('data-price'));
        }

        let modTotal = 0.00;
        document.querySelectorAll('.cust-mod-check:checked').forEach(chk => {
            modTotal += parseFloat(chk.getAttribute('data-price'));
        });

        const total = base + modTotal;
        document.getElementById('custTotalPrice').innerText = RMS.formatMoney(total);
    },

    confirmAddCustomizedItem: function() {
        const item = POS.customizingItem;
        const checkedVar = document.querySelector('input[name="variantChoice"]:checked');
        let variantId = null;
        let variantName = null;
        let price = parseFloat(item.base_price);

        if (checkedVar) {
            variantId = parseInt(checkedVar.value);
            variantName = checkedVar.getAttribute('data-name');
            price = parseFloat(checkedVar.getAttribute('data-price'));
        }

        const selectedMods = [];
        document.querySelectorAll('.cust-mod-check:checked').forEach(chk => {
            selectedMods.push({
                id: parseInt(chk.value),
                name: chk.getAttribute('data-name'),
                price: parseFloat(chk.getAttribute('data-price'))
            });
        });

        const instructions = document.getElementById('custSpecialInstructions').value.trim();

        POS.addItemToCart({
            menu_item_id: item.id,
            name: item.name,
            menu_variant_id: variantId,
            variant_name: variantName,
            unit_price: price,
            kitchen_station_id: item.kitchen_station_id,
            quantity: 1,
            modifiers: selectedMods,
            instructions: instructions
        });

        RMS.closeSlidePanel('customizeItemPanel');
    },

    addItemToCart: function(line) {
        // Check if identical line exists
        const match = POS.cart.find(c => 
            c.menu_item_id === line.menu_item_id &&
            c.menu_variant_id === line.menu_variant_id &&
            JSON.stringify(c.modifiers) === JSON.stringify(line.modifiers) &&
            c.instructions === line.instructions
        );

        if (match) {
            match.quantity += 1;
        } else {
            POS.cart.push(line);
        }

        POS.renderCart();
    },

    updateCartQty: function(index, delta) {
        if (!POS.cart[index]) return;
        POS.cart[index].quantity += delta;
        if (POS.cart[index].quantity <= 0) {
            POS.cart.splice(index, 1);
        }
        POS.renderCart();
    },

    removeCartItem: function(index) {
        POS.cart.splice(index, 1);
        POS.renderCart();
    },

    renderCart: function() {
        const container = document.getElementById('cartItemsContainer');
        if (!container) return;

        if (POS.cart.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-5" id="cartEmptyState">
                    <i class="bi bi-cart-x fs-1 opacity-50"></i>
                    <div class="mt-2 fw-semibold">Order is Empty</div>
                    <small>Click any menu item to add it to the ticket</small>
                </div>
            `;
            POS.calculateTotals();
            return;
        }

        container.innerHTML = POS.cart.map((c, idx) => {
            let modText = '';
            if (c.modifiers && c.modifiers.length > 0) {
                modText = '<small class="text-danger d-block">' + c.modifiers.map(m => '+ ' + m.name).join(', ') + '</small>';
            }
            let instText = '';
            if (c.instructions) {
                instText = `<small class="text-muted fst-italic d-block"><i class="bi bi-chat-left-text me-1"></i>${c.instructions}</small>`;
            }

            let lineSubtotal = (c.unit_price + (c.modifiers || []).reduce((acc, m) => acc + m.price, 0)) * c.quantity;

            return `
                <div class="cart-line">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="fw-bold text-dark">${c.name}</span>
                            ${c.variant_name ? `<span class="badge bg-secondary-subtle text-secondary ms-1">${c.variant_name}</span>` : ''}
                            ${modText}
                            ${instText}
                        </div>
                        <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="POS.removeCartItem(${idx})">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="input-group input-group-sm" style="width: 100px;">
                            <button class="btn btn-outline-secondary" type="button" onclick="POS.updateCartQty(${idx}, -1)">-</button>
                            <input type="text" class="form-control text-center bg-white" value="${c.quantity}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="POS.updateCartQty(${idx}, 1)">+</button>
                        </div>
                        <span class="fw-bold text-dark">${RMS.formatMoney(lineSubtotal)}</span>
                    </div>
                </div>
            `;
        }).join('');

        POS.calculateTotals();
    },

    calculateTotals: function() {
        let subtotal = 0.00;
        POS.cart.forEach(c => {
            const modSum = (c.modifiers || []).reduce((a, m) => a + m.price, 0);
            subtotal += (c.unit_price + modSum) * c.quantity;
        });

        const discountInput = parseFloat(document.getElementById('billDiscountInput').value) || 0;
        const discount = Math.min(discountInput, subtotal);
        const taxable = Math.max(0, subtotal - discount);

        const tax = taxable * (POS.currentOrder.tax_percent / 100);
        const service = taxable * (POS.currentOrder.service_charge_percent / 100);
        const delivery = POS.currentOrder.delivery_fee;
        const grandTotal = taxable + tax + service + delivery;

        document.getElementById('billSubtotal').innerText = RMS.formatMoney(subtotal);
        document.getElementById('billDiscountDisplay').innerText = '-' + RMS.formatMoney(discount);
        document.getElementById('billTax').innerText = RMS.formatMoney(tax);
        document.getElementById('billService').innerText = RMS.formatMoney(service);
        document.getElementById('billDelivery').innerText = RMS.formatMoney(delivery);
        document.getElementById('billGrandTotal').innerText = RMS.formatMoney(grandTotal);

        POS.currentOrder.subtotal = subtotal;
        POS.currentOrder.discount_amount = discount;
        POS.currentOrder.tax_amount = tax;
        POS.currentOrder.service_charge_amount = service;
        POS.currentOrder.grand_total = grandTotal;
    },

    setOrderType: function(typeId, reqTable, reqDelivery, btn) {
        document.querySelectorAll('.order-type-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        POS.currentOrder.order_type_id = typeId;
        POS.currentOrder.requires_table = reqTable;
        POS.currentOrder.requires_delivery = reqDelivery;

        const tableWrapper = document.getElementById('tableSelectWrapper');
        const deliveryRow = document.getElementById('deliveryDetailsRow');
        const deliveryFeeRow = document.getElementById('deliveryFeeRow');
        const serviceLabel = document.getElementById('servicePercentLabel');

        if (reqTable) {
            tableWrapper.classList.remove('d-none');
            POS.currentOrder.service_charge_percent = <?= $serviceCharge ?>;
            serviceLabel.innerText = '<?= $serviceCharge ?>';
        } else {
            tableWrapper.classList.add('d-none');
            POS.currentOrder.table_id = null;
            document.getElementById('posTableSelect').value = '';
            POS.currentOrder.service_charge_percent = 0;
            serviceLabel.innerText = '0';
        }

        if (reqDelivery) {
            deliveryRow.classList.remove('d-none');
            deliveryFeeRow.style.display = 'flex';
        } else {
            deliveryRow.classList.add('d-none');
            deliveryFeeRow.style.display = 'none';
            POS.currentOrder.delivery_fee = 0.00;
        }

        POS.calculateTotals();
    },

    onTableChange: function(tblId) {
        POS.currentOrder.table_id = tblId ? parseInt(tblId) : null;
    },

    onCustomerChange: function(custId) {
        POS.currentOrder.customer_id = parseInt(custId) || 1;
        POS.checkCreditAvailability();
    },

    checkCreditAvailability: function() {
        const custSelect = document.getElementById('posCustomerSelect');
        const custId = parseInt(custSelect ? custSelect.value : 1) || 1;
        const creditInput = document.querySelector('.pay-method-input[data-code="CREDIT"]') || document.getElementById('payInput_4');
        const creditRow = document.getElementById('payMethodRow_4');
        const notice = document.getElementById('creditDisabledNotice');

        if (custId === 1) {
            // Walk-in customer: Disable credit purchase
            if (creditInput) {
                creditInput.value = '';
                creditInput.disabled = true;
                creditInput.placeholder = 'N/A (Walk-in)';
            }
            if (creditRow) creditRow.classList.add('opacity-50');
            if (notice) notice.classList.remove('d-none');
        } else {
            // Registered account customer: Enable credit purchase
            if (creditInput) {
                creditInput.disabled = false;
                creditInput.placeholder = '0.00';
            }
            if (creditRow) creditRow.classList.remove('opacity-50');
            if (notice) notice.classList.add('d-none');
        }
        POS.calculatePaymentBalance();
    },

    onDeliveryZoneChange: function(select) {
        const fee = parseFloat(select.options[select.selectedIndex].getAttribute('data-fee')) || 0;
        POS.currentOrder.delivery_fee = fee;
        POS.currentOrder.delivery_zone_id = parseInt(select.value);
        POS.calculateTotals();
    },

    clearCart: function() {
        POS.cart = [];
        POS.currentOrder.id = null;
        POS.currentOrder.order_number = 'New Order';
        POS.currentOrder.customer_id = 1;
        document.getElementById('displayOrderNum').innerText = 'New Order';
        document.getElementById('orderStatusBadge').innerText = 'Draft';
        document.getElementById('posTableSelect').value = '';
        const custSelect = document.getElementById('posCustomerSelect');
        if (custSelect) custSelect.value = '1';
        if (document.getElementById('billCustomerName')) document.getElementById('billCustomerName').value = '';
        if (document.getElementById('billCustomerPhone')) document.getElementById('billCustomerPhone').value = '';
        document.getElementById('billDiscountInput').value = 0;
        POS.renderCart();
        POS.checkCreditAvailability();
    },

    // Save order as active/draft in database
    saveOrderDraft: async function() {
        if (POS.cart.length === 0) {
            RMS.toast('Please add items to the order first.', 'warning');
            return;
        }

        if (POS.currentOrder.requires_table && !POS.currentOrder.table_id) {
            RMS.toast('Please select a dine-in table.', 'warning');
            return;
        }

        const custSelect = document.getElementById('posCustomerSelect');
        const custId = parseInt(custSelect ? custSelect.value : 1) || 1;
        const custName = document.getElementById('billCustomerName')?.value.trim() || null;
        const custPhone = document.getElementById('billCustomerPhone')?.value.trim() || null;

        const payload = {
            order_id: POS.currentOrder.id,
            order_type_id: POS.currentOrder.order_type_id,
            table_id: POS.currentOrder.table_id,
            customer_id: custId,
            customer_name: custName,
            customer_phone: custPhone,
            discount_amount: POS.currentOrder.discount_amount,
            tax_percent: POS.currentOrder.tax_percent,
            service_charge_percent: POS.currentOrder.service_charge_percent,
            delivery_fee: POS.currentOrder.delivery_fee,
            delivery_zone_id: POS.currentOrder.delivery_zone_id,
            rider_id: document.getElementById('posRiderSelect').value,
            items: POS.cart
        };

        try {
            const res = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=save_order', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            POS.currentOrder.id = res.order_id;
            POS.currentOrder.order_number = res.order_number;
            document.getElementById('displayOrderNum').innerText = res.order_number;
            document.getElementById('orderStatusBadge').innerText = 'Saved';
            RMS.toast(res.message, 'success');
        } catch (e) {}
    },

    // Send items to Kitchen KDS
    sendToKitchen: async function() {
        await POS.saveOrderDraft();
        if (!POS.currentOrder.id) return;

        const formData = new FormData();
        formData.append('order_id', POS.currentOrder.id);

        try {
            const res = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=send_to_kitchen', {
                method: 'POST',
                body: formData
            });
            document.getElementById('orderStatusBadge').innerText = 'In Kitchen';
            RMS.toast(res.message, 'success');
        } catch (e) {}
    },

    // Open Payment Drawer (Slide-in)
    openPaymentDrawer: async function() {
        if (POS.cart.length === 0) {
            RMS.toast('No items to pay for.', 'warning');
            return;
        }
        await POS.saveOrderDraft();
        if (!POS.currentOrder.id) return;

        // Always recalculate totals fresh so grand_total is never stale/zero
        POS.calculateTotals();

        const grandTotal = POS.currentOrder.grand_total || 0;
        document.getElementById('payPanelGrandTotal').innerText = RMS.formatMoney(grandTotal);
        
        // Populate or clear optional customer bill details
        const custSelect = document.getElementById('posCustomerSelect');
        const custId = parseInt(custSelect ? custSelect.value : 1) || 1;
        const selectedOpt = custSelect ? custSelect.options[custSelect.selectedIndex] : null;
        
        if (custId !== 1 && selectedOpt) {
            document.getElementById('billCustomerName').value = selectedOpt.getAttribute('data-name') || '';
            document.getElementById('billCustomerPhone').value = selectedOpt.getAttribute('data-phone') || '';
        } else {
            // Walk-in: keep optional fields ready for cashier to enter if customer wants receipt with their name/phone
            document.getElementById('billCustomerName').value = '';
            document.getElementById('billCustomerPhone').value = '';
        }

        // Apply credit policy (Walk-in cannot purchase by credit)
        POS.checkCreditAvailability();

        // Default entire amount in Cash input, clear all others first
        document.querySelectorAll('.pay-method-input').forEach(inp => inp.value = '');
        // Find Cash by code (robust — not hardcoded id=1)
        const cashInput = document.querySelector('.pay-method-input[data-code="CASH"]')
                       || document.querySelector('.pay-method-input[data-method-id="1"]');
        if (cashInput && !cashInput.disabled) {
            cashInput.value = grandTotal.toFixed(2);
            document.getElementById('payTenderedInput').value = grandTotal.toFixed(2);
        }
        POS.calculatePaymentBalance();
        RMS.openSlidePanel('paymentSlidePanel');
    },

    calculatePaymentBalance: function() {
        let totalAllocated = 0.00;
        let grandTotal = POS.currentOrder.grand_total;
        if (!grandTotal || grandTotal <= 0) {
            POS.calculateTotals();
            grandTotal = POS.currentOrder.grand_total || 0;
        }

        document.querySelectorAll('.pay-method-input').forEach(inp => {
            const mId = inp.getAttribute('data-method-id');
            const badge = document.getElementById('payBadge_' + mId);
            const btn = document.getElementById('payMethodBtn_' + mId);

            if (inp.disabled) {
                if (badge) {
                    badge.className = 'badge bg-secondary-subtle text-secondary small flex-shrink-0';
                    badge.innerText = 'Disabled';
                }
                if (btn) btn.classList.remove('border-success', 'border-primary', 'bg-success-subtle', 'bg-primary-subtle');
                return;
            }

            const val = parseFloat(inp.value) || 0;
            totalAllocated += val;

            if (badge && btn) {
                if (val > 0 && Math.abs(val - grandTotal) < 0.01) {
                    badge.className = 'badge bg-success text-white small flex-shrink-0';
                    badge.innerText = 'Full Amount';
                    btn.classList.add('border-success', 'bg-success-subtle');
                    btn.classList.remove('border-primary', 'bg-primary-subtle');
                } else if (val > 0) {
                    badge.className = 'badge bg-primary text-white small flex-shrink-0';
                    badge.innerText = 'Split: ' + RMS.formatMoney(val);
                    btn.classList.add('border-primary', 'bg-primary-subtle');
                    btn.classList.remove('border-success', 'bg-success-subtle');
                } else {
                    badge.className = 'badge bg-light text-muted border small flex-shrink-0';
                    badge.innerText = 'Select';
                    btn.classList.remove('border-success', 'border-primary', 'bg-success-subtle', 'bg-primary-subtle');
                }
            }
        });

        const remaining = Math.max(0, grandTotal - totalAllocated);
        const remElem = document.getElementById('payRemainingBalance');
        if (remElem) {
            remElem.innerText = RMS.formatMoney(remaining);
            if (remaining <= 0.001) {
                remElem.className = 'fw-bold text-success fs-5';
            } else {
                remElem.className = 'fw-bold text-danger fs-5';
            }
        }

        POS.calculateChangeReturn();
    },

    selectPaymentMethod: function(methodId) {
        methodId = parseInt(methodId);
        let grandTotal = POS.currentOrder.grand_total;
        if (!grandTotal || grandTotal <= 0) {
            POS.calculateTotals();
            grandTotal = POS.currentOrder.grand_total || 0;
        }

        const targetInput = document.getElementById('payInput_' + methodId);
        if (!targetInput || targetInput.disabled) return;

        const allInputs = Array.from(document.querySelectorAll('.pay-method-input:not(:disabled)'));

        // Identify other inputs that currently have non-zero amounts
        const otherInputsWithValues = allInputs.filter(inp => {
            const mId = parseInt(inp.getAttribute('data-method-id'));
            return mId !== methodId && (parseFloat(inp.value) || 0) > 0.001;
        });

        const targetCurrentVal = parseFloat(targetInput.value) || 0;
        const otherTotal = otherInputsWithValues.reduce((sum, inp) => sum + (parseFloat(inp.value) || 0), 0);
        const remainingUnallocated = Math.max(0, grandTotal - (otherTotal + targetCurrentVal));

        if (otherInputsWithValues.length <= 1 && otherTotal >= (grandTotal - 0.01)) {
            // Case 1: All money was in one other method (e.g. Cash was 100%, now user clicks Bank/Card or Wallet)
            // Move the WHOLE amount to this clicked method!
            allInputs.forEach(inp => inp.value = '');
            targetInput.value = grandTotal.toFixed(2);
        } else if (remainingUnallocated > 0.001) {
            // Case 2: Cashier entered a partial split in another field (e.g. 100 in Cash)
            // and now clicked Bank/Wallet to fill the remaining unallocated balance!
            targetInput.value = (targetCurrentVal + remainingUnallocated).toFixed(2);
        } else if (otherInputsWithValues.length > 0 && targetCurrentVal <= 0.001) {
            // Case 3: All balance was allocated, but user clicked this method which currently has 0.
            // Move the non-cash portion to this method!
            const nonCashOther = otherInputsWithValues.find(inp => inp.getAttribute('data-code') !== 'CASH') || otherInputsWithValues[0];
            const amountToMove = parseFloat(nonCashOther.value) || 0;
            nonCashOther.value = '';
            targetInput.value = amountToMove.toFixed(2);
        } else {
            // Case 4: Re-affirm 100% on target method
            allInputs.forEach(inp => inp.value = '');
            targetInput.value = grandTotal.toFixed(2);
        }

        // Sync Cash Tendered
        const cashInput = document.querySelector('.pay-method-input[data-code="CASH"]') || document.getElementById('payInput_1');
        const cashVal = parseFloat(cashInput ? cashInput.value : 0) || 0;
        const tenderedInput = document.getElementById('payTenderedInput');
        if (tenderedInput) {
            tenderedInput.value = cashVal > 0 ? cashVal.toFixed(2) : '';
        }

        targetInput.focus();
        targetInput.select();
        POS.calculatePaymentBalance();
    },

    allocateRemaining: function(methodId) {
        POS.selectPaymentMethod(methodId);
    },

    calculateChangeReturn: function() {
        const cashInput = document.querySelector('.pay-method-input[data-code="CASH"]')
                       || document.querySelector('.pay-method-input[data-method-id="1"]');
        const cashAllocated = parseFloat(cashInput ? cashInput.value : 0) || 0;
        const cashTendered = parseFloat(document.getElementById('payTenderedInput')?.value) || 0;
        const change = Math.max(0, cashTendered - cashAllocated);
        const changeElem = document.getElementById('payChangeReturn');
        if (changeElem) changeElem.innerText = RMS.formatMoney(change);
    },

    submitPayment: async function() {
        const custSelect = document.getElementById('posCustomerSelect');
        const custId = parseInt(custSelect ? custSelect.value : 1) || 1;

        const payments = [];
        let totalAllocated = 0.00;
        let creditAllocated = 0.00;

        document.querySelectorAll('.pay-method-input').forEach(inp => {
            if (inp.disabled) return;
            const amt = parseFloat(inp.value) || 0;
            if (amt > 0) {
                const methodId = parseInt(inp.getAttribute('data-method-id'));
                if (methodId === 4) {
                    creditAllocated += amt;
                }
                let tendered = amt;
                let change = 0.00;
                if (methodId === 1) { // Cash
                    tendered = parseFloat(document.getElementById('payTenderedInput').value) || amt;
                    change = Math.max(0, tendered - amt);
                }
                payments.push({
                    method_id: methodId,
                    amount: amt,
                    tendered: tendered,
                    change: change
                });
                totalAllocated += amt;
            }
        });

        // Enforce walk-in cannot purchase by credit
        if (creditAllocated > 0 && custId === 1) {
            RMS.toast('Walk-in customer cannot purchase by credit. Please choose Cash or Card, or select a registered customer.', 'error');
            return;
        }

        if (payments.length === 0) {
            RMS.toast('Please enter a payment amount.', 'warning');
            return;
        }

        const custName = document.getElementById('billCustomerName')?.value.trim() || '';
        const custPhone = document.getElementById('billCustomerPhone')?.value.trim() || '';

        const payload = {
            order_id: POS.currentOrder.id,
            customer_name: custName,
            customer_phone: custPhone,
            payments: payments
        };

        try {
            const res = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=process_payment', {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            RMS.toast(res.message, 'success');
            RMS.closeSlidePanel('paymentSlidePanel');

            // Trigger silent print of customer receipt
            document.getElementById('printReceiptFrame').src = '<?= BASE_URL ?>print/receipt.php?order_id=' + res.order_id;

            // Reset cart
            POS.clearCart();
        } catch (e) {}
    },

    // Held orders modal
    openHeldOrders: async function() {
        try {
            const res = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=get_held_orders');
            const container = document.getElementById('heldOrdersList');
            if (res.orders.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2"></i><div class="mt-2">No active held orders.</div></div>';
            } else {
                container.innerHTML = res.orders.map(o => `
                    <div class="card p-3 mb-2 shadow-sm border">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-bold text-danger">${o.order_number}</span>
                                <span class="badge bg-secondary ms-1">${o.order_type_name}</span>
                                ${o.table_number ? `<span class="badge bg-danger ms-1">Table ${o.table_number}</span>` : ''}
                            </div>
                            <span class="fw-bold">${RMS.formatMoney(o.grand_total)}</span>
                        </div>
                        <div class="small text-muted mt-1">
                            ${o.customer_name ? `Customer: ${o.customer_name}` : 'Walk-in'} | Status: <span class="badge bg-warning-subtle text-warning">${o.order_status}</span>
                        </div>
                        <div class="mt-2 text-end">
                            <button class="btn btn-sm btn-danger px-3" onclick="POS.resumeOrder(${o.id})">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Resume Order
                            </button>
                        </div>
                    </div>
                `).join('');
            }
            RMS.openSlidePanel('heldOrdersPanel');
        } catch (e) {}
    },

    resumeOrder: async function(orderId) {
        try {
            const res = await RMS.api('<?= BASE_URL ?>ajax/pos.php?action=get_order_details&order_id=' + orderId);
            const o = res.order;

            POS.currentOrder.id = o.id;
            POS.currentOrder.order_number = o.order_number;
            POS.currentOrder.order_type_id = o.order_type_id;
            POS.currentOrder.table_id = o.table_id;
            POS.currentOrder.customer_id = o.customer_id;
            POS.currentOrder.tax_percent = parseFloat(o.tax_percent);
            POS.currentOrder.service_charge_percent = parseFloat(o.service_charge_percent);
            POS.currentOrder.delivery_fee = parseFloat(o.delivery_fee);

            document.getElementById('displayOrderNum').innerText = o.order_number;
            document.getElementById('orderStatusBadge').innerText = o.order_status;
            document.getElementById('posTableSelect').value = o.table_id || '';
            document.getElementById('posCustomerSelect').value = o.customer_id || '1';
            document.getElementById('billDiscountInput').value = o.discount_amount || 0;

            // Load cart items
            POS.cart = (o.items || []).map(it => ({
                menu_item_id: it.menu_item_id,
                name: it.item_name,
                menu_variant_id: it.menu_variant_id,
                variant_name: it.variant_name,
                unit_price: parseFloat(it.unit_price),
                kitchen_station_id: it.kitchen_station_id,
                quantity: parseInt(it.quantity),
                modifiers: (it.modifiers || []).map(m => ({ id: m.modifier_id, name: m.modifier_name, price: parseFloat(m.price) })),
                instructions: it.special_instructions || ''
            }));

            POS.renderCart();
            RMS.closeSlidePanel('heldOrdersPanel');
            RMS.toast('Order resumed successfully!', 'info');
        } catch (e) {}
    }
};

document.addEventListener('DOMContentLoaded', POS.init);
</script>
</body>
</html>
