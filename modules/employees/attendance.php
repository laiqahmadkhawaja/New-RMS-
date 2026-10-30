<?php
// modules/employees/attendance.php - Daily Staff Attendance Tracking (PRD 18.2)
$pageTitle = 'Attendance Management';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('employees.manage');

$db = getDB();
$branchId = currentBranchId();
$date = cleanInput(get('date', date('Y-m-d')));

// Handle bulk attendance save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken(post('csrf_token'))) {
        $statuses = post('status', []);
        $clockIns = post('clock_in', []);
        $clockOuts = post('clock_out', []);
        $notes = post('notes', []);

        $stmt = $db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, clock_out, status, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), clock_out = VALUES(clock_out), status = VALUES(status), notes = VALUES(notes)
        ");

        foreach ($statuses as $empId => $st) {
            $cIn = !empty($clockIns[$empId]) ? $clockIns[$empId] : null;
            $cOut = !empty($clockOuts[$empId]) ? $clockOuts[$empId] : null;
            $nt = cleanInput($notes[$empId] ?? '');
            $stmt->execute([(int)$empId, $date, $cIn, $cOut, $st, $nt]);
        }

        setFlash('success', 'Attendance for ' . date('d-M-Y', strtotime($date)) . ' saved successfully.');
        header('Location: ' . BASE_URL . 'modules/employees/attendance.php?date=' . $date);
        exit;
    }
}

// Fetch employees with attendance for selected date
$records = $db->query("
    SELECT e.id as employee_id, e.employee_code, e.full_name, e.designation,
           a.clock_in, a.clock_out, a.status, a.notes 
    FROM employees e 
    LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = '$date' 
    WHERE e.branch_id = $branchId AND e.is_active = 1 
    ORDER BY e.full_name ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-clock-history text-danger me-2"></i> Staff Daily Attendance</h3>
        <p class="text-muted small mb-0">Record daily check-ins, departures, overtime, and leave records</p>
    </div>

    <!-- Date Filter -->
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 fw-semibold text-muted small">Date:</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
        <a href="<?= BASE_URL ?>modules/employees/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Staff Directory
        </a>
    </form>
</div>

<!-- Attendance Form -->
<form method="POST" action="">
    <?= csrfField(); ?>
    <div class="card-rms overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Attendance Status</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Remarks / Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): 
                        $status = $r['status'] ?? 'present';
                    ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($r['full_name']) ?></span>
                                <small class="text-muted d-block font-monospace"><?= htmlspecialchars($r['employee_code']) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['designation']) ?></span></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="status[<?= $r['employee_id'] ?>]" id="pres_<?= $r['employee_id'] ?>" value="present" <?= $status === 'present' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success" for="pres_<?= $r['employee_id'] ?>">Present</label>

                                    <input type="radio" class="btn-check" name="status[<?= $r['employee_id'] ?>]" id="late_<?= $r['employee_id'] ?>" value="late" <?= $status === 'late' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-warning" for="late_<?= $r['employee_id'] ?>">Late</label>

                                    <input type="radio" class="btn-check" name="status[<?= $r['employee_id'] ?>]" id="abs_<?= $r['employee_id'] ?>" value="absent" <?= $status === 'absent' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-danger" for="abs_<?= $r['employee_id'] ?>">Absent</label>

                                    <input type="radio" class="btn-check" name="status[<?= $r['employee_id'] ?>]" id="leave_<?= $r['employee_id'] ?>" value="leave" <?= $status === 'leave' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="leave_<?= $r['employee_id'] ?>">Leave</label>
                                </div>
                            </td>
                            <td>
                                <input type="time" name="clock_in[<?= $r['employee_id'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['clock_in'] ?? '09:00') ?>">
                            </td>
                            <td>
                                <input type="time" name="clock_out[<?= $r['employee_id'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['clock_out'] ?? '18:00') ?>">
                            </td>
                            <td>
                                <input type="text" name="notes[<?= $r['employee_id'] ?>]" class="form-control form-control-sm" placeholder="Optional notes..." value="<?= htmlspecialchars($r['notes'] ?? '') ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-light border-top text-end">
            <button type="submit" class="btn btn-danger fw-semibold px-4">
                <i class="bi bi-check2-circle me-1"></i> Save Daily Attendance
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
