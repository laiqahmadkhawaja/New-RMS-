<?php
// modules/purchasing/index.php - Purchases & Supplier Operations
$pageTitle = 'Purchases & Suppliers';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('purchasing.manage');

$db = getDB();
$branchId = currentBranchId();

$pos = $db->query("
    SELECT po.*, s.name as supplier_name, s.phone as supplier_phone, u.full_name as creator_name,
           (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = po.id) as items_count 
    FROM purchase_orders po 
    JOIN suppliers s ON po.supplier_id = s.id 
    JOIN users u ON po.created_by = u.id 
    WHERE po.branch_id = $branchId 
    ORDER BY po.id DESC LIMIT 100
")->fetchAll();

$suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$totalPayables = array_sum(array_column($suppliers, 'current_balance'));
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-truck text-danger me-2"></i> Purchases & Suppliers</h3>
        <p class="text-muted small mb-0">Total Outstanding Accounts Payable (Suppliers): <b class="text-danger fs-6"><?= formatCurrency($totalPayables) ?></b></p>
    </div>

    <!-- Actions (Dedicated pages per PRD 25.3) -->
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/purchasing/suppliers.php" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-people me-1"></i> Supplier Directory
        </a>
        <a href="<?= BASE_URL ?>modules/purchasing/create.php" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-plus-circle me-1"></i> New Purchase Order
        </a>
    </div>
</div>

<!-- Purchase Orders List Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>PO Number</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Items</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Paid Amount</th>
                    <th class="text-end">Balance Due</th>
                    <th>Status</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pos)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No purchase orders recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($pos as $p): 
                        $due = (float)$p['grand_total'] - (float)$p['paid_amount'];
                    ?>
                        <tr>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($p['po_number']) ?></td>
                            <td class="small text-muted"><?= date('d-M-Y', strtotime($p['po_date'])) ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($p['supplier_name']) ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($p['supplier_phone']) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= $p['items_count'] ?> line items</span></td>
                            <td class="text-end fw-bold"><?= formatCurrency($p['grand_total']) ?></td>
                            <td class="text-end text-success fw-semibold"><?= formatCurrency($p['paid_amount']) ?></td>
                            <td class="text-end fw-bold <?= $due > 0 ? 'text-danger' : 'text-muted' ?>">
                                <?= formatCurrency($due) ?>
                            </td>
                            <td>
                                <span class="badge <?php
                                    echo match($p['status']) {
                                        'received' => 'bg-success',
                                        'approved' => 'bg-primary',
                                        'partial' => 'bg-warning text-dark',
                                        default => 'bg-secondary'
                                    };
                                ?> text-capitalize"><?= $p['status'] ?></span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($p['creator_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
