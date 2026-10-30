<?php
// modules/branches/index.php - Multi-Branch Management (PRD 22)
$pageTitle = 'Branch Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('settings.manage');

$db = getDB();

// Switch active branch in session
if (isset($_GET['switch_branch'])) {
    $swId = (int)$_GET['switch_branch'];
    $stmt = $db->prepare("SELECT id, name FROM branches WHERE id = ? AND is_active = 1");
    $stmt->execute([$swId]);
    $br = $stmt->fetch();
    if ($br) {
        $_SESSION['branch_id'] = (int)$br['id'];
        $_SESSION['branch_name'] = $br['name'];
        setFlash('success', "Switched active operating branch to {$br['name']}.");
    }
    header('Location: ' . BASE_URL . 'modules/branches/index.php');
    exit;
}

// Handle branch create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken(post('csrf_token'))) {
        $id = (int)post('branch_id', 0);
        $name = cleanInput(post('name'));
        $code = cleanInput(post('code'));
        $phone = cleanInput(post('phone'));
        $email = cleanInput(post('email'));
        $address = cleanInput(post('address'));
        $tax = (float)post('default_tax_rate', 5.0);
        $service = (float)post('default_service_charge', 2.5);

        if (!empty($name) && !empty($code)) {
            if ($id) {
                $db->prepare("
                    UPDATE branches 
                    SET name = ?, code = ?, phone = ?, email = ?, address = ?, 
                        default_tax_rate = ?, default_service_charge = ? 
                    WHERE id = ?
                ")->execute([$name, $code, $phone, $email, $address, $tax, $service, $id]);
                setFlash('success', "Branch '$name' updated.");
            } else {
                $db->prepare("
                    INSERT INTO branches (name, code, phone, email, address, default_tax_rate, default_service_charge)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([$name, $code, $phone, $email, $address, $tax, $service]);
                setFlash('success', "Branch '$name' created.");
            }
            header('Location: ' . BASE_URL . 'modules/branches/index.php');
            exit;
        }
    }
}

$branches = $db->query("
    SELECT b.*, 
           (SELECT COUNT(*) FROM users WHERE branch_id = b.id) as staff_count,
           (SELECT COUNT(*) FROM restaurant_tables WHERE branch_id = b.id) as tables_count 
    FROM branches b 
    WHERE b.is_active = 1 
    ORDER BY b.id ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-buildings text-danger me-2"></i> Multi-Branch Management</h3>
        <p class="text-muted small mb-0">Centralized control of multiple restaurant locations, tax rules, and local menus</p>
    </div>
    <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddBranch()">
        <i class="bi bi-plus-circle me-1"></i> Add Branch
    </button>
</div>

<!-- Branches Grid -->
<div class="row g-3 mb-4">
    <?php foreach ($branches as $br): 
        $isActiveCurrent = ($br['id'] == currentBranchId());
    ?>
        <div class="col-12 col-md-6">
            <div class="card-rms p-4 shadow-sm h-100 <?= $isActiveCurrent ? 'border-danger' : '' ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($br['name']) ?></h5>
                            <?php if ($isActiveCurrent): ?>
                                <span class="badge bg-danger">ACTIVE NOW</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted font-monospace"><?= htmlspecialchars($br['code']) ?></small>
                    </div>
                    <?php if (!$isActiveCurrent): ?>
                        <a href="?switch_branch=<?= $br['id'] ?>" class="btn btn-sm btn-outline-danger fw-semibold">
                            Switch To This Branch
                        </a>
                    <?php endif; ?>
                </div>

                <div class="small text-muted mb-3">
                    <div><i class="bi bi-geo-alt me-1 text-danger"></i> <?= htmlspecialchars($br['address'] ?? 'No address') ?></div>
                    <div><i class="bi bi-telephone me-1 text-secondary"></i> <?= htmlspecialchars($br['phone'] ?? '-') ?></div>
                </div>

                <div class="row g-2 pt-2 border-top small">
                    <div class="col-6">
                        <span class="text-muted">Default Tax:</span> <b><?= $br['default_tax_rate'] ?>%</b>
                    </div>
                    <div class="col-6">
                        <span class="text-muted">Service Charge:</span> <b><?= $br['default_service_charge'] ?>%</b>
                    </div>
                    <div class="col-6">
                        <span class="text-muted">Assigned Staff:</span> <b><?= $br['staff_count'] ?></b>
                    </div>
                    <div class="col-6">
                        <span class="text-muted">Dining Tables:</span> <b><?= $br['tables_count'] ?></b>
                    </div>
                </div>

                <div class="text-end mt-3 pt-2 border-top">
                    <button class="btn btn-sm btn-link text-secondary p-0" onclick='openEditBranch(<?= json_encode($br) ?>)'>
                        <i class="bi bi-pencil-square me-1"></i> Edit Configuration
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Slide Panel: Add/Edit Branch (No modal) -->
<div id="branchSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="brPanelTitle">Add New Branch</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('branchSlidePanel')"></button>
    </div>
    <form method="POST" action="" class="p-3 flex-grow-1 overflow-y-auto">
        <?= csrfField(); ?>
        <input type="hidden" name="branch_id" id="brIdInput" value="0">

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Branch Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="brNameInput" class="form-control" placeholder="e.g. Uptown Express" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Branch Code <span class="text-danger">*</span></label>
                <input type="text" name="code" id="brCodeInput" class="form-control" placeholder="UP02" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Phone</label>
                <input type="text" name="phone" id="brPhoneInput" class="form-control" placeholder="0300-9876543">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Address</label>
            <textarea name="address" id="brAddressInput" class="form-control" rows="2" placeholder="Full branch address..."></textarea>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Default Tax Rate (%)</label>
                <input type="number" name="default_tax_rate" id="brTaxInput" class="form-control" step="0.1" value="5.0">
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Service Charge (%)</label>
                <input type="number" name="default_service_charge" id="brServiceInput" class="form-control" step="0.1" value="0.0">
            </div>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Branch
        </button>
    </form>
</div>

<script>
function openAddBranch() {
    document.getElementById('brPanelTitle').innerText = 'Add New Branch';
    document.getElementById('brIdInput').value = '0';
    document.getElementById('brNameInput').value = '';
    document.getElementById('brCodeInput').value = '';
    document.getElementById('brPhoneInput').value = '';
    document.getElementById('brAddressInput').value = '';
    document.getElementById('brTaxInput').value = '5.0';
    document.getElementById('brServiceInput').value = '0.0';
    RMS.openSlidePanel('branchSlidePanel');
}

function openEditBranch(b) {
    document.getElementById('brPanelTitle').innerText = 'Edit Branch: ' + b.name;
    document.getElementById('brIdInput').value = b.id;
    document.getElementById('brNameInput').value = b.name;
    document.getElementById('brCodeInput').value = b.code;
    document.getElementById('brPhoneInput').value = b.phone || '';
    document.getElementById('brAddressInput').value = b.address || '';
    document.getElementById('brTaxInput').value = b.default_tax_rate;
    document.getElementById('brServiceInput').value = b.default_service_charge;
    RMS.openSlidePanel('branchSlidePanel');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
