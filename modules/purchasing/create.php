<?php
// modules/purchasing/create.php - Dedicated Purchase Order & Stock Receiving Workflow (PRD 11.2 & 25.3)
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_engine.php';
require_once __DIR__ . '/../../includes/accounting_engine.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';

requirePermission('purchasing.manage');

$pageTitle = 'New Purchase & Receiving';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();

// Handle purchase submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Security token expired.');
    } else {
        $supplierId = (int)post('supplier_id');
        $poDate = cleanInput(post('po_date', date('Y-m-d')));
        $shippingCost = (float)post('shipping_cost', 0);
        $paidAmount = (float)post('paid_amount', 0);
        $notes = cleanInput(post('notes'));

        $ingIds = post('ingredient_ids', []);
        $quantities = post('quantities', []);
        $unitPrices = post('unit_prices', []);
        $unitIds = post('unit_ids', []);

        if (!$supplierId || empty($ingIds)) {
            setFlash('error', 'Supplier and at least one line item are required.');
        } else {
            try {
                $db->beginTransaction();

                $subtotal = 0.00;
                $lineItems = [];

                foreach ($ingIds as $k => $iId) {
                    $iId = (int)$iId;
                    $qty = (float)($quantities[$k] ?? 0);
                    $price = (float)($unitPrices[$k] ?? 0);
                    $uId = (int)($unitIds[$k] ?? 1);

                    if ($iId > 0 && $qty > 0) {
                        $lineTotal = round($qty * $price, 2);
                        $subtotal += $lineTotal;

                        $lineItems[] = [
                            'ingredient_id' => $iId,
                            'quantity' => $qty,
                            'unit_id' => $uId,
                            'unit_price' => $price,
                            'total_price' => $lineTotal
                        ];
                    }
                }

                $grandTotal = $subtotal + $shippingCost;
                $poNumber = 'PO-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

                // Insert PO
                $poStmt = $db->prepare("
                    INSERT INTO purchase_orders (
                        branch_id, supplier_id, po_number, po_date, subtotal, 
                        shipping_cost, grand_total, paid_amount, status, notes, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'received', ?, ?, NOW())
                ");
                $poStmt->execute([
                    $branchId, $supplierId, $poNumber, $poDate, $subtotal,
                    $shippingCost, $grandTotal, $paidAmount, $notes, $userId
                ]);
                $poId = (int)$db->lastInsertId();

                // Insert PO items
                $itemStmt = $db->prepare("
                    INSERT INTO purchase_order_items (purchase_order_id, ingredient_id, quantity_ordered, quantity_received, unit_id, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($lineItems as $li) {
                    $itemStmt->execute([$poId, $li['ingredient_id'], $li['quantity'], $li['quantity'], $li['unit_id'], $li['unit_price'], $li['total_price']]);
                }

                // Update supplier balance
                $unpaid = round($grandTotal - $paidAmount, 2);
                if ($unpaid > 0) {
                    $db->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?")->execute([$unpaid, $supplierId]);
                    $balAfter = (float)$db->query("SELECT current_balance FROM suppliers WHERE id = $supplierId")->fetchColumn();

                    $db->prepare("
                        INSERT INTO supplier_ledger (supplier_id, transaction_type, reference_type, reference_id, debit, credit, balance, notes, created_at)
                        VALUES (?, 'purchase_invoice', 'purchase_orders', ?, 0.00, ?, ?, ?, NOW())
                    ")->execute([$supplierId, $poId, $unpaid, $balAfter, "PO #$poNumber stock received"]);
                }

                // 1. INVENTORY ENGINE: Receive stock into ingredients & update weighted average cost
                InventoryEngine::receivePurchaseStock($poId, $branchId, $userId);

                // 2. ACCOUNTING ENGINE: Post double-entry journal entry
                AccountingEngine::postPurchaseJournal($poId, $userId);

                $db->commit();
                logAudit('create_po', 'purchase_orders', $poId, null, ['po_number' => $poNumber, 'total' => $grandTotal]);

                setFlash('success', "Purchase Order #$poNumber received and inventory stock updated successfully!");
                echo "<script>window.location.href = '" . BASE_URL . "modules/purchasing/index.php';</script>";
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', "Database error: " . $e->getMessage());
            }
        }
    }
}

$suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allIngredients = $db->query("SELECT i.*, u.code as unit_code FROM ingredients i JOIN units u ON i.unit_id = u.id WHERE i.is_active = 1 ORDER BY i.name ASC")->fetchAll();
$allUnits = $db->query("SELECT * FROM units WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Create Purchase & Goods Receiving</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>modules/purchasing/index.php">Purchases</a></li>
                <li class="breadcrumb-item active">New Receiving</li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>modules/purchasing/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Orders
    </a>
</div>

<form method="POST" action="" id="poForm">
    <?= csrfField(); ?>
    <div class="row g-4">
        <!-- Order Lines -->
        <div class="col-12 col-lg-8">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Supplier & Order Details</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-7">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['name']) ?> (Balance: <?= formatCurrency($s['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Receiving Date</label>
                        <input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card-rms p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0">Items to Receive</h5>
                    <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" onclick="addPoRow()">
                        <i class="bi bi-plus-lg me-1"></i> Add Line
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="poTable">
                        <thead class="table-light small fw-bold text-uppercase">
                            <tr>
                                <th style="width: 40%;">Ingredient</th>
                                <th style="width: 20%;">Qty Received</th>
                                <th style="width: 15%;">Unit</th>
                                <th style="width: 20%;" class="text-end">Unit Price (Rs.)</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="poItemsBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Notes -->
            <div class="card-rms p-4">
                <label class="form-label small fw-semibold text-muted text-uppercase">Receiving Remarks / Invoice Reference</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Supplier invoice number, driver notes..."></textarea>
            </div>
        </div>

        <!-- Right Summary & Payment Allocation -->
        <div class="col-12 col-lg-4">
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Purchase Financials</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal:</span>
                    <span class="fw-bold" id="poSubtotalDisplay">Rs. 0.00</span>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Landed Freight / Shipping (Rs.)</label>
                    <input type="number" name="shipping_cost" id="poShippingInput" class="form-control" value="0.00" step="0.01" oninput="calcPoTotals()">
                </div>

                <div class="d-flex justify-content-between fs-5 fw-bold text-danger border-top pt-2 mb-3">
                    <span>Grand Total:</span>
                    <span id="poGrandTotalDisplay">Rs. 0.00</span>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Amount Paid Today (Rs.)</label>
                    <input type="number" name="paid_amount" id="poPaidInput" class="form-control fw-bold" value="0.00" step="0.01" oninput="calcPoTotals()">
                    <small class="text-muted">Remaining will be credited to supplier balance.</small>
                </div>

                <div class="d-flex justify-content-between text-muted small border-top pt-2">
                    <span>Payable Balance:</span>
                    <span class="fw-bold text-danger" id="poPayableDisplay">Rs. 0.00</span>
                </div>
            </div>

            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow">
                <i class="bi bi-box-arrow-in-down me-1"></i> Receive & Post to Inventory
            </button>
        </div>
    </div>
</form>

<script>
const ingredientsList = <?= json_encode($allIngredients) ?>;
const unitsList = <?= json_encode($allUnits) ?>;

function addPoRow() {
    const tbody = document.getElementById('poItemsBody');
    const tr = document.createElement('tr');
    tr.className = 'po-row';
    tr.innerHTML = `
        <td>
            <select name="ingredient_ids[]" class="form-select form-select-sm po-ing-select" onchange="onPoIngChange(this)" required>
                <option value="">-- Choose Ingredient --</option>
                ${ingredientsList.map(i => `<option value="${i.id}" data-cost="${i.cost_per_unit}" data-unit="${i.unit_id}">${i.name} (Last: Rs. ${parseFloat(i.cost_per_unit).toFixed(2)})</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="number" name="quantities[]" class="form-control form-control-sm po-qty" step="0.0001" placeholder="0.00" oninput="calcPoTotals()" required>
        </td>
        <td>
            <select name="unit_ids[]" class="form-select form-select-sm po-unit">
                ${unitsList.map(u => `<option value="${u.id}">${u.code}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="number" name="unit_prices[]" class="form-control form-control-sm text-end po-price" step="0.01" placeholder="0.00" oninput="calcPoTotals()" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('tr').remove(); calcPoTotals();">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function onPoIngChange(select) {
    const opt = select.options[select.selectedIndex];
    const unitId = opt.getAttribute('data-unit');
    const cost = opt.getAttribute('data-cost');
    const row = select.closest('tr');
    if (unitId) row.querySelector('.po-unit').value = unitId;
    if (cost) row.querySelector('.po-price').value = parseFloat(cost).toFixed(2);
    calcPoTotals();
}

function calcPoTotals() {
    let subtotal = 0.00;
    document.querySelectorAll('.po-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.po-qty').value) || 0;
        const price = parseFloat(row.querySelector('.po-price').value) || 0;
        subtotal += (qty * price);
    });

    const shipping = parseFloat(document.getElementById('poShippingInput').value) || 0;
    const grand = subtotal + shipping;
    const paid = parseFloat(document.getElementById('poPaidInput').value) || 0;
    const payable = Math.max(0, grand - paid);

    document.getElementById('poSubtotalDisplay').innerText = RMS.formatMoney(subtotal);
    document.getElementById('poGrandTotalDisplay').innerText = RMS.formatMoney(grand);
    document.getElementById('poPayableDisplay').innerText = RMS.formatMoney(payable);
}

document.addEventListener('DOMContentLoaded', () => {
    addPoRow();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
