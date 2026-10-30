-- Complete Restaurant Management System Database Schema
-- Localhost & Production Compatible, MySQL InnoDB, UTF8MB4

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Businesses and Branches
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notification_logs`;
DROP TABLE IF EXISTS `backup_logs`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `printer_configurations`;
DROP TABLE IF EXISTS `journal_entry_lines`;
DROP TABLE IF EXISTS `journal_entries`;
DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `fiscal_periods`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `expense_categories`;
DROP TABLE IF EXISTS `payroll_items`;
DROP TABLE IF EXISTS `payrolls`;
DROP TABLE IF EXISTS `employee_advances`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `cash_movements`;
DROP TABLE IF EXISTS `cash_shifts`;
DROP TABLE IF EXISTS `cash_registers`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `delivery_assignments`;
DROP TABLE IF EXISTS `riders`;
DROP TABLE IF EXISTS `delivery_zones`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `waiting_list`;
DROP TABLE IF EXISTS `customer_ledger`;
DROP TABLE IF EXISTS `loyalty_transactions`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `refund_items`;
DROP TABLE IF EXISTS `refunds`;
DROP TABLE IF EXISTS `payment_allocations`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `payment_methods`;
DROP TABLE IF EXISTS `kitchen_ticket_items`;
DROP TABLE IF EXISTS `kitchen_tickets`;
DROP TABLE IF EXISTS `kitchen_stations`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_item_modifiers`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `order_types`;
DROP TABLE IF EXISTS `restaurant_tables`;
DROP TABLE IF EXISTS `dining_areas`;
DROP TABLE IF EXISTS `stock_transfer_items`;
DROP TABLE IF EXISTS `stock_transfers`;
DROP TABLE IF EXISTS `stock_count_items`;
DROP TABLE IF EXISTS `stock_counts`;
DROP TABLE IF EXISTS `stock_wastage`;
DROP TABLE IF EXISTS `stock_batches`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `supplier_payments`;
DROP TABLE IF EXISTS `supplier_ledger`;
DROP TABLE IF EXISTS `goods_receipt_items`;
DROP TABLE IF EXISTS `goods_receipts`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `recipe_ingredients`;
DROP TABLE IF EXISTS `recipes`;
DROP TABLE IF EXISTS `unit_conversions`;
DROP TABLE IF EXISTS `units`;
DROP TABLE IF EXISTS `ingredients`;
DROP TABLE IF EXISTS `promotions`;
DROP TABLE IF EXISTS `menu_item_modifier_groups`;
DROP TABLE IF EXISTS `menu_category_modifier_groups`;
DROP TABLE IF EXISTS `modifiers`;
DROP TABLE IF EXISTS `modifier_groups`;
DROP TABLE IF EXISTS `menu_variants`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `menu_categories`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `businesses`;

-- BUSINESSES
CREATE TABLE `businesses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `tagline` VARCHAR(255) NULL,
    `tax_number` VARCHAR(50) NULL,
    `currency_symbol` VARCHAR(10) NOT NULL DEFAULT 'Rs.',
    `currency_code` VARCHAR(10) NOT NULL DEFAULT 'PKR',
    `phone` VARCHAR(50) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `logo_url` VARCHAR(255) NULL,
    `website` VARCHAR(150) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BRANCHES
CREATE TABLE `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `receipt_header` TEXT NULL,
    `receipt_footer` TEXT NULL,
    `default_tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `default_service_charge` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `is_main` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ROLES
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PERMISSIONS
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `code` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ROLE PERMISSIONS
CREATE TABLE `role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USERS
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `role_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NULL,
    `phone` VARCHAR(50) NULL,
    `pin_code` VARCHAR(10) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SYSTEM SETTINGS
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `data_type` ENUM('string','integer','decimal','boolean','json') NOT NULL DEFAULT 'string',
    `description` VARCHAR(255) NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DINING AREAS
CREATE TABLE `dining_areas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RESTAURANT TABLES
CREATE TABLE `restaurant_tables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `dining_area_id` INT NOT NULL,
    `table_number` VARCHAR(50) NOT NULL,
    `capacity` INT NOT NULL DEFAULT 4,
    `shape` ENUM('round','square','rectangle') NOT NULL DEFAULT 'square',
    `status` ENUM('available','occupied','reserved','order_ready','cleaning','unavailable') NOT NULL DEFAULT 'available',
    `current_order_id` INT NULL,
    `service_charge_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`dining_area_id`) REFERENCES `dining_areas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER TYPES
CREATE TABLE `order_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `requires_table` TINYINT(1) NOT NULL DEFAULT 0,
    `requires_customer` TINYINT(1) NOT NULL DEFAULT 0,
    `requires_delivery` TINYINT(1) NOT NULL DEFAULT 0,
    `default_service_charge` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KITCHEN STATIONS
CREATE TABLE `kitchen_stations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `printer_name` VARCHAR(150) NULL,
    `display_color` VARCHAR(20) NOT NULL DEFAULT '#0d6efd',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MENU CATEGORIES
CREATE TABLE `menu_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NULL,
    `kitchen_station_id` INT NULL,
    `icon_class` VARCHAR(50) NOT NULL DEFAULT 'bi-egg-fried',
    `color_hex` VARCHAR(20) NOT NULL DEFAULT '#6c757d',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`kitchen_station_id`) REFERENCES `kitchen_stations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MENU ITEMS
CREATE TABLE `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `kitchen_station_id` INT NULL,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NULL,
    `barcode` VARCHAR(50) NULL,
    `description` TEXT NULL,
    `image_url` VARCHAR(255) NULL,
    `base_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_estimate` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `prep_time_minutes` INT NOT NULL DEFAULT 15,
    `is_veg` TINYINT(1) NOT NULL DEFAULT 0,
    `is_spicy` TINYINT(1) NOT NULL DEFAULT 0,
    `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `menu_categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`kitchen_station_id`) REFERENCES `kitchen_stations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MENU VARIANTS
CREATE TABLE `menu_variants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_item_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `sku` VARCHAR(50) NULL,
    `additional_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `final_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_estimate` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MODIFIER GROUPS
CREATE TABLE `modifier_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `min_selection` INT NOT NULL DEFAULT 0,
    `max_selection` INT NOT NULL DEFAULT 1,
    `is_required` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MODIFIERS
CREATE TABLE `modifiers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_estimate` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`group_id`) REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MENU ITEM MODIFIER GROUPS (DIRECT ITEM ASSIGNMENT)
CREATE TABLE `menu_item_modifier_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_item_id` INT NOT NULL,
    `modifier_group_id` INT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_item_group` (`menu_item_id`, `modifier_group_id`),
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MENU CATEGORY MODIFIER GROUPS (CATEGORY-WIDE INHERITANCE)
CREATE TABLE `menu_category_modifier_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `modifier_group_id` INT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_cat_group` (`category_id`, `modifier_group_id`),
    FOREIGN KEY (`category_id`) REFERENCES `menu_categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UNITS OF MEASUREMENT
CREATE TABLE `units` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `category` ENUM('weight','volume','piece','packaging','other') NOT NULL DEFAULT 'weight',
    `is_base_unit` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UNIT CONVERSIONS
CREATE TABLE `unit_conversions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `from_unit_id` INT NOT NULL,
    `to_unit_id` INT NOT NULL,
    `multiplier` DECIMAL(14,6) NOT NULL,
    FOREIGN KEY (`from_unit_id`) REFERENCES `units`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_unit_id`) REFERENCES `units`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- INGREDIENTS
CREATE TABLE `ingredients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `category` VARCHAR(50) NOT NULL DEFAULT 'raw',
    `unit_id` INT NOT NULL,
    `cost_per_unit` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `current_stock` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `min_stock_alert` DECIMAL(14,4) NOT NULL DEFAULT 5.0000,
    `reorder_level` DECIMAL(14,4) NOT NULL DEFAULT 10.0000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RECIPES
CREATE TABLE `recipes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_item_id` INT NOT NULL,
    `menu_variant_id` INT NULL,
    `yield_quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `prep_loss_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `instructions` TEXT NULL,
    `total_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `version` INT NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_variant_id`) REFERENCES `menu_variants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RECIPE INGREDIENTS
CREATE TABLE `recipe_ingredients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipe_id` INT NOT NULL,
    `ingredient_id` INT NOT NULL,
    `quantity` DECIMAL(12,4) NOT NULL,
    `unit_id` INT NOT NULL,
    `cost_snapshot` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `is_packaging` TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (`recipe_id`) REFERENCES `recipes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SUPPLIERS
CREATE TABLE `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PURCHASE ORDERS
CREATE TABLE `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `supplier_id` INT NOT NULL,
    `po_number` VARCHAR(50) NOT NULL UNIQUE,
    `po_date` DATE NOT NULL,
    `expected_delivery_date` DATE NULL,
    `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `shipping_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft','approved','received','partial','cancelled') NOT NULL DEFAULT 'draft',
    `notes` TEXT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PURCHASE ORDER ITEMS
CREATE TABLE `purchase_order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT NOT NULL,
    `ingredient_id` INT NOT NULL,
    `quantity_ordered` DECIMAL(12,4) NOT NULL,
    `quantity_received` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `unit_id` INT NOT NULL,
    `unit_price` DECIMAL(12,4) NOT NULL,
    `total_price` DECIMAL(14,2) NOT NULL,
    `batch_number` VARCHAR(50) NULL,
    `expiry_date` DATE NULL,
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK MOVEMENTS (Immutable Ledger)
CREATE TABLE `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `ingredient_id` INT NOT NULL,
    `movement_type` ENUM('purchase_in','sale_consumption','wastage','transfer_in','transfer_out','adjustment_in','adjustment_out') NOT NULL,
    `reference_type` VARCHAR(50) NOT NULL,
    `reference_id` INT NULL,
    `quantity` DECIMAL(14,4) NOT NULL,
    `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `balance_after` DECIMAL(14,4) NOT NULL,
    `notes` VARCHAR(255) NULL,
    `user_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK WASTAGE
CREATE TABLE `stock_wastage` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `ingredient_id` INT NOT NULL,
    `quantity` DECIMAL(12,4) NOT NULL,
    `unit_id` INT NOT NULL,
    `cost_per_unit` DECIMAL(12,4) NOT NULL,
    `total_cost` DECIMAL(14,2) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `notes` TEXT NULL,
    `recorded_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK TRANSFERS
CREATE TABLE `stock_transfers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transfer_number` VARCHAR(50) NOT NULL UNIQUE,
    `from_branch_id` INT NOT NULL,
    `to_branch_id` INT NOT NULL,
    `status` ENUM('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `dispatched_by` INT NOT NULL,
    `received_by` INT NULL,
    `dispatched_at` DATETIME NULL,
    `received_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`from_branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`to_branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`dispatched_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK TRANSFER ITEMS
CREATE TABLE `stock_transfer_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transfer_id` INT NOT NULL,
    `ingredient_id` INT NOT NULL,
    `quantity` DECIMAL(12,4) NOT NULL,
    `unit_id` INT NOT NULL,
    `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK COUNTS (Stocktaking)
CREATE TABLE `stock_counts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `count_date` DATE NOT NULL,
    `notes` TEXT NULL,
    `counted_by` INT NOT NULL,
    `status` ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`counted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK COUNT ITEMS
CREATE TABLE `stock_count_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_count_id` INT NOT NULL,
    `ingredient_id` INT NOT NULL,
    `system_quantity` DECIMAL(12,4) NOT NULL,
    `counted_quantity` DECIMAL(12,4) NOT NULL,
    `variance` DECIMAL(12,4) NOT NULL,
    `unit_cost` DECIMAL(12,4) NOT NULL,
    `variance_cost` DECIMAL(14,2) NOT NULL,
    `notes` VARCHAR(255) NULL,
    FOREIGN KEY (`stock_count_id`) REFERENCES `stock_counts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CUSTOMERS
CREATE TABLE `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `delivery_notes` TEXT NULL,
    `credit_limit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `loyalty_points` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CUSTOMER LEDGER
CREATE TABLE `customer_ledger` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `transaction_type` ENUM('sale_credit','payment_receipt','refund','opening_balance') NOT NULL,
    `reference_type` VARCHAR(50) NOT NULL,
    `reference_id` INT NULL,
    `debit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SUPPLIER LEDGER
CREATE TABLE `supplier_ledger` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_id` INT NOT NULL,
    `transaction_type` ENUM('purchase_invoice','payment','purchase_return','opening_balance') NOT NULL,
    `reference_type` VARCHAR(50) NOT NULL,
    `reference_id` INT NULL,
    `debit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DELIVERY ZONES
CREATE TABLE `delivery_zones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `coverage_details` TEXT NULL,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estimated_minutes` INT NOT NULL DEFAULT 35,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RIDERS
CREATE TABLE `riders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `vehicle_number` VARCHAR(50) NULL,
    `status` ENUM('available','on_delivery','offline') NOT NULL DEFAULT 'available',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CASH REGISTERS
CREATE TABLE `cash_registers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CASH SHIFTS (Shift Opening / Closing)
CREATE TABLE `cash_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `register_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `shift_number` VARCHAR(50) NOT NULL UNIQUE,
    `opened_at` DATETIME NOT NULL,
    `closed_at` DATETIME NULL,
    `opening_cash` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `closing_cash_actual` DECIMAL(14,2) NULL,
    `closing_cash_expected` DECIMAL(14,2) NULL,
    `cash_difference` DECIMAL(14,2) NULL,
    `total_cash_sales` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total_card_sales` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total_digital_sales` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total_refunds` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total_cash_paid_out` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
    `notes` TEXT NULL,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`register_id`) REFERENCES `cash_registers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PROMOTIONS / COUPONS
CREATE TABLE `promotions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `coupon_code` VARCHAR(50) NULL UNIQUE,
    `discount_type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDERS
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `shift_id` INT NULL,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `order_type_id` INT NOT NULL,
    `table_id` INT NULL,
    `customer_id` INT NULL,
    `customer_name` VARCHAR(150) NULL,
    `customer_phone` VARCHAR(50) NULL,
    `waiter_id` INT NULL,
    `cashier_id` INT NOT NULL,
    `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `discount_reason` VARCHAR(255) NULL,
    `tax_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `service_charge_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `service_charge_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `order_status` ENUM('draft','confirmed','sent_to_kitchen','preparing','ready','served','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'confirmed',
    `kitchen_status` ENUM('pending','preparing','ready','completed') NOT NULL DEFAULT 'pending',
    `payment_status` ENUM('unpaid','partially_paid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    `delivery_status` ENUM('none','assigned','picked_up','delivered','failed') NOT NULL DEFAULT 'none',
    `notes` TEXT NULL,
    `cancellation_reason` VARCHAR(255) NULL,
    `cancelled_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`shift_id`) REFERENCES `cash_shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`order_type_id`) REFERENCES `order_types`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER ITEMS
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `menu_item_id` INT NOT NULL,
    `menu_variant_id` INT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `variant_name` VARCHAR(100) NULL,
    `kitchen_station_id` INT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `kitchen_status` ENUM('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
    `special_instructions` VARCHAR(255) NULL,
    `is_amendment` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`menu_variant_id`) REFERENCES `menu_variants`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`kitchen_station_id`) REFERENCES `kitchen_stations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER ITEM MODIFIERS
CREATE TABLE `order_item_modifiers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_item_id` INT NOT NULL,
    `modifier_id` INT NOT NULL,
    `modifier_name` VARCHAR(100) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_id`) REFERENCES `modifiers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER STATUS HISTORY
CREATE TABLE `order_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KITCHEN TICKETS (KOT)
CREATE TABLE `kitchen_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
    `order_id` INT NOT NULL,
    `kitchen_station_id` INT NOT NULL,
    `ticket_type` ENUM('initial','amendment','cancelled') NOT NULL DEFAULT 'initial',
    `status` ENUM('pending','preparing','ready','served') NOT NULL DEFAULT 'pending',
    `printed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`kitchen_station_id`) REFERENCES `kitchen_stations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KITCHEN TICKET ITEMS
CREATE TABLE `kitchen_ticket_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kitchen_ticket_id` INT NOT NULL,
    `order_item_id` INT NOT NULL,
    `status` ENUM('pending','preparing','ready','served') NOT NULL DEFAULT 'pending',
    `ready_at` DATETIME NULL,
    FOREIGN KEY (`kitchen_ticket_id`) REFERENCES `kitchen_tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAYMENT METHODS
CREATE TABLE `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `account_id` INT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAYMENTS
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `shift_id` INT NULL,
    `order_id` INT NOT NULL,
    `payment_method_id` INT NOT NULL,
    `payment_number` VARCHAR(50) NOT NULL UNIQUE,
    `amount` DECIMAL(14,2) NOT NULL,
    `tendered_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `change_returned` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `reference_number` VARCHAR(100) NULL,
    `received_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`shift_id`) REFERENCES `cash_shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REFUNDS
CREATE TABLE `refunds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `refund_number` VARCHAR(50) NOT NULL UNIQUE,
    `order_id` INT NOT NULL,
    `payment_id` INT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `authorized_by` INT NOT NULL,
    `refund_method_id` INT NOT NULL,
    `restore_inventory` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`authorized_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`refund_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DELIVERY ASSIGNMENTS
CREATE TABLE `delivery_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `rider_id` INT NOT NULL,
    `zone_id` INT NULL,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('assigned','picked_up','delivered','failed') NOT NULL DEFAULT 'assigned',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `picked_up_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `cash_collected` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `cash_handed_over` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`rider_id`) REFERENCES `riders`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RESERVATIONS
CREATE TABLE `reservations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `customer_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `reservation_date` DATE NOT NULL,
    `arrival_time` TIME NOT NULL,
    `guests_count` INT NOT NULL DEFAULT 2,
    `dining_area_id` INT NULL,
    `table_id` INT NULL,
    `special_requests` TEXT NULL,
    `deposit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending','confirmed','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'confirmed',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`dining_area_id`) REFERENCES `dining_areas`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WAITING LIST
CREATE TABLE `waiting_list` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `customer_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `party_size` INT NOT NULL DEFAULT 2,
    `status` ENUM('waiting','called','seated','cancelled') NOT NULL DEFAULT 'waiting',
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EXPENSE CATEGORIES
CREATE TABLE `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `account_id` INT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EXPENSES
CREATE TABLE `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `category_id` INT NOT NULL,
    `expense_number` VARCHAR(50) NOT NULL UNIQUE,
    `expense_date` DATE NOT NULL,
    `payee` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `payment_method_id` INT NOT NULL,
    `account_id` INT NULL,
    `description` TEXT NOT NULL,
    `receipt_attachment` VARCHAR(255) NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CHART OF ACCOUNTS (Double-Entry Engine)
CREATE TABLE `accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `account_type` ENUM('asset','liability','equity','revenue','cogs','expense') NOT NULL,
    `sub_type` VARCHAR(50) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `current_balance` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- JOURNAL ENTRIES
CREATE TABLE `journal_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `entry_number` VARCHAR(50) NOT NULL UNIQUE,
    `entry_date` DATE NOT NULL,
    `reference_type` VARCHAR(50) NOT NULL,
    `reference_id` INT NULL,
    `description` TEXT NOT NULL,
    `total_debit` DECIMAL(16,2) NOT NULL,
    `total_credit` DECIMAL(16,2) NOT NULL,
    `is_reversed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- JOURNAL ENTRY LINES
CREATE TABLE `journal_entry_lines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `journal_entry_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `debit` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    `credit` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) NULL,
    FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EMPLOYEES
CREATE TABLE `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `user_id` INT NULL,
    `employee_code` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `joining_date` DATE NOT NULL,
    `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `salary_type` ENUM('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ATTENDANCE
CREATE TABLE `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `clock_in` TIME NULL,
    `clock_out` TIME NULL,
    `status` ENUM('present','absent','late','half_day','leave') NOT NULL DEFAULT 'present',
    `notes` VARCHAR(255) NULL,
    UNIQUE KEY `emp_date` (`employee_id`, `date`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAYROLL
CREATE TABLE `payrolls` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `employee_id` INT NOT NULL,
    `month_year` VARCHAR(10) NOT NULL,
    `basic_salary` DECIMAL(12,2) NOT NULL,
    `allowances` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_salary` DECIMAL(12,2) NOT NULL,
    `payment_method_id` INT NULL,
    `payment_date` DATE NULL,
    `status` ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRINTER CONFIGURATIONS
CREATE TABLE `printer_configurations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `printer_type` ENUM('receipt_58','receipt_80','a4','kitchen') NOT NULL DEFAULT 'receipt_80',
    `paper_width_mm` INT NOT NULL DEFAULT 80,
    `station_id` INT NULL,
    `footer_text` VARCHAR(255) NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AUDIT LOGS
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) NOT NULL,
    `record_id` INT NULL,
    `old_values` LONGTEXT NULL,
    `new_values` LONGTEXT NULL,
    `ip_address` VARCHAR(50) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
