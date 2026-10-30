<?php
// modules/audit/index.php - Immutable System Audit Logs (PRD 27)
$pageTitle = 'Audit Trail';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('users.manage');

$db = getDB();

$actionFilter = cleanInput(get('action', ''));
$tableFilter = cleanInput(get('table', ''));

$sql = "
    SELECT al.*, u.full_name as user_name, u.username 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE 1=1 
";
$params = [];
if (!empty($actionFilter)) {
    $sql .= " AND al.action = ?";
    $params[] = $actionFilter;
}
if (!empty($tableFilter)) {
    $sql .= " AND al.table_name = ?";
    $params[] = $tableFilter;
}
$sql .= " ORDER BY al.id DESC LIMIT 150";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$tables = $db->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-activity text-danger me-2"></i> Audit Trail & Activity Logs</h3>
        <p class="text-muted small mb-0">Tamper-evident logs of user activities, financial adjustments, refunds, and cancellations</p>
    </div>
</div>

<!-- Filters -->
<div class="card-rms p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label small fw-semibold text-muted">Activity Action</label>
            <select name="action" class="form-select form-select-sm">
                <option value="">All Actions</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= $act ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= $act ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5">
            <label class="form-label small fw-semibold text-muted">Entity / Database Table</label>
            <select name="table" class="form-select form-select-sm">
                <option value="">All Entities</option>
                <?php foreach ($tables as $tbl): ?>
                    <option value="<?= $tbl ?>" <?= $tableFilter === $tbl ? 'selected' : '' ?>><?= $tbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold">
                <i class="bi bi-funnel"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Audit Logs Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light text-uppercase fw-bold">
                <tr>
                    <th>Timestamp</th>
                    <th>User / Actor</th>
                    <th>Action</th>
                    <th>Target Table & ID</th>
                    <th>Audit Payload / Values</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No audit events logged.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-muted"><?= date('d-M-Y H:i:s', strtotime($l['created_at'])) ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($l['user_name'] ?? 'System') ?></span>
                                <small class="text-muted d-block">@<?= htmlspecialchars($l['username'] ?? 'system') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($l['action']) ?></span>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($l['table_name']) ?></code>
                                <?= $l['record_id'] ? '#' . $l['record_id'] : '' ?>
                            </td>
                            <td>
                                <?php if ($l['new_values']): ?>
                                    <pre class="mb-0 bg-light p-1 rounded small" style="max-width: 320px; white-space: pre-wrap; font-size: 0.72rem;"><?= htmlspecialchars($l['new_values']) ?></pre>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
