<?php
// includes/audit.php - Audit Trail Recording

require_once __DIR__ . '/../config/database.php';

function logAudit(string $action, string $tableName, ?int $recordId = null, $oldValues = null, $newValues = null): void {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            $agent
        ]);
    } catch (Exception $e) {
        // Silently log to file rather than breaking the application flow
        error_log("Audit log failure: " . $e->getMessage());
    }
}
