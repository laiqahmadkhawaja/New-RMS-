<?php
// modules/users/index.php - User Management & RBAC Roles / Permissions (PRD 23)
$pageTitle = 'Users & Roles';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('users.manage');

$db = getDB();
$branchId = currentBranchId();

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_user'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $userId = (int)post('user_id', 0);
        $username = cleanInput(post('username'));
        $fullName = cleanInput(post('full_name'));
        $roleId = (int)post('role_id');
        $userBranchId = (int)post('branch_id', $branchId);
        $email = cleanInput(post('email'));
        $phone = cleanInput(post('phone'));
        $pin = cleanInput(post('pin_code'));
        $pass = post('password');

        if (!empty($username) && !empty($fullName) && $roleId) {
            if ($userId) {
                if (!empty($pass)) {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $db->prepare("UPDATE users SET username = ?, full_name = ?, role_id = ?, branch_id = ?, email = ?, phone = ?, pin_code = ?, password_hash = ? WHERE id = ?")
                       ->execute([$username, $fullName, $roleId, $userBranchId, $email, $phone, $pin, $hash, $userId]);
                } else {
                    $db->prepare("UPDATE users SET username = ?, full_name = ?, role_id = ?, branch_id = ?, email = ?, phone = ?, pin_code = ? WHERE id = ?")
                       ->execute([$username, $fullName, $roleId, $userBranchId, $email, $phone, $pin, $userId]);
                }
                setFlash('success', "User '$username' updated.");
            } else {
                $hash = password_hash($pass ?: '123456', PASSWORD_BCRYPT);
                $db->prepare("
                    INSERT INTO users (branch_id, role_id, username, password_hash, full_name, email, phone, pin_code, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ")->execute([$userBranchId, $roleId, $username, $hash, $fullName, $email, $phone, $pin]);
                setFlash('success', "User '$username' created successfully.");
            }
            header('Location: ' . BASE_URL . 'modules/users/index.php');
            exit;
        }
    }
}

// Handle role permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_role_perms'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $editRoleId = (int)post('role_id');
        $selectedPerms = post('permissions', []);

        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$editRoleId]);
        $insPerm = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($selectedPerms as $pId) {
            $insPerm->execute([$editRoleId, (int)$pId]);
        }
        setFlash('success', "Permissions updated for role.");
        header('Location: ' . BASE_URL . 'modules/users/index.php?tab=roles');
        exit;
    }
}

$tab = get('tab', 'users');
$users = $db->query("
    SELECT u.*, r.display_name as role_name, b.name as branch_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    JOIN branches b ON u.branch_id = b.id 
    ORDER BY u.id ASC
")->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$branches = $db->query("SELECT * FROM branches WHERE is_active = 1")->fetchAll();
$allPermissions = $db->query("SELECT * FROM permissions ORDER BY module, id ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-shield-lock text-danger me-2"></i> Users & Role-Based Access Control (RBAC)</h3>
        <p class="text-muted small mb-0">Manage staff POS login credentials and configure granular backend permission matrix</p>
    </div>
    <?php if ($tab === 'users'): ?>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddUser()">
            <i class="bi bi-person-plus me-1"></i> Add System User
        </button>
    <?php endif; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'users' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=users">
            System Users Directory
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'roles' ? 'active bg-danger text-white' : 'btn-light' ?> fw-semibold" href="?tab=roles">
            Role Permission Matrix
        </a>
    </li>
</ul>

<!-- ================= 1. USERS LIST TAB ================= -->
<?php if ($tab === 'users'): ?>
    <div class="card-rms overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th>Username / Name</th>
                        <th>Role Assigned</th>
                        <th>Branch</th>
                        <th>Contact Phone</th>
                        <th>Quick PIN</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></span>
                                <small class="text-muted d-block font-monospace">@<?= htmlspecialchars($u['username']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary fs-6"><?= htmlspecialchars($u['role_name']) ?></span></td>
                            <td><?= htmlspecialchars($u['branch_name']) ?></td>
                            <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                            <td><code><?= htmlspecialchars($u['pin_code'] ?? '-') ?></code></td>
                            <td class="small text-muted"><?= $u['last_login_at'] ? date('d-M H:i', strtotime($u['last_login_at'])) : 'Never' ?></td>
                            <td>
                                <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-danger' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openEditUser(<?= json_encode($u) ?>)' title="Edit User">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ================= 2. ROLE PERMISSION MATRIX TAB ================= -->
<?php if ($tab === 'roles'): 
    $selectedRoleId = (int)get('role_id', 4); // Default to Cashier
    $currentRolePerms = $db->query("SELECT permission_id FROM role_permissions WHERE role_id = $selectedRoleId")->fetchAll(PDO::FETCH_COLUMN);
?>
    <div class="row g-4">
        <!-- Roles List -->
        <div class="col-12 col-md-4">
            <div class="card-rms p-3">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Select Role to Edit</h6>
                <div class="list-group">
                    <?php foreach ($roles as $r): ?>
                        <a href="?tab=roles&role_id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $r['id'] == $selectedRoleId ? 'active bg-danger border-danger' : '' ?>">
                            <div>
                                <span class="fw-bold"><?= htmlspecialchars($r['display_name']) ?></span>
                                <small class="d-block <?= $r['id'] == $selectedRoleId ? 'text-white-50' : 'text-muted' ?>"><?= htmlspecialchars($r['description'] ?? '') ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Permissions Checkboxes Matrix -->
        <div class="col-12 col-md-8">
            <div class="card-rms p-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2">
                    Permissions for: <span class="text-danger"><?= htmlspecialchars($roles[array_search($selectedRoleId, array_column($roles, 'id'))]['display_name'] ?? 'Role') ?></span>
                </h5>

                <form method="POST" action="">
                    <?= csrfField(); ?>
                    <input type="hidden" name="action_update_role_perms" value="1">
                    <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">

                    <div class="row g-3 mb-4">
                        <?php foreach ($allPermissions as $p): ?>
                            <div class="col-12 col-sm-6">
                                <div class="form-check p-2 border rounded bg-light">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" id="perm_<?= $p['id'] ?>" <?= in_array($p['id'], $currentRolePerms) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="perm_<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['name']) ?>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;"><?= htmlspecialchars($p['description'] ?? '') ?></small>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-danger fw-semibold px-4">
                        <i class="bi bi-shield-check me-1"></i> Save Role Permissions
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Slide Panel: Add/Edit User (No modal) -->
<div id="userSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="userPanelTitle">Create User</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('userSlidePanel')"></button>
    </div>
    <form method="POST" action="" class="p-3 flex-grow-1 overflow-y-auto">
        <?= csrfField(); ?>
        <input type="hidden" name="action_save_user" value="1">
        <input type="hidden" name="user_id" id="userIdInput" value="0">

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" id="userFullName" class="form-control" placeholder="User full name" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" id="userLoginName" class="form-control" placeholder="login_username" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Password</label>
                <input type="password" name="password" id="userPassword" class="form-control" placeholder="Leave blank to keep current">
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Assigned Role <span class="text-danger">*</span></label>
                <select name="role_id" id="userRoleSelect" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Operating Branch <span class="text-danger">*</span></label>
                <select name="branch_id" id="userBranchSelect" class="form-select" required>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Phone</label>
                <input type="text" name="phone" id="userPhone" class="form-control" placeholder="0300-1122334">
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">POS Quick PIN (4 digits)</label>
                <input type="text" name="pin_code" id="userPin" class="form-control" placeholder="1234" maxlength="6">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Email</label>
            <input type="email" name="email" id="userEmail" class="form-control" placeholder="user@restaurant.local">
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save User Account
        </button>
    </form>
</div>

<script>
function openAddUser() {
    document.getElementById('userPanelTitle').innerText = 'Create System User';
    document.getElementById('userIdInput').value = '0';
    document.getElementById('userFullName').value = '';
    document.getElementById('userLoginName').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPhone').value = '';
    document.getElementById('userPin').value = '1234';
    document.getElementById('userEmail').value = '';
    RMS.openSlidePanel('userSlidePanel');
}

function openEditUser(u) {
    document.getElementById('userPanelTitle').innerText = 'Edit User: ' + u.username;
    document.getElementById('userIdInput').value = u.id;
    document.getElementById('userFullName').value = u.full_name;
    document.getElementById('userLoginName').value = u.username;
    document.getElementById('userPassword').value = '';
    document.getElementById('userRoleSelect').value = u.role_id;
    document.getElementById('userBranchSelect').value = u.branch_id;
    document.getElementById('userPhone').value = u.phone || '';
    document.getElementById('userPin').value = u.pin_code || '';
    document.getElementById('userEmail').value = u.email || '';
    RMS.openSlidePanel('userSlidePanel');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
