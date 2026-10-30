<?php
require_once __DIR__ . '/../config/app.php';

$db = getDB();

echo "Running Modifier Relationship Migration...\n";

// 1. Create menu_item_modifier_groups table
$db->exec("
CREATE TABLE IF NOT EXISTS `menu_item_modifier_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_item_id` INT NOT NULL,
    `modifier_group_id` INT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_item_group` (`menu_item_id`, `modifier_group_id`),
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 2. Create menu_category_modifier_groups table
$db->exec("
CREATE TABLE IF NOT EXISTS `menu_category_modifier_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `modifier_group_id` INT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_cat_group` (`category_id`, `modifier_group_id`),
    FOREIGN KEY (`category_id`) REFERENCES `menu_categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 3. Seed category modifier groups relationships
// Category 1: Burgers & Sandwiches -> Cheesy Add-ons (1), Gourmet Dips (2)
// Category 2: Artisan Pizza -> Crust Type (3), Gourmet Dips (2)
// Category 3: Charcoal BBQ & Grills -> Gourmet Dips (2)
// Category 4: Sides & Finger Foods -> Cheesy Add-ons (1), Gourmet Dips (2)
// Category 5: Chilled Beverages -> None
// Category 6: Bakery Desserts -> None

$categoryLinks = [
    ['category_id' => 1, 'modifier_group_id' => 1, 'sort_order' => 1], // Burgers -> Cheesy Add-ons
    ['category_id' => 1, 'modifier_group_id' => 2, 'sort_order' => 2], // Burgers -> Gourmet Dips
    ['category_id' => 2, 'modifier_group_id' => 3, 'sort_order' => 1], // Pizza -> Crust Type
    ['category_id' => 2, 'modifier_group_id' => 2, 'sort_order' => 2], // Pizza -> Gourmet Dips
    ['category_id' => 3, 'modifier_group_id' => 2, 'sort_order' => 1], // BBQ -> Gourmet Dips
    ['category_id' => 4, 'modifier_group_id' => 1, 'sort_order' => 1], // Sides -> Cheesy Add-ons
    ['category_id' => 4, 'modifier_group_id' => 2, 'sort_order' => 2], // Sides -> Gourmet Dips
];

$stmt = $db->prepare("INSERT IGNORE INTO `menu_category_modifier_groups` (`category_id`, `modifier_group_id`, `sort_order`) VALUES (?, ?, ?)");
foreach ($categoryLinks as $link) {
    $stmt->execute([$link['category_id'], $link['modifier_group_id'], $link['sort_order']]);
}

echo "Migration completed successfully!\n";
