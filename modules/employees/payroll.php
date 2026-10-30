<?php
// modules/employees/payroll.php - Staff Payroll & Wage Disbursal (PRD 18.5)
$pageTitle = 'Payroll & Wages';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('employees.manage');

$db = getDB();
$branchId = currentBranchId();
$userId = currentUserId();
$selectedMonth = cleanInput(get('month', date('Y-m')));

// Handle generate payroll for all staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_generate_payroll'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $staff = $db->query("SELECT id, basic_salary FROM employees WHERE branch_id = $branchId AND is_active = 1")->fetchAll();
        $ins = $db->prepare("
            INSERT IGNORE INTO payrolls (branch_id, employee_id, month_year, basic_salary, allowances, deductions, net_salary, status)
            VALUES (?, ?, ?, ?, 0.00, 0.00, ?, 'draft')
        ");
        foreach ($staff as $stf) {
            $ins->execute([$branchId, $stf['id'], $selectedMonth, $stf['basic_salary'], $stf['basic_salary']]);
        }
        setFlash('success', "Payroll sheets for $selectedMonth generated.");
        header('Location: ' . BASE_URL . "modules/employees/payroll.php?month=$selectedMonth");
        exit;
    }
}

// Handle salary payout & post to accounting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pay_salary'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $payrollId = (int)post('payroll_id');
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT p.*, e.full_name FROM payrolls p JOIN employees e ON p.employee_id = e.id WHERE p.id = ?");
            $stmt->execute([$payrollId]);
            $pay = $stmt->fetch();
            if (!$pay) throw new Exception("Payroll slip not found.");

            $net = (float)$pay['net_salary'];

            $db->prepare("UPDATE payrolls SET status = 'paid', payment_date = CURDATE() WHERE id = ?")->execute([$payrollId]);

            // Post double-entry journal (Debit Salaries Expense: Account 15, Credit Cash in Register: Account 1)
            $lines = [
                [
                    'account_id' => 15, // Salaries Expense
                    'debit' => $net,
                    'credit' => 0.00,
                    'notes' => "Salary payment for {$pay['full_name']} ({$pay['month_year']})"
                ],
                [
                    'account_id' => 1, // Cash in Register
                    'debit' => 0.00,
                    'credit' => $net,
                    'notes' => "Salary cash disbursal"
                ]
            ];

            AccountingEngine::createJournalEntry(
                $branchId,
                'payroll',
                $payrollId,
                "Payroll Disbursal - {$pay['full_name']} ({$pay['month_year']})",
                $lines,
                $userId
            );

            $db->commit();
            setFlash('success', "Salary for {$pay['full_name']} paid and recorded in General Ledger.");
            header('Location: ' . BASE_URL . "modules/employees/payroll.php?month=$selectedMonth");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', $e->getMessage());
        }
    }
}

$payrolls = $db->query("
    SELECT p.*, e.employee_code, e.full_name, e.designation 
    FROM payrolls p 
    JOIN employees e ON p.employee_id = e.id 
    WHERE p.branch_id = $branchId AND p.month_year = '$selectedMonth' 
    ORDER BY e.full_name ASC
")->fetchAll();

$totalPayroll = array_sum(array_column($payrolls, 'net_salary'));
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-cash text-danger me-2"></i> Payroll & Wages Disbursal</h3>
        <p class="text-muted small mb-0">Total Net Payroll for <?= $selectedMonth ?>: <b class="text-danger fs-6"><?= formatCurrency($totalPayroll) ?></b></p>
    </div>

    <!-- Actions -->
    <div class="d-flex align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center gap-1">
            <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()">
        </form>

        <form method="POST" action="" class="d-inline">
            <?= csrfField(); ?>
            <input type="hidden" name="action_generate_payroll" value="1">
            <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                <i class="bi bi-magic me-1"></i> Generate Month Slips
            </button>
        </form>
    </div>
</div>

<!-- Payroll Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th class="text-end">Basic Salary</th>
                    <th class="text-end">Allowances</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end">Net Salary</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payrolls)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-2"></i>
                            <div class="mt-2">No payroll slips generated for <?= $selectedMonth ?>. Click "Generate Month Slips" above.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payrolls as $p): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($p['full_name']) ?></span>
                                <small class="text-muted d-block font-monospace"><?= htmlspecialchars($p['employee_code']) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['designation']) ?></span></td>
                            <td class="text-end"><?= formatCurrency($p['basic_salary']) ?></td>
                            <td class="text-end text-success">+<?= formatCurrency($p['allowances']) ?></td>
                            <td class="text-end text-danger">-<?= formatCurrency($p['deductions']) ?></td>
                            <td class="text-end fw-bold fs-6 text-dark"><?= formatCurrency($p['net_salary']) ?></td>
                            <td>
                                <span class="badge <?= $p['status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?> text-capitalize"><?= $p['status'] ?></span>
                                <?php if ($p['payment_date']): ?>
                                    <small class="text-muted d-block"><?= date('d-M', strtotime($p['payment_date'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($p['status'] !== 'paid'): ?>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Disburse and pay salary of <?= formatCurrency($p['net_salary']) ?> to <?= htmlspecialchars($p['full_name'], ENT_QUOTES) ?>?')">
                                        <?= csrfField(); ?>
                                        <input type="hidden" name="action_pay_salary" value="1">
                                        <input type="hidden" name="payroll_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                            <i class="bi bi-cash-stack me-1"></i> Disburse & Post
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary" title="Print Payslip">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
