<?php
// modules/employees/index.php - Employee & Staff Profiles (PRD 18.1)
$pageTitle = 'Employee Directory';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('employees.manage');

$db = getDB();
$branchId = currentBranchId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken(post('csrf_token'))) {
        $id = (int)post('employee_id', 0);
        $code = cleanInput(post('employee_code'));
        $name = cleanInput(post('full_name'));
        $designation = cleanInput(post('designation'));
        $phone = cleanInput(post('phone'));
        $email = cleanInput(post('email'));
        $joiningDate = cleanInput(post('joining_date', date('Y-m-d')));
        $basicSalary = (float)post('basic_salary', 0);
        $salaryType = cleanInput(post('salary_type', 'monthly'));

        if (!empty($name) && !empty($phone)) {
            if ($id) {
                $db->prepare("
                    UPDATE employees 
                    SET employee_code = ?, full_name = ?, designation = ?, phone = ?, email = ?, 
                        joining_date = ?, basic_salary = ?, salary_type = ? 
                    WHERE id = ?
                ")->execute([$code, $name, $designation, $phone, $email, $joiningDate, $basicSalary, $salaryType, $id]);
                setFlash('success', "Staff member '$name' updated.");
            } else {
                $db->prepare("
                    INSERT INTO employees (branch_id, employee_code, full_name, designation, phone, email, joining_date, basic_salary, salary_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$branchId, $code, $name, $designation, $phone, $email, $joiningDate, $basicSalary, $salaryType]);
                setFlash('success', "Staff member '$name' registered.");
            }
            header('Location: ' . BASE_URL . 'modules/employees/index.php');
            exit;
        }
    }
}

$employees = $db->query("
    SELECT e.*, u.username 
    FROM employees e 
    LEFT JOIN users u ON e.user_id = u.id 
    WHERE e.branch_id = $branchId AND e.is_active = 1 
    ORDER BY e.id ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-person-badge text-danger me-2"></i> Staff & Workforce Directory</h3>
        <p class="text-muted small mb-0">Maintain employee profiles, job designations, salary structures, and linked POS logins</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/employees/attendance.php" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-clock-history me-1"></i> Attendance
        </a>
        <a href="<?= BASE_URL ?>modules/employees/payroll.php" class="btn btn-outline-dark btn-sm fw-semibold">
            <i class="bi bi-cash me-1"></i> Payroll
        </a>
        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="openAddEmployee()">
            <i class="bi bi-person-plus me-1"></i> Add Employee
        </button>
    </div>
</div>

<!-- Employees Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Emp ID / Code</th>
                    <th>Full Name</th>
                    <th>Job Designation</th>
                    <th>Contact Phone</th>
                    <th>Joining Date</th>
                    <th>Salary Structure</th>
                    <th>User Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No staff members registered.</td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><code class="fw-bold"><?= htmlspecialchars($emp['employee_code']) ?></code></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($emp['full_name']) ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($emp['email'] ?? '') ?></small>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary fs-6"><?= htmlspecialchars($emp['designation']) ?></span></td>
                            <td class="fw-semibold text-danger"><?= htmlspecialchars($emp['phone']) ?></td>
                            <td class="small text-muted"><?= date('d-M-Y', strtotime($emp['joining_date'])) ?></td>
                            <td>
                                <div class="fw-bold"><?= formatCurrency($emp['basic_salary']) ?></div>
                                <small class="text-muted text-capitalize"><?= $emp['salary_type'] ?></small>
                            </td>
                            <td>
                                <?php if ($emp['username']): ?>
                                    <span class="badge bg-light text-dark border"><i class="bi bi-person-check me-1"></i><?= htmlspecialchars($emp['username']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">No POS Login</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openEditEmployee(<?= json_encode($emp) ?>)' title="Edit Profile">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= SLIDE PANEL: ADD / EDIT EMPLOYEE ================= -->
<div id="employeeSlidePanel" class="slide-panel">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
        <h5 class="fw-bold mb-0" id="empPanelTitle">Register Employee</h5>
        <button type="button" class="btn-close" onclick="RMS.closeSlidePanel('employeeSlidePanel')"></button>
    </div>
    <form method="POST" action="" class="p-3 flex-grow-1 overflow-y-auto">
        <?= csrfField(); ?>
        <input type="hidden" name="employee_id" id="empIdInput" value="0">

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Employee ID Code <span class="text-danger">*</span></label>
                <input type="text" name="employee_code" id="empCodeInput" class="form-control" placeholder="EMP-005" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Job Designation <span class="text-danger">*</span></label>
                <input type="text" name="designation" id="empDesigInput" class="form-control" placeholder="e.g. Waiter, Chef..." required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" id="empNameInput" class="form-control" placeholder="Staff member name" required>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Contact Phone <span class="text-danger">*</span></label>
                <input type="text" name="phone" id="empPhoneInput" class="form-control" placeholder="0300-1112233" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Email</label>
                <input type="email" name="email" id="empEmailInput" class="form-control" placeholder="staff@restaurant.local">
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Joining Date</label>
                <input type="date" name="joining_date" id="empJoinInput" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold text-muted text-uppercase">Salary Type</label>
                <select name="salary_type" id="empSalTypeInput" class="form-select">
                    <option value="monthly">Monthly Fixed</option>
                    <option value="daily">Daily Wage</option>
                    <option value="hourly">Hourly Rate</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase">Basic Base Salary (Rs.) <span class="text-danger">*</span></label>
            <input type="number" name="basic_salary" id="empSalInput" class="form-control fw-bold" step="0.01" value="30000.00" required>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Save Employee Profile
        </button>
    </form>
</div>

<script>
function openAddEmployee() {
    document.getElementById('empPanelTitle').innerText = 'Register Employee';
    document.getElementById('empIdInput').value = '0';
    document.getElementById('empCodeInput').value = 'EMP-' + Math.floor(100 + Math.random() * 900);
    document.getElementById('empNameInput').value = '';
    document.getElementById('empDesigInput').value = '';
    document.getElementById('empPhoneInput').value = '';
    document.getElementById('empEmailInput').value = '';
    document.getElementById('empSalInput').value = '30000.00';
    RMS.openSlidePanel('employeeSlidePanel');
}

function openEditEmployee(e) {
    document.getElementById('empPanelTitle').innerText = 'Edit Employee: ' + e.full_name;
    document.getElementById('empIdInput').value = e.id;
    document.getElementById('empCodeInput').value = e.employee_code;
    document.getElementById('empNameInput').value = e.full_name;
    document.getElementById('empDesigInput').value = e.designation;
    document.getElementById('empPhoneInput').value = e.phone;
    document.getElementById('empEmailInput').value = e.email || '';
    document.getElementById('empJoinInput').value = e.joining_date;
    document.getElementById('empSalTypeInput').value = e.salary_type;
    document.getElementById('empSalInput').value = e.basic_salary;
    RMS.openSlidePanel('employeeSlidePanel');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
