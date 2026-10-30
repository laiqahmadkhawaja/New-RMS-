<?php
// modules/settings/index.php - System Configuration & Dynamic Settings (PRD 24)
$pageTitle = 'System Settings';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('settings.manage');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken(post('csrf_token'))) {
        setFlash('error', 'Token expired.');
    } else {
        $settingsToSave = [
            'business_name' => post('business_name'),
            'currency_symbol' => post('currency_symbol'),
            'currency_code' => post('currency_code'),
            'invoice_prefix' => post('invoice_prefix'),
            'order_prefix' => post('order_prefix'),
            'default_tax_rate' => post('default_tax_rate'),
            'default_service_charge' => post('default_service_charge'),
            'tax_enabled' => !empty(post('tax_enabled')) ? '1' : '0',
            'service_charge_enabled' => !empty(post('service_charge_enabled')) ? '1' : '0',
            'costing_method' => post('costing_method'),
            'auto_consume_stock_on_sale' => !empty(post('auto_consume_stock_on_sale')) ? '1' : '0'
        ];

        foreach ($settingsToSave as $k => $val) {
            setSetting($k, $val);
        }

        logAudit('update_settings', 'settings', null, null, $settingsToSave);
        setFlash('success', 'System settings saved successfully.');
        header('Location: ' . BASE_URL . 'modules/settings/index.php');
        exit;
    }
}
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-sliders text-danger me-2"></i> System Settings & Preferences</h3>
        <p class="text-muted small mb-0">Configure business branding, dynamic tax rules, POS prefixes, and inventory costing methods</p>
    </div>
</div>

<form method="POST" action="">
    <?= csrfField(); ?>
    <div class="row g-4 justify-content-center">
        <div class="col-12 col-lg-8">
            <!-- 1. General Profile -->
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Business Identity & Formatting</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Restaurant Display Name</label>
                        <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars(getSetting('currency_symbol', 'Rs.')) ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Currency Code</label>
                        <input type="text" name="currency_code" class="form-control" value="<?= htmlspecialchars(getSetting('currency_code', 'PKR')) ?>" required>
                    </div>

                    <div class="col-6 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Order Number Prefix</label>
                        <input type="text" name="order_prefix" class="form-control" value="<?= htmlspecialchars(getSetting('order_prefix', 'ORD-')) ?>" required>
                    </div>
                    <div class="col-6 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Tax Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars(getSetting('invoice_prefix', 'INV-')) ?>" required>
                    </div>
                </div>
            </div>

            <!-- 2. POS Charges & Taxes -->
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Sales Taxes & Service Charges</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="tax_enabled" id="taxEnabled" <?= getSetting('tax_enabled', 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="taxEnabled">Enable Sales Tax Calculation</label>
                        </div>
                        <label class="form-label small fw-semibold text-muted text-uppercase">Default Sales Tax Rate (%)</label>
                        <input type="number" name="default_tax_rate" class="form-control" step="0.1" value="<?= (float)getSetting('default_tax_rate', 5.0) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="service_charge_enabled" id="scEnabled" <?= getSetting('service_charge_enabled', 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="scEnabled">Enable Dine-In Service Charge</label>
                        </div>
                        <label class="form-label small fw-semibold text-muted text-uppercase">Default Service Charge (%)</label>
                        <input type="number" name="default_service_charge" class="form-control" step="0.1" value="<?= (float)getSetting('default_service_charge', 2.5) ?>">
                    </div>
                </div>
            </div>

            <!-- 3. Inventory & Operations Policies -->
            <div class="card-rms p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Inventory Costing & Automation</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Inventory Costing Method (PRD 10.4)</label>
                        <select name="costing_method" class="form-select">
                            <option value="weighted_average" <?= getSetting('costing_method') === 'weighted_average' ? 'selected' : '' ?>>Moving Weighted Average Cost</option>
                            <option value="fifo" <?= getSetting('costing_method') === 'fifo' ? 'selected' : '' ?>>FIFO (First-In, First-Out)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 d-flex align-items-center pt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="auto_consume_stock_on_sale" id="autoConsume" <?= getSetting('auto_consume_stock_on_sale', 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="autoConsume">Auto-deduct ingredient stock upon order billing</label>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow">
                <i class="bi bi-check2-circle me-1"></i> Save System Preferences
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
