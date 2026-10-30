-- Seed Data for Restaurant Management System

-- 1. BUSINESS PROFILE
INSERT INTO `businesses` (`id`, `name`, `tagline`, `tax_number`, `currency_symbol`, `currency_code`, `phone`, `email`, `address`, `website`)
VALUES (1, 'Spice & Savor Bistro', 'Artisanal Flavors & Fresh Hospitality', 'NTN-8947291-3', 'Rs.', 'PKR', '+92 300 1234567', 'info@spiceandsavor.com', 'Plot 42, Main Commercial Avenue, Phase 5', 'www.spiceandsavor.com');

-- 2. BRANCHES
INSERT INTO `branches` (`id`, `business_id`, `name`, `code`, `phone`, `email`, `address`, `receipt_header`, `receipt_footer`, `default_tax_rate`, `default_service_charge`, `is_main`, `is_active`)
VALUES 
(1, 1, 'Downtown Main Branch', 'DT01', '+92 300 1234567', 'downtown@spiceandsavor.com', 'Plot 42, Main Commercial Ave, Downtown', 'Spice & Savor Bistro - Downtown\nHotline: +92 300 1234567', 'Thank you for dining with us!\nPlease visit again.', 5.00, 2.50, 1, 1),
(2, 1, 'Uptown Express Branch', 'UP02', '+92 300 9876543', 'uptown@spiceandsavor.com', 'Shop 15, Mall Galleria, Uptown', 'Spice & Savor - Uptown Express', 'Thank you! Enjoy your meal.', 5.00, 0.00, 0, 1);

-- 3. ROLES
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `is_system`)
VALUES 
(1, 'super_admin', 'Super Admin', 'Full access to all system features and settings', 1),
(2, 'owner', 'Restaurant Owner', 'Executive dashboard, accounting, and reports access', 1),
(3, 'manager', 'Branch Manager', 'Manages branch operations, approvals, and daily closing', 1),
(4, 'cashier', 'Cashier', 'POS order taking, billing, payment processing, and shift closing', 1),
(5, 'waiter', 'Waiter / Server', 'Table order entry and status tracking', 1),
(6, 'chef', 'Kitchen Staff / Chef', 'Kitchen display system and ticket preparation', 1),
(7, 'inventory_manager', 'Inventory Manager', 'Stock management, recipes, suppliers, and purchase orders', 1),
(8, 'accountant', 'Accountant', 'General ledger, journal vouchers, expenses, and financial statements', 1);

-- 4. PERMISSIONS
INSERT INTO `permissions` (`id`, `module`, `code`, `name`, `description`)
VALUES
(1, 'pos', 'pos.view', 'Access POS', 'Allow opening POS terminal'),
(2, 'pos', 'pos.discount', 'Apply POS Discount', 'Allow applying discounts in POS'),
(3, 'pos', 'pos.price_override', 'Price Override', 'Allow custom pricing on order items'),
(4, 'orders', 'orders.view', 'View Orders', 'View order list and history'),
(5, 'orders', 'orders.cancel', 'Cancel Order', 'Cancel active or confirmed orders'),
(6, 'orders', 'orders.refund', 'Process Refund', 'Refund paid orders'),
(7, 'tables', 'tables.manage', 'Manage Tables', 'Manage dining areas and table layouts'),
(8, 'kitchen', 'kitchen.kds', 'Access KDS', 'View kitchen display screen'),
(9, 'menu', 'menu.manage', 'Manage Menu', 'Create/edit categories, items, and variants'),
(10, 'recipes', 'recipes.manage', 'Manage Recipes', 'Formulate ingredient recipes and costing'),
(11, 'inventory', 'inventory.view', 'View Inventory', 'View stock balances and movements'),
(12, 'inventory', 'inventory.adjust', 'Adjust Stock', 'Perform stock adjustments and wastage'),
(13, 'purchasing', 'purchasing.manage', 'Purchasing & Suppliers', 'Manage suppliers, POs, and receiving'),
(14, 'accounting', 'accounting.view', 'View Accounting', 'View financial reports and ledgers'),
(15, 'accounting', 'accounting.journals', 'Post Journals', 'Create manual journal entries'),
(16, 'expenses', 'expenses.manage', 'Manage Expenses', 'Record and approve operating expenses'),
(17, 'customers', 'customers.manage', 'Manage Customers', 'View customer list and credit balances'),
(18, 'delivery', 'delivery.manage', 'Manage Delivery', 'Dispatch riders and track deliveries'),
(19, 'reservations', 'reservations.manage', 'Manage Reservations', 'Book tables and manage waiting lists'),
(20, 'employees', 'employees.manage', 'Manage Employees', 'Staff directory, attendance, and payroll'),
(21, 'shifts', 'shifts.manage', 'Manage Shifts', 'Open, close, and reconcile cashier shifts'),
(22, 'reports', 'reports.view', 'View Reports', 'Access sales, profit, and stock reports'),
(23, 'settings', 'settings.manage', 'System Settings', 'Configure general and restaurant settings'),
(24, 'users', 'users.manage', 'User Administration', 'Create users and assign roles');

-- 5. ROLE PERMISSIONS (Super Admin gets all)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `module` IN ('pos','orders','tables','kitchen','menu','inventory','expenses','customers','delivery','reservations','shifts','reports');

-- Cashier permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` WHERE `code` IN ('pos.view', 'pos.discount', 'orders.view', 'customers.manage', 'shifts.manage', 'delivery.manage', 'reservations.manage');

-- Waiter permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` WHERE `code` IN ('pos.view', 'orders.view', 'tables.manage');

-- Chef permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `code` IN ('kitchen.kds', 'orders.view');

-- 6. USERS (default password: admin123)
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `username`, `password_hash`, `full_name`, `email`, `phone`, `pin_code`, `is_active`)
VALUES 
(1, 1, 1, 'admin', '$2y$10$H00BKujdgi/Pg8hyXv/h2ed4aIrLSBSjGJ.0V60FwlCCx.1xrcJbG', 'System Administrator', 'admin@restaurant.local', '+92 300 0000000', '1234', 1),
(2, 1, 4, 'cashier1', '$2y$10$H00BKujdgi/Pg8hyXv/h2ed4aIrLSBSjGJ.0V60FwlCCx.1xrcJbG', 'Hamza Aslam (Cashier)', 'cashier@restaurant.local', '+92 300 1111111', '1111', 1),
(3, 1, 6, 'chef1', '$2y$10$H00BKujdgi/Pg8hyXv/h2ed4aIrLSBSjGJ.0V60FwlCCx.1xrcJbG', 'Chef Farhan (Head Chef)', 'chef@restaurant.local', '+92 300 2222222', '2222', 1),
(4, 1, 5, 'waiter1', '$2y$10$H00BKujdgi/Pg8hyXv/h2ed4aIrLSBSjGJ.0V60FwlCCx.1xrcJbG', 'Bilal Ahmed (Server)', 'waiter@restaurant.local', '+92 300 3333333', '3333', 1);

-- 7. SETTINGS
INSERT INTO `settings` (`setting_group`, `setting_key`, `setting_value`, `data_type`, `description`)
VALUES
('general', 'business_name', 'Spice & Savor Bistro', 'string', 'Restaurant Display Name'),
('general', 'currency_symbol', 'Rs.', 'string', 'Currency Symbol'),
('general', 'currency_code', 'PKR', 'string', 'Currency Code'),
('general', 'invoice_prefix', 'INV-', 'string', 'Prefix for Sales Invoices'),
('general', 'order_prefix', 'ORD-', 'string', 'Prefix for POS Orders'),
('general', 'tax_enabled', '1', 'boolean', 'Enable Sales Tax Calculation'),
('general', 'default_tax_rate', '5.00', 'decimal', 'Default Sales Tax %'),
('general', 'service_charge_enabled', '1', 'boolean', 'Enable Dine-in Service Charge'),
('general', 'default_service_charge', '2.50', 'decimal', 'Default Service Charge %'),
('pos', 'require_shift_for_pos', '0', 'boolean', 'Require open shift register to start POS sales'),
('pos', 'allow_price_override', '1', 'boolean', 'Allow cashiers to override prices with permission'),
('pos', 'default_order_type', 'dine_in', 'string', 'Default order type on opening POS'),
('inventory', 'costing_method', 'weighted_average', 'string', 'Inventory Costing Method (weighted_average / fifo)'),
('inventory', 'auto_consume_stock_on_sale', '1', 'boolean', 'Deduct ingredient stock immediately upon order completion');

-- 8. ORDER TYPES
INSERT INTO `order_types` (`id`, `code`, `name`, `requires_table`, `requires_customer`, `requires_delivery`, `default_service_charge`, `is_active`, `sort_order`)
VALUES
(1, 'dine_in', 'Dine-In', 1, 0, 0, 2.50, 1, 1),
(2, 'takeaway', 'Takeaway / Pickup', 0, 0, 0, 0.00, 1, 2),
(3, 'delivery', 'Home Delivery', 0, 1, 1, 0.00, 1, 3),
(4, 'drive_thru', 'Drive-Through', 0, 0, 0, 0.00, 1, 4);

-- 9. KITCHEN STATIONS
INSERT INTO `kitchen_stations` (`id`, `branch_id`, `name`, `code`, `printer_name`, `display_color`, `is_active`)
VALUES
(1, 1, 'Main Hot Kitchen', 'HOT_KIT', 'Kitchen_Printer_1', '#e63946', 1),
(2, 1, 'Pizza & Oven Station', 'PIZZA_STN', 'Pizza_Printer', '#f77f00', 1),
(3, 1, 'Grill & Charcoal Bar', 'GRILL_STN', 'Grill_Printer', '#d62828', 1),
(4, 1, 'Beverage & Bar', 'BAR_STN', 'Bar_Printer', '#0077b6', 1),
(5, 1, 'Dessert & Bakery Counter', 'DESSERT_STN', 'Dessert_Printer', '#7209b7', 1);

-- 10. DINING AREAS & TABLES
INSERT INTO `dining_areas` (`id`, `branch_id`, `name`, `description`, `sort_order`, `is_active`)
VALUES
(1, 1, 'Ground Floor Main Hall', 'Spacious ground floor seating with music', 1, 1),
(2, 1, 'Family Lounge', 'Private and quiet section for families', 2, 1),
(3, 1, 'Open Air Rooftop Patio', 'Outdoor dining with city view', 3, 1),
(4, 1, 'Executive VIP Room', 'Reserved executive dining suite', 4, 1);

INSERT INTO `restaurant_tables` (`id`, `branch_id`, `dining_area_id`, `table_number`, `capacity`, `shape`, `status`, `service_charge_percent`, `sort_order`)
VALUES
(1, 1, 1, 'T-01', 4, 'square', 'available', 2.50, 1),
(2, 1, 1, 'T-02', 4, 'square', 'available', 2.50, 2),
(3, 1, 1, 'T-03', 6, 'rectangle', 'available', 2.50, 3),
(4, 1, 1, 'T-04', 2, 'round', 'available', 2.50, 4),
(5, 1, 2, 'F-01', 6, 'rectangle', 'available', 2.50, 5),
(6, 1, 2, 'F-02', 8, 'rectangle', 'available', 2.50, 6),
(7, 1, 2, 'F-03', 4, 'round', 'available', 2.50, 7),
(8, 1, 3, 'R-01', 4, 'round', 'available', 0.00, 8),
(9, 1, 3, 'R-02', 4, 'round', 'available', 0.00, 9),
(10, 1, 3, 'R-03', 6, 'rectangle', 'available', 0.00, 10),
(11, 1, 4, 'VIP-1', 12, 'rectangle', 'available', 5.00, 11);

-- 11. UNITS & CONVERSIONS
INSERT INTO `units` (`id`, `name`, `code`, `category`, `is_base_unit`, `is_active`)
VALUES
(1, 'Kilogram', 'kg', 'weight', 1, 1),
(2, 'Gram', 'g', 'weight', 0, 1),
(3, 'Liter', 'L', 'volume', 1, 1),
(4, 'Milliliter', 'ml', 'volume', 0, 1),
(5, 'Piece', 'pc', 'piece', 1, 1),
(6, 'Packet', 'pkt', 'piece', 0, 1),
(7, 'Box / Set', 'box', 'packaging', 1, 1),
(8, 'Can (250ml)', 'can', 'piece', 1, 1);

INSERT INTO `unit_conversions` (`id`, `from_unit_id`, `to_unit_id`, `multiplier`)
VALUES
(1, 1, 2, 1000.000000), -- 1 kg = 1000 g
(2, 2, 1, 0.001000),    -- 1 g = 0.001 kg
(3, 3, 4, 1000.000000), -- 1 L = 1000 ml
(4, 4, 3, 0.001000);    -- 1 ml = 0.001 L

-- 12. INGREDIENTS (Master Stock)
INSERT INTO `ingredients` (`id`, `name`, `code`, `category`, `unit_id`, `cost_per_unit`, `current_stock`, `min_stock_alert`, `reorder_level`, `is_active`)
VALUES
(1, 'Fresh Boneless Chicken', 'ING-CHK-01', 'raw_meat', 1, 650.0000, 50.0000, 10.0000, 20.0000, 1),
(2, 'Prime Beef Mince', 'ING-BF-01', 'raw_meat', 1, 850.0000, 35.0000, 8.0000, 15.0000, 1),
(3, 'Gourmet Burger Buns', 'ING-BUN-01', 'bakery', 5, 30.0000, 120.0000, 25.0000, 50.0000, 1),
(4, 'Mozzarella Cheese Blend', 'ING-CHS-01', 'dairy', 1, 1400.0000, 22.5000, 5.0000, 10.0000, 1),
(5, 'Prepared Pizza Dough Base', 'ING-DGH-01', 'prepared', 5, 55.0000, 60.0000, 15.0000, 30.0000, 1),
(6, 'Italian Pizza Sauce', 'ING-SCE-01', 'prepared', 1, 380.0000, 18.0000, 4.0000, 8.0000, 1),
(7, 'Fresh Iceberg Lettuce', 'ING-LET-01', 'produce', 1, 180.0000, 12.0000, 3.0000, 5.0000, 1),
(8, 'Canola Cooking Oil', 'ING-OIL-01', 'grocery', 3, 480.0000, 40.0000, 10.0000, 20.0000, 1),
(9, 'Crispy French Fries (Pre-cut)', 'ING-FRY-01', 'frozen', 1, 320.0000, 45.0000, 10.0000, 25.0000, 1),
(10, 'Coca Cola 250ml Tin', 'ING-COKE-01', 'beverage', 8, 70.0000, 180.0000, 24.0000, 48.0000, 1),
(11, 'Burger Wrapping & Box', 'ING-PKG-01', 'packaging', 7, 25.0000, 250.0000, 50.0000, 100.0000, 1),
(12, 'Pizza Delivery Box (12 inch)', 'ING-PBOX-01', 'packaging', 7, 45.0000, 150.0000, 30.0000, 60.0000, 1);

-- 13. MENU CATEGORIES
INSERT INTO `menu_categories` (`id`, `name`, `code`, `kitchen_station_id`, `icon_class`, `color_hex`, `sort_order`, `is_active`)
VALUES
(1, 'Burgers & Sandwiches', 'CAT-BRG', 1, 'bi-badge-ad', '#e63946', 1, 1),
(2, 'Artisan Pizza', 'CAT-PIZ', 2, 'bi-pie-chart', '#f77f00', 2, 1),
(3, 'Charcoal BBQ & Grills', 'CAT-GRL', 3, 'bi-fire', '#d62828', 3, 1),
(4, 'Sides & Finger Foods', 'CAT-SID', 1, 'bi-egg-fried', '#457b9d', 4, 1),
(5, 'Chilled Beverages', 'CAT-BEV', 4, 'bi-cup-straw', '#0077b6', 5, 1),
(6, 'Bakery Desserts', 'CAT-DES', 5, 'bi-cake2', '#7209b7', 6, 1);

-- 14. MENU ITEMS
INSERT INTO `menu_items` (`id`, `category_id`, `kitchen_station_id`, `name`, `code`, `description`, `base_price`, `cost_estimate`, `tax_rate`, `prep_time_minutes`, `is_veg`, `is_spicy`, `is_popular`, `is_available`, `is_active`)
VALUES
(1, 1, 1, 'Zesty Crispy Chicken Burger', 'ITM-BRG-01', 'Deep fried chicken breast fillet with spicy secret mayo and crisp iceberg', 550.00, 185.00, 5.00, 12, 0, 1, 1, 1, 1),
(2, 1, 1, 'Prime Beef Cheddar Burger', 'ITM-BRG-02', 'Charbroiled beef patty loaded with melted Wisconsin cheddar and caramelized onions', 750.00, 260.00, 5.00, 15, 0, 0, 1, 1, 1),
(3, 2, 2, 'Pepperoni Supremo Pizza', 'ITM-PIZ-01', 'Rich marinara, mozzarella blend, double beef pepperoni and Italian herbs', 950.00, 320.00, 5.00, 20, 0, 1, 1, 1, 1),
(4, 2, 2, 'BBQ Smokey Chicken Pizza', 'ITM-PIZ-02', 'Charcoal grilled chicken chunks, smokey barbecue sauce, onions and bell peppers', 990.00, 340.00, 5.00, 20, 0, 0, 1, 1, 1),
(5, 3, 3, 'Charcoal Malai Boti Skewers', 'ITM-GRL-01', 'Velvety marinated boneless chicken cubes skewered and slow roasted over live coals', 680.00, 230.00, 5.00, 18, 0, 0, 1, 1, 1),
(6, 4, 1, 'Golden Crinkle Cut Fries', 'ITM-SID-01', 'Crispy salted golden potato fries served with garlic mayo dip', 280.00, 80.00, 5.00, 8, 1, 0, 1, 1, 1),
(7, 4, 1, 'Tangy Buffalo Wings (6 Pcs)', 'ITM-SID-02', 'Crispy chicken wings tossed in our signature hot glaze and sesame seeds', 480.00, 160.00, 5.00, 12, 0, 1, 0, 1, 1),
(8, 5, 4, 'Fresh Mint Lemonade', 'ITM-BEV-01', 'Refreshing crushed mint leaves, fresh lemon juice, soda and pink rock salt', 220.00, 45.00, 5.00, 5, 1, 0, 1, 1, 1),
(9, 5, 4, 'Coca Cola Chilled Can (250ml)', 'ITM-BEV-02', 'Ice cold 250ml can of Coca Cola served with lemon slice', 120.00, 70.00, 5.00, 2, 1, 0, 0, 1, 1),
(10, 6, 5, 'Molten Chocolate Lava Cake', 'ITM-DES-01', 'Warm chocolate fudge cake with molten core, served with vanilla ice cream', 450.00, 140.00, 5.00, 10, 1, 0, 1, 1, 1);

-- 15. MENU VARIANTS
INSERT INTO `menu_variants` (`id`, `menu_item_id`, `name`, `sku`, `additional_price`, `final_price`, `cost_estimate`, `is_default`, `is_active`)
VALUES
(1, 1, 'Single Patty', 'VAR-BRG-SGL', 0.00, 550.00, 185.00, 1, 1),
(2, 1, 'Double Trouble (2 Patties)', 'VAR-BRG-DBL', 220.00, 770.00, 280.00, 0, 1),
(3, 3, 'Regular (8 Inch)', 'VAR-PIZ-08', 0.00, 950.00, 320.00, 1, 1),
(4, 3, 'Medium (10 Inch)', 'VAR-PIZ-10', 350.00, 1300.00, 440.00, 0, 1),
(5, 3, 'Large (12 Inch)', 'VAR-PIZ-12', 650.00, 1600.00, 560.00, 0, 1),
(6, 4, 'Regular (8 Inch)', 'VAR-BPIZ-08', 0.00, 990.00, 340.00, 1, 1),
(7, 4, 'Medium (10 Inch)', 'VAR-BPIZ-10', 360.00, 1350.00, 460.00, 0, 1),
(8, 4, 'Large (12 Inch)', 'VAR-BPIZ-12', 680.00, 1670.00, 580.00, 0, 1),
(9, 6, 'Regular Serving', 'VAR-FRY-REG', 0.00, 280.00, 80.00, 1, 1),
(10, 6, 'Jumbo Bucket', 'VAR-FRY-JMB', 150.00, 430.00, 130.00, 0, 1);

-- 16. MODIFIER GROUPS & MODIFIERS
INSERT INTO `modifier_groups` (`id`, `name`, `description`, `min_selection`, `max_selection`, `is_required`, `is_active`)
VALUES
(1, 'Cheesy Add-ons', 'Top your burger or fries with extra cheese', 0, 2, 0, 1),
(2, 'Gourmet Dips', 'Choose your favorite dipping sauce', 0, 3, 0, 1),
(3, 'Crust Type', 'Select pizza crust preference', 1, 1, 1, 1);

INSERT INTO `modifiers` (`id`, `group_id`, `name`, `price`, `cost_estimate`, `is_active`)
VALUES
(1, 1, 'Melted Cheddar Slice', 70.00, 30.00, 1),
(2, 1, 'Extra Mozzarella Pull', 100.00, 45.00, 1),
(3, 2, 'Smoked Chipotle Dip', 50.00, 15.00, 1),
(4, 2, 'Garlic Herb Mayo', 50.00, 15.00, 1),
(5, 2, 'Spicy Peri Mayo', 50.00, 15.00, 1),
(6, 3, 'Pan Regular Crust', 0.00, 0.00, 1),
(7, 3, 'Cheese Stuffed Crust', 180.00, 80.00, 1);

-- 16b. MODIFIER GROUP MAPPINGS (Category & Item Associations)
INSERT INTO `menu_category_modifier_groups` (`category_id`, `modifier_group_id`, `sort_order`)
VALUES
(1, 1, 1), -- Burgers & Sandwiches -> Cheesy Add-ons
(1, 2, 2), -- Burgers & Sandwiches -> Gourmet Dips
(2, 3, 1), -- Artisan Pizza -> Crust Type
(2, 2, 2), -- Artisan Pizza -> Gourmet Dips
(3, 2, 1), -- Charcoal BBQ & Grills -> Gourmet Dips
(4, 1, 1), -- Sides & Finger Foods -> Cheesy Add-ons
(4, 2, 2); -- Sides & Finger Foods -> Gourmet Dips

-- 17. RECIPES (Sample linked consumption)
INSERT INTO `recipes` (`id`, `menu_item_id`, `menu_variant_id`, `yield_quantity`, `prep_loss_percent`, `instructions`, `total_cost`, `version`, `is_active`)
VALUES
(1, 1, 1, 1.00, 5.00, 'Marinate chicken fillet in batter, deep fry at 180C for 6 mins, assemble with bun and lettuce.', 163.00, 1, 1),
(2, 6, 9, 1.00, 2.00, 'Fry 200g frozen cut fries until golden and crispy.', 72.00, 1, 1),
(3, 9, NULL, 1.00, 0.00, 'Dispense 1 can chilled.', 70.00, 1, 1);

-- Recipe ingredients for Single Crispy Chicken Burger
INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_id`, `quantity`, `unit_id`, `cost_snapshot`, `is_packaging`)
VALUES
(1, 1, 1, 0.1600, 1, 104.00, 0), -- 160g chicken (at 650/kg)
(2, 1, 3, 1.0000, 5, 30.00, 0),   -- 1 burger bun
(3, 1, 7, 0.0250, 1, 4.50, 0),    -- 25g iceberg lettuce
(4, 1, 8, 0.0300, 3, 14.40, 0),   -- 30ml oil consumption
(5, 1, 11, 1.0000, 7, 25.00, 1);  -- 1 box packaging

-- Recipe for regular fries
INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_id`, `quantity`, `unit_id`, `cost_snapshot`, `is_packaging`)
VALUES
(6, 2, 9, 0.2000, 1, 64.00, 0),   -- 200g fries
(7, 2, 8, 0.0150, 3, 7.20, 0);    -- 15ml oil

-- Recipe for drink
INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_id`, `quantity`, `unit_id`, `cost_snapshot`, `is_packaging`)
VALUES
(8, 3, 10, 1.0000, 8, 70.00, 0);  -- 1 can

-- 18. SUPPLIERS
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `opening_balance`, `current_balance`, `is_active`)
VALUES
(1, 'Punjab Farms Meat Suppliers', 'Rashid Gujjar', '+92 321 4455667', 'punjabfarms@email.com', 'Wholesale Meat Market, Block B', 0.00, 0.00, 1),
(2, 'Master Bakers & Packaging Co.', 'Shahid Siddiqui', '+92 333 7788990', 'masterbakers@email.com', 'Industrial Area, Sector 7', 0.00, 0.00, 1),
(3, 'Metro Cash & Carry Bulk Dist.', 'Customer Desk', '+92 42 111-786-111', 'metro@metro.pk', 'Metro Wholesale Centre, Bypass', 0.00, 0.00, 1);

-- 19. PAYMENT METHODS
INSERT INTO `payment_methods` (`id`, `name`, `code`, `account_id`, `is_active`, `sort_order`)
VALUES
(1, 'Cash', 'CASH', 1, 1, 1),
(2, 'Credit / Debit Card (POS)', 'CARD', 2, 1, 2),
(3, 'Mobile Wallet (JazzCash / EasyPaisa)', 'WALLET', 3, 1, 3),
(4, 'Customer Credit Account', 'CREDIT', 4, 1, 4);

-- 20. CHART OF ACCOUNTS (Double Entry)
INSERT INTO `accounts` (`id`, `code`, `name`, `account_type`, `sub_type`, `is_system`, `is_active`, `current_balance`, `description`)
VALUES
(1, '1001', 'Cash in Register', 'asset', 'current_asset', 1, 1, 25000.00, 'Physical cash in hand and registers'),
(2, '1002', 'Bank Account - Alfalah', 'asset', 'bank', 1, 1, 150000.00, 'Main operating bank account'),
(3, '1003', 'Mobile Wallet Accounts', 'asset', 'bank', 1, 1, 15000.00, 'JazzCash, EasyPaisa digital merchant accounts'),
(4, '1100', 'Accounts Receivable (Customers)', 'asset', 'receivable', 1, 1, 0.00, 'Customer credit sales receivable'),
(5, '1200', 'Raw Ingredients Inventory', 'asset', 'inventory', 1, 1, 115000.00, 'Inventory valuation of food ingredients and packaging'),
(6, '2001', 'Accounts Payable (Suppliers)', 'liability', 'payable', 1, 1, 0.00, 'Amounts owed to meat, bakery, and beverage suppliers'),
(7, '2100', 'Sales Tax Payable', 'liability', 'tax', 1, 1, 0.00, 'Collected sales tax owed to tax authority'),
(8, '3001', 'Owner Capital', 'equity', 'equity', 1, 1, 305000.00, 'Initial investment and owner equity'),
(9, '4001', 'Food & Beverage Sales Revenue', 'revenue', 'operating_revenue', 1, 1, 0.00, 'Net revenue from dining and delivery sales'),
(10, '4002', 'Service Charge Income', 'revenue', 'other_revenue', 1, 1, 0.00, 'Dine-in service charges collected'),
(11, '4003', 'Delivery Fee Income', 'revenue', 'other_revenue', 1, 1, 0.00, 'Fees charged for rider delivery'),
(12, '4100', 'Sales Discounts Granted', 'revenue', 'contra_revenue', 1, 1, 0.00, 'Discounts given to customers (reduces revenue)'),
(13, '5001', 'Cost of Goods Sold (Food & Ingredients)', 'cogs', 'direct_cost', 1, 1, 0.00, 'Direct consumption cost of menu ingredients'),
(14, '5002', 'Packaging Material Cost', 'cogs', 'direct_cost', 1, 1, 0.00, 'Takeaway and delivery packaging cost'),
(15, '6001', 'Staff Salaries & Wages', 'expense', 'payroll', 1, 1, 0.00, 'Monthly wages paid to staff and chefs'),
(16, '6002', 'Premises Rent Expense', 'expense', 'occupancy', 1, 1, 0.00, 'Monthly building rental'),
(17, '6003', 'Electricity & Gas Utility Bills', 'expense', 'utility', 1, 1, 0.00, 'WAPDA and SNGPL utility charges'),
(18, '6004', 'Repair & Equipment Maintenance', 'expense', 'general', 1, 1, 0.00, 'Kitchen maintenance and service'),
(19, '6005', 'Stock Spoilage & Wastage Expense', 'expense', 'loss', 1, 1, 0.00, 'Cost of wasted or expired food ingredients');

-- 21. EXPENSE CATEGORIES
INSERT INTO `expense_categories` (`id`, `name`, `description`, `account_id`, `is_active`)
VALUES
(1, 'Utilities (Electricity & Gas)', 'Monthly power, gas and generator fuel', 17, 1),
(2, 'Building Rent', 'Monthly restaurant premises rent', 16, 1),
(3, 'Kitchen Repairs & Equipment', 'Oven repair, AC service, plumbing', 18, 1),
(4, 'Marketing & Social Media', 'Flyers, influencer promos, Facebook ads', 18, 1),
(5, 'Cleaning & Sanitation Supplies', 'Detergents, floor cleaners, kitchen wipes', 18, 1);

-- 22. CUSTOMERS
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `delivery_notes`, `credit_limit`, `current_balance`, `loyalty_points`, `is_active`)
VALUES
(1, 'Walk-in / General Customer', '0300-0000000', 'guest@customer.local', 'Counter Walk-in', NULL, 0.00, 0.00, 0, 1),
(2, 'Mr. Laiq Ahmad', '0321-7654321', 'laiq@example.com', 'House 45, Street 8, Phase 5 DHA', 'Ring bell twice, leave at reception if not home', 25000.00, 0.00, 120, 1),
(3, 'Dr. Sarah Tariq', '0333-1234987', 'sarah.tariq@hospital.org', 'Flat 402, Executive Tower, Gulberg', 'Call on arrival at security gate', 15000.00, 0.00, 60, 1);

-- 23. DELIVERY ZONES & RIDERS
INSERT INTO `delivery_zones` (`id`, `branch_id`, `name`, `coverage_details`, `delivery_fee`, `min_order_amount`, `estimated_minutes`, `is_active`)
VALUES
(1, 1, 'Downtown Core & Commercial Area', 'Within 3 KM radius of restaurant', 100.00, 500.00, 30, 1),
(2, 1, 'DHA Phases 1-5', '3 to 7 KM radius', 150.00, 750.00, 40, 1),
(3, 1, 'Gulberg & Cantonment', '7 to 12 KM radius', 220.00, 1000.00, 50, 1);

INSERT INTO `riders` (`id`, `branch_id`, `name`, `phone`, `vehicle_number`, `status`, `is_active`)
VALUES
(1, 1, 'Kamran Shafi', '+92 312 9988776', 'LEA-24-9081', 'available', 1),
(2, 1, 'Zahid Iqbal', '+92 345 5544332', 'LHR-22-1245', 'available', 1);

-- 24. CASH REGISTERS & EMPLOYEES
INSERT INTO `cash_registers` (`id`, `branch_id`, `name`, `code`, `is_active`)
VALUES
(1, 1, 'POS Terminal #1 (Front Counter)', 'REG-01', 1),
(2, 1, 'POS Terminal #2 (Drive-Thru / Takeaway)', 'REG-02', 1);

INSERT INTO `employees` (`id`, `branch_id`, `user_id`, `employee_code`, `full_name`, `designation`, `phone`, `email`, `joining_date`, `basic_salary`, `salary_type`, `is_active`)
VALUES
(1, 1, 2, 'EMP-001', 'Hamza Aslam', 'Lead POS Cashier', '+92 300 1111111', 'hamza@restaurant.local', '2025-01-15', 35000.00, 'monthly', 1),
(2, 1, 3, 'EMP-002', 'Farhan Ali', 'Head Chef', '+92 300 2222222', 'chef@restaurant.local', '2024-11-01', 65000.00, 'monthly', 1),
(3, 1, 4, 'EMP-003', 'Bilal Ahmed', 'Dining Server', '+92 300 3333333', 'bilal@restaurant.local', '2025-03-01', 28000.00, 'monthly', 1);

-- 25. PRINTER CONFIGURATIONS
INSERT INTO `printer_configurations` (`id`, `branch_id`, `name`, `printer_type`, `paper_width_mm`, `station_id`, `footer_text`, `is_default`)
VALUES
(1, 1, 'Front Cashier Thermal (80mm)', 'receipt_80', 80, NULL, 'Thank you for your visit! Follow us @spiceandsavor', 1),
(2, 1, 'Kitchen Hot Station Ticket (80mm)', 'kitchen', 80, 1, NULL, 0),
(3, 1, 'Oven & Pizza Ticket (80mm)', 'kitchen', 80, 2, NULL, 0);

-- 26. INITIAL OPEN SHIFT (So POS is immediately usable out-of-the-box!)
INSERT INTO `cash_shifts` (`id`, `branch_id`, `register_id`, `user_id`, `shift_number`, `opened_at`, `closed_at`, `opening_cash`, `status`, `notes`)
VALUES
(1, 1, 1, 1, 'SHF-20261030-001', NOW(), NULL, 5000.00, 'open', 'Initial opening shift for POS Terminal #1');
