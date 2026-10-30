<?php
// modules/inventory/transfers.php - Inter-Branch Stock Transfers (PRD 22.3)
$pageTitle = 'Stock Transfers';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('inventory.view');

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();

// Handle new transfer creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_transfer'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $toBranchId = (int)post('to_branch_id');
        $ingId = (int)post('ingredient_id');
        $qty = (float)post('quantity');
        $notes = cleanInput(post('notes'));

        if ($toBranchId && $ingId && $qty > 0 && $toBranchId !== $branchId) {
            try {
                $db->beginTransaction();

                // Check source stock
                $stmt = $db->prepare("SELECT current_stock, cost_per_unit, unit_id FROM ingredients WHERE id = ?");
                $stmt->execute([$ingId]);
                $ing = $stmt->fetch();
                if (!$ing || (float)$ing['current_stock'] < $qty) {
                    throw new Exception("Insufficient stock in source branch.");
                }

                $transferNum = 'TRF-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

                $db->prepare("
                    INSERT INTO stock_transfers (transfer_number, from_branch_id, to_branch_id, status, notes, dispatched_by, dispatched_at)
                    VALUES (?, ?, ?, 'in_transit', ?, ?, NOW())
                ")->execute([$transferNum, $branchId, $toBranchId, $notes, $userId]);
                $transferId = (int)$db->lastInsertId();

                $db->prepare("
                    INSERT INTO stock_transfer_items (transfer_id, ingredient_id, quantity, unit_id, unit_cost)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$transferId, $ingId, $qty, $ing['unit_id'], $ing['cost_per_unit']]);

                // Deduct from source branch
                $newStock = (float)$ing['current_stock'] - $qty;
                $db->prepare("UPDATE ingredients SET current_stock = ? WHERE id = ?")->execute([$newStock, $ingId]);

                // Record transfer_out movement
                $db->prepare("
                    INSERT INTO stock_movements (branch_id, ingredient_id, movement_type, reference_type, reference_id, quantity, unit_cost, total_cost, balance_after, notes, user_id)
                    VALUES (?, ?, 'transfer_out', 'stock_transfers', ?, ?, ?, ?, ?, ?)
                ")->execute([$branchId, $ingId, $transferId, -$qty, $ing['cost_per_unit'], round($qty * $ing['cost_per_unit'], 2), $newStock, "Dispatched via Transfer #$transferNum", $userId]);

                $db->commit();
                setFlash('success', "Stock transfer #$transferNum dispatched and is now in-transit.");
                header('Location: ' . BASE_URL . 'modules/inventory/transfers.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', $e->getMessage());
            }
        }
    }
}

// Handle receiving transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_receive_transfer'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $trfId = (int)post('transfer_id');
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM stock_transfers WHERE id = ? AND status = 'in_transit'");
            $stmt->execute([$trfId]);
            $trf = $stmt->fetch();
            if (!$trf) throw new Exception("Transfer not found or already completed.");

            // Items in transfer
            $items = $db->query("SELECT * FROM stock_transfer_items WHERE transfer_id = $trfId")->fetchAll();
            foreach ($items as $it) {
                // Add stock to current branch
                $db->prepare("UPDATE ingredients SET current_stock = current_stock + ? WHERE id = ?")->execute([$it['quantity'], $it['ingredient_id']]);

                $balAfter = (float)$db->query("SELECT current_stock FROM ingredients WHERE id = " . $it['ingredient_id'])->fetchColumn();

                $db->prepare("
                    INSERT INTO stock_movements (branch_id, ingredient_id, movement_type, reference_type, reference_id, quantity, unit_cost, total_cost, balance_after, notes, user_id)
                    VALUES (?, ?, 'transfer_in', 'stock_transfers', ?, ?, ?, ?, ?, ?)
                ")->execute([$branchId, $it['ingredient_id'], $trfId, $it['quantity'], $it['unit_cost'], round($it['quantity'] * $it['unit_cost'], 2), $balAfter, "Received via Transfer #" . $trf['transfer_number'], $userId]);
            }

            $db->prepare("UPDATE stock_transfers SET status = 'completed', received_by = ?, received_at = NOW() WHERE id = ?")->execute([$userId, $trfId]);

            $db->commit();
            setFlash('success', "Transfer #" . $trf['transfer_number'] . " received and added to inventory.");
            header('Location: ' . BASE_URL . 'modules/inventory/transfers.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', $e->getMessage());
        }
    }
}

$transfers = $db->query("
    SELECT st.*, fb.name as from_branch, tb.name as to_branch, u.full_name as dispatcher_name 
    FROM stock_transfers st 
    JOIN branches fb ON st.from_branch_id = fb.id 
    JOIN branches tb ON st.to_branch_id = tb.id 
    JOIN users u ON st.dispatched_by = u.id 
    WHERE st.from_branch_id = $branchId OR st.to_branch_id = $branchId 
    ORDER BY st.id DESC
")->fetchAll();

$branches = $db->query("SELECT * FROM branches WHERE is_active = 1 AND id != $branchId")->fetchAll();
$ingredients = $db->query("SELECT i.*, u.code as unit_code FROM ingredients i JOIN units u ON i.unit_id = u.id WHERE i.is_active = 1 ORDER BY i.name ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-arrow-left-right text-danger me-2"></i> Inter-Branch Stock Transfers</h3>
        <p class="text-muted small mb-0">Dispatch and receive stock between branch locations without double-counting inventory</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Stock Overview
        </a>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="RMS.openSlidePanel('newTransferPanel')">
            <i class="bi bi-send me-1"></i> Dispatch Transfer
        </button>
    </div>
</div>

<!-- Transfers List -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Transfer #</th>
                    <th>Date</th>
                    <th>From Branch</th>
                    <th>To Branch</th>
                    <th>Status</th>
                    <th>Dispatched By</th>
                    <th>Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No stock transfers recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($transfers as $tr): ?>
                        <tr>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($tr['transfer_number']) ?></td>
                            <td class="small text-muted"><?= date('d-M-Y H:i', strtotime($tr['created_at'])) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($tr['from_branch']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($tr['to_branch']) ?></td>
                            <td>
                                <span class="badge <?php
                                    echo match($tr['status']) {
                                        'in_transit' => 'bg-warning text-dark',
                                        'completed' => 'bg-success',
                                        default => 'bg-secondary'
                                    };
                                ?> text-capitalize"><?= str_replace('_', ' ', $tr['status']) ?></span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($tr['dispatcher_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($tr['notes'] ?? '-') ?></td>
                            <td class="text-end">
                                <?php if ($tr['status'] === 'in_transit' && $tr['to_branch_id'] == $branchId): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?= csrfField(); ?>
                                        <input type="hidden" name="action_receive_transfer" value="1">
                                        <input type="hidden" name="transfer_id" value="<?= $tr['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Confirm & Receive
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= SLIDE PANEL: DISPATCH TRANSFER ================= -->
<div id="newTransferPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0 text-danger">Dispatch Stock Transfer</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('newTransferPanel')"></button>
    </div>
    <form method="POST" action="" class="p-3 flex-grow-1 overflow-y-auto">
        <?= csrfField(); ?>
        <input type="hidden" name="action_create_transfer" value="1">

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Destination Branch <span class="text-danger">*</span></label>
            <select name="to_branch_id" class="form-select" required>
                <option value="">-- Choose Target Branch --</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Ingredient to Transfer <span class="text-danger">*</span></label>
            <select name="ingredient_id" class="form-select" required>
                <option value="">-- Choose Ingredient --</option>
                <?php foreach ($ingredients as $i): ?>
                    <option value="<?= $i['id'] ?>">
                        <?= htmlspecialchars($i['name']) ?> (Available: <?= (float)$i['current_stock'] ?> <?= $i['unit_code'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Transfer Quantity <span class="text-danger">*</span></label>
            <input type="number" name="quantity" class="form-control fw-bold" step="0.0001" placeholder="0.00" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Transfer Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Driver details, delivery instructions..."></textarea>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-send me-1"></i> Dispatch Stock
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
