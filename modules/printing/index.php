<?php
// modules/printing/index.php - Printers & Hardware Configuration (PRD 21)
$pageTitle = 'Printers & Hardware';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('settings.manage');

$db = getDB();
$branchId = currentBranchId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken(post('csrf_token'))) {
        $name = cleanInput(post('name'));
        $type = cleanInput(post('printer_type'));
        $paperWidth = (int)post('paper_width_mm', 80);
        $stationId = !empty(post('station_id')) ? (int)post('station_id') : null;
        $footer = cleanInput(post('footer_text'));

        if (!empty($name)) {
            $db->prepare("
                INSERT INTO printer_configurations (branch_id, name, printer_type, paper_width_mm, station_id, footer_text)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$branchId, $name, $type, $paperWidth, $stationId, $footer]);
            setFlash('success', "Printer '$name' added.");
            header('Location: ' . BASE_URL . 'modules/printing/index.php');
            exit;
        }
    }
}

$printers = $db->query("
    SELECT pc.*, ks.name as station_name 
    FROM printer_configurations pc 
    LEFT JOIN kitchen_stations ks ON pc.station_id = ks.id 
    WHERE pc.branch_id = $branchId 
    ORDER BY pc.id ASC
")->fetchAll();

$stations = $db->query("SELECT * FROM kitchen_stations WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-printer text-danger me-2"></i> Printers & Hardware Routing</h3>
        <p class="text-muted small mb-0">Configure thermal receipt printers (58mm/80mm), station routing, and kitchen ticket dispatch</p>
    </div>
    <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="RMS.openSlidePanel('addPrinterPanel')">
        <i class="bi bi-plus-circle me-1"></i> Add Printer
    </button>
</div>

<!-- Printers List -->
<div class="card-rms overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Printer Device Name</th>
                    <th>Type / Purpose</th>
                    <th>Paper Width</th>
                    <th>Assigned Kitchen Station</th>
                    <th>Default Receipt Footer</th>
                    <th class="text-end">Test Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($printers as $p): ?>
                    <tr>
                        <td>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></span>
                            <?= $p['is_default'] ? '<span class="badge bg-danger ms-1">DEFAULT RECEIPT</span>' : '' ?>
                        </td>
                        <td><span class="badge bg-light text-dark border text-uppercase"><?= str_replace('_', ' ', $p['printer_type']) ?></span></td>
                        <td><?= $p['paper_width_mm'] ?> mm</td>
                        <td><?= htmlspecialchars($p['station_name'] ?? 'Front Cashier Till') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($p['footer_text'] ?? '-') ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>print/receipt.php?order_id=1&width=<?= $p['paper_width_mm'] ?>" target="_blank" class="btn btn-sm btn-outline-dark fw-semibold">
                                <i class="bi bi-printer me-1"></i> Print Test
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= SLIDE PANEL: ADD PRINTER (NO MODAL) ================= -->
<div id="addPrinterPanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0">Register Printer Device</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('addPrinterPanel')"></button>
    </div>
    <form method="POST" action="" class="p-3 flex-grow-1 overflow-y-auto">
        <?= csrfField(); ?>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Printer Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Front_Cashier_Thermal" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Printer Type</label>
                <select name="printer_type" class="form-select">
                    <option value="receipt_80">Receipt (80mm Thermal)</option>
                    <option value="receipt_58">Receipt (58mm Thermal)</option>
                    <option value="kitchen">Kitchen Ticket (KOT)</option>
                    <option value="a4">Standard A4 Printer</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Paper Width (mm)</label>
                <select name="paper_width_mm" class="form-select">
                    <option value="80">80 mm (Standard)</option>
                    <option value="58">58 mm (Compact)</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Assign to Kitchen Station</label>
            <select name="station_id" class="form-select">
                <option value="">-- Front Cashier (Customer Bills) --</option>
                <?php foreach ($stations as $st): ?>
                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Receipt Footer Message</label>
            <textarea name="footer_text" class="form-control" rows="2" placeholder="Thank you for your visit!"></textarea>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Printer Setup
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
