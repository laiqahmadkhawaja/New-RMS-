<?php
// modules/backup/index.php - Database Backup & Disaster Recovery (PRD 27)
$pageTitle = 'Backup & Recovery';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('users.manage');

$backupDir = ROOT_PATH . 'storage/backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// 1. CREATE BACKUP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_backup'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $db = getDB();
        $filename = 'backup_rms_' . date('Ymd_His') . '.sql';
        $filepath = $backupDir . $filename;

        // Dump tables
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sqlDump = "-- Restaurant Management System Database Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $tbl) {
            $createTable = $db->query("SHOW CREATE TABLE `$tbl`")->fetch();
            $sqlDump .= "DROP TABLE IF EXISTS `$tbl`;\n";
            $sqlDump .= $createTable['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = array_keys($rows[0]);
                $colNames = implode('`, `', $cols);
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($db) {
                        return $v === null ? 'NULL' : $db->quote($v);
                    }, array_values($row));
                    $sqlDump .= "INSERT INTO `$tbl` (`$colNames`) VALUES (" . implode(', ', $vals) . ");\n";
                }
                $sqlDump .= "\n";
            }
        }
        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        file_put_contents($filepath, $sqlDump);
        logAudit('backup_created', 'system', null, null, ['filename' => $filename]);
        setFlash('success', "Full database backup '$filename' created successfully!");
        header('Location: ' . BASE_URL . 'modules/backup/index.php');
        exit;
    }
}

// 2. DOWNLOAD BACKUP
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $fullPath = $backupDir . $file;
    if (file_exists($fullPath)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}

// 3. RESTORE BACKUP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_restore_backup'])) {
    if (verifyCsrfToken(post('csrf_token'))) {
        $file = basename(post('backup_file'));
        $fullPath = $backupDir . $file;
        if (file_exists($fullPath)) {
            $db = getDB();
            $sql = file_get_contents($fullPath);
            $db->exec($sql);
            logAudit('backup_restored', 'system', null, null, ['filename' => $file]);
            setFlash('success', "Database successfully restored from '$file'!");
            header('Location: ' . BASE_URL . 'modules/backup/index.php');
            exit;
        }
    }
}

// List existing backup files
$backupFiles = glob($backupDir . '*.sql');
rsort($backupFiles);
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-database-down text-danger me-2"></i> Database Backup & Disaster Recovery</h3>
        <p class="text-muted small mb-0">Create full SQL database snapshots, download backups locally, and restore data</p>
    </div>

    <form method="POST" action="">
        <?= csrfField(); ?>
        <input type="hidden" name="action_create_backup" value="1">
        <button type="submit" class="btn btn-danger btn-sm fw-semibold shadow-sm">
            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create Full Backup Now
        </button>
    </form>
</div>

<!-- Backups Table -->
<div class="card-rms overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light text-uppercase fw-bold">
                <tr>
                    <th>Backup Archive Name</th>
                    <th>Date & Time Created</th>
                    <th class="text-end">File Size</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backupFiles)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No database backups generated yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($backupFiles as $bf): 
                        $bName = basename($bf);
                        $size = round(filesize($bf) / 1024, 1);
                        $created = filemtime($bf);
                    ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-code text-danger me-2 fs-5"></i>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($bName) ?></span>
                            </td>
                            <td class="text-muted"><?= date('d-M-Y H:i:s', $created) ?></td>
                            <td class="text-end fw-semibold"><?= $size ?> KB</td>
                            <td class="text-end">
                                <a href="?download=<?= urlencode($bName) ?>" class="btn btn-sm btn-outline-secondary" title="Download SQL">
                                    <i class="bi bi-download me-1"></i> Download
                                </a>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('CAUTION: Restoring will overwrite current database state with this backup file. Proceed?')">
                                    <?= csrfField(); ?>
                                    <input type="hidden" name="action_restore_backup" value="1">
                                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($bName) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger fw-semibold">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
