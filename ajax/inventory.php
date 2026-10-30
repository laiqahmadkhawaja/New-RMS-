<?php
// ajax/inventory.php - Inventory & Stock Operations API
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory_engine.php';
require_once __DIR__ . '/../includes/accounting_engine.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. SAVE INGREDIENT (Create/Update)
if ($action === 'save_ingredient') {
    requirePermission('inventory.view');
    $id = (int)($_POST['ingredient_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $category = trim($_POST['category'] ?? 'raw');
    $unitId = (int)($_POST['unit_id'] ?? 1);
    $cost = (float)($_POST['cost_per_unit'] ?? 0);
    $stock = (float)($_POST['current_stock'] ?? 0);
    $minAlert = (float)($_POST['min_stock_alert'] ?? 5);
    $reorder = (float)($_POST['reorder_level'] ?? 10);

    if (empty($name) || empty($code)) {
        jsonResponse(['success' => false, 'message' => 'Ingredient name and code are required.'], 400);
    }

    try {
        if ($id) {
            $db->prepare("
                UPDATE ingredients 
                SET name = ?, code = ?, category = ?, unit_id = ?, cost_per_unit = ?, 
                    min_stock_alert = ?, reorder_level = ?, updated_at = NOW() 
                WHERE id = ?
            ")->execute([$name, $code, $category, $unitId, $cost, $minAlert, $reorder, $id]);
            logAudit('update_ingredient', 'ingredients', $id, null, ['name' => $name, 'cost' => $cost]);
        } else {
            $db->prepare("
                INSERT INTO ingredients (name, code, category, unit_id, cost_per_unit, current_stock, min_stock_alert, reorder_level)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $code, $category, $unitId, $cost, $stock, $minAlert, $reorder]);
            $id = (int)$db->lastInsertId();

            if ($stock > 0) {
                // Record opening stock movement
                $db->prepare("
                    INSERT INTO stock_movements (branch_id, ingredient_id, movement_type, reference_type, quantity, unit_cost, total_cost, balance_after, notes, user_id)
                    VALUES (?, ?, 'adjustment_in', 'opening_stock', ?, ?, ?, ?, 'Opening Stock Balance', ?)
                ")->execute([$branchId, $id, $stock, $cost, round($stock * $cost, 2), $stock, $userId]);
            }
            logAudit('create_ingredient', 'ingredients', $id, null, ['name' => $name, 'stock' => $stock]);
        }

        jsonResponse(['success' => true, 'message' => "Ingredient '$name' saved successfully."]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 2. RECORD WASTAGE
if ($action === 'record_wastage') {
    requirePermission('inventory.adjust');
    $ingId = (int)($_POST['ingredient_id'] ?? 0);
    $qty = (float)($_POST['quantity'] ?? 0);
    $unitId = (int)($_POST['unit_id'] ?? 1);
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$ingId || $qty <= 0 || empty($reason)) {
        jsonResponse(['success' => false, 'message' => 'Please provide ingredient, valid quantity, and reason.'], 400);
    }

    try {
        $db->beginTransaction();
        InventoryEngine::recordWastage($branchId, $ingId, $qty, $unitId, $reason, $notes, $userId);
        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Stock wastage recorded and written off to accounting successfully.']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// 3. MANUAL STOCK ADJUSTMENT (Physical Stock Reconciliation)
if ($action === 'adjust_stock') {
    requirePermission('inventory.adjust');
    $ingId = (int)($_POST['ingredient_id'] ?? 0);
    $actualCount = (float)($_POST['actual_count'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Physical stocktake adjustment');

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT current_stock, cost_per_unit FROM ingredients WHERE id = ?");
        $stmt->execute([$ingId]);
        $ing = $stmt->fetch();
        if (!$ing) throw new Exception("Ingredient not found.");

        $systemStock = (float)$ing['current_stock'];
        $diff = $actualCount - $systemStock;
        $unitCost = (float)$ing['cost_per_unit'];

        if (abs($diff) > 0.0001) {
            $movType = $diff > 0 ? 'adjustment_in' : 'adjustment_out';
            $db->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")->execute([$actualCount, $ingId]);

            $db->prepare("
                INSERT INTO stock_movements (branch_id, ingredient_id, movement_type, reference_type, quantity, unit_cost, total_cost, balance_after, notes, user_id)
                VALUES (?, ?, ?, 'manual_adjustment', ?, ?, ?, ?, ?, ?)
            ")->execute([$branchId, $ingId, $movType, $diff, $unitCost, round(abs($diff) * $unitCost, 2), $actualCount, $reason, $userId]);
        }

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Stock quantity adjusted to ' . $actualCount]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
