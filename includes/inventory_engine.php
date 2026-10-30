<?php
// includes/inventory_engine.php - Recipe-based Inventory Management & Stock Movements

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit.php';

class InventoryEngine {

    /**
     * Deduct inventory based on order items sold and their recipes
     */
    public static function consumeStockForOrder(int $orderId, int $branchId, int $userId): float {
        $db = getDB();
        $totalCogs = 0.00;

        // Fetch all items in this order
        $stmt = $db->prepare("
            SELECT oi.*, m.name as item_name 
            FROM order_items oi 
            JOIN menu_items m ON oi.menu_item_id = m.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'];
            $variantId = $item['menu_variant_id'];
            $qtySold = (float)$item['quantity'];

            // Find matching recipe
            $recStmt = $db->prepare("
                SELECT * FROM recipes 
                WHERE menu_item_id = ? 
                AND (menu_variant_id = ? OR menu_variant_id IS NULL) 
                AND is_active = 1 
                ORDER BY menu_variant_id DESC LIMIT 1
            ");
            $recStmt->execute([$menuItemId, $variantId]);
            $recipe = $recStmt->fetch();

            if (!$recipe) {
                continue; // No recipe configured for this item
            }

            // Recipe ingredients
            $ingStmt = $db->prepare("
                SELECT ri.*, i.name as ing_name, i.cost_per_unit, i.current_stock, i.unit_id as ing_unit_id 
                FROM recipe_ingredients ri 
                JOIN ingredients i ON ri.ingredient_id = i.id 
                WHERE ri.recipe_id = ?
            ");
            $ingStmt->execute([$recipe['id']]);
            $ingredients = $ingStmt->fetchAll();

            foreach ($ingredients as $ing) {
                $ingId = $ing['ingredient_id'];
                $recipeQty = (float)$ing['quantity'];
                $prepLoss = (float)$recipe['prep_loss_percent'];
                
                // Account for prep loss factor
                $lossFactor = 1 + ($prepLoss / 100);
                $totalQtyToDeduct = $recipeQty * $qtySold * $lossFactor;
                $unitCost = (float)$ing['cost_per_unit'];
                $itemCogs = round($totalQtyToDeduct * $unitCost, 2);
                $totalCogs += $itemCogs;

                // Update ingredient stock
                $currentStock = (float)$ing['current_stock'];
                $newStock = $currentStock - $totalQtyToDeduct;

                $upStmt = $db->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?");
                $upStmt->execute([$newStock, $ingId]);

                // Record immutable stock movement
                $movStmt = $db->prepare("
                    INSERT INTO stock_movements (
                        branch_id, ingredient_id, movement_type, reference_type, reference_id, 
                        quantity, unit_cost, total_cost, balance_after, notes, user_id, created_at
                    ) VALUES (?, ?, 'sale_consumption', 'orders', ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $movStmt->execute([
                    $branchId,
                    $ingId,
                    $orderId,
                    -$totalQtyToDeduct,
                    $unitCost,
                    $itemCogs,
                    $newStock,
                    "Consumption for Order #$orderId ({$item['item_name']} x $qtySold)",
                    $userId
                ]);
            }
        }

        return $totalCogs;
    }

    /**
     * Add inventory from received purchase order
     */
    public static function receivePurchaseStock(int $poId, int $branchId, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?");
        $stmt->execute([$poId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            $ingId = $item['ingredient_id'];
            $qtyReceived = (float)$item['quantity_ordered']; // or quantity_received
            $unitPrice = (float)$item['unit_price'];

            // Fetch current ingredient details
            $iStmt = $db->prepare("SELECT current_stock, cost_per_unit FROM ingredients WHERE id = ?");
            $iStmt->execute([$ingId]);
            $ing = $iStmt->fetch();

            if (!$ing) continue;

            $currentStock = (float)$ing['current_stock'];
            $oldCost = (float)$ing['cost_per_unit'];
            $newStock = $currentStock + $qtyReceived;

            // Weighted average cost calculation
            $newCost = $oldCost;
            if ($newStock > 0) {
                $newCost = (($currentStock * $oldCost) + ($qtyReceived * $unitPrice)) / $newStock;
            }

            $upStmt = $db->prepare("UPDATE ingredients SET current_stock = ?, cost_per_unit = ?, updated_at = NOW() WHERE id = ?");
            $upStmt->execute([$newStock, $newCost, $ingId]);

            // Mark received in PO item
            $db->prepare("UPDATE purchase_order_items SET quantity_received = ? WHERE id = ?")->execute([$qtyReceived, $item['id']]);

            // Log stock movement
            $db->prepare("
                INSERT INTO stock_movements (
                    branch_id, ingredient_id, movement_type, reference_type, reference_id, 
                    quantity, unit_cost, total_cost, balance_after, notes, user_id, created_at
                ) VALUES (?, ?, 'purchase_in', 'purchase_orders', ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $branchId,
                $ingId,
                $poId,
                $qtyReceived,
                $unitPrice,
                round($qtyReceived * $unitPrice, 2),
                $newStock,
                "Received via PO #$poId",
                $userId
            ]);
        }
    }

    /**
     * Record wastage / spoilage
     */
    public static function recordWastage(int $branchId, int $ingId, float $qty, int $unitId, string $reason, string $notes, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("SELECT current_stock, cost_per_unit FROM ingredients WHERE id = ?");
        $stmt->execute([$ingId]);
        $ing = $stmt->fetch();
        if (!$ing) throw new Exception("Ingredient not found.");

        $costPerUnit = (float)$ing['cost_per_unit'];
        $totalCost = round($qty * $costPerUnit, 2);
        $newStock = (float)$ing['current_stock'] - $qty;

        $db->prepare("UPDATE ingredients SET current_stock = ? WHERE id = ?")->execute([$newStock, $ingId]);

        // Insert stock wastage record
        $db->prepare("
            INSERT INTO stock_wastage (branch_id, ingredient_id, quantity, unit_id, cost_per_unit, total_cost, reason, notes, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$branchId, $ingId, $qty, $unitId, $costPerUnit, $totalCost, $reason, $notes, $userId]);

        $wastageId = $db->lastInsertId();

        // Movement
        $db->prepare("
            INSERT INTO stock_movements (
                branch_id, ingredient_id, movement_type, reference_type, reference_id, 
                quantity, unit_cost, total_cost, balance_after, notes, user_id, created_at
            ) VALUES (?, ?, 'wastage', 'stock_wastage', ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $branchId,
            $ingId,
            $wastageId,
            -$qty,
            $costPerUnit,
            $totalCost,
            $newStock,
            "Wastage: $reason",
            $userId
        ]);

        // Accounting entry for wastage
        require_once __DIR__ . '/accounting_engine.php';
        AccountingEngine::postWastageJournal($wastageId, $totalCost, $reason, $userId, $branchId);
    }
}
