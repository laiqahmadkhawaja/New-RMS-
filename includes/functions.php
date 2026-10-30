<?php
// includes/functions.php - Universal Helper Functions

require_once __DIR__ . '/../config/database.php';

function getSetting(string $key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value, data_type FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row) {
            $val = $row['setting_value'];
            if ($row['data_type'] === 'boolean') $val = (bool)$val;
            elseif ($row['data_type'] === 'integer') $val = (int)$val;
            elseif ($row['data_type'] === 'decimal') $val = (float)$val;
            elseif ($row['data_type'] === 'json') $val = json_decode($val, true);
            $cache[$key] = $val;
            return $val;
        }
    } catch (Exception $e) {}
    return $default;
}

function setSetting(string $key, $value): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([(string)$value, $key]);
    } catch (Exception $e) {
        return false;
    }
}

function formatCurrency($amount): string {
    $symbol = getSetting('currency_symbol', 'Rs.');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

function renderFlash(): string {
    $html = '';
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $key = 'flash_' . $type;
        if (!empty($_SESSION[$key])) {
            $bsClass = $type === 'error' ? 'danger' : $type;
            $html .= '<div class="alert alert-' . $bsClass . ' alert-dismissible fade show my-2" role="alert">
                ' . htmlspecialchars($_SESSION[$key]) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
}

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateDocumentNumber(string $type, string $prefix): string {
    $db = getDB();
    $datePart = date('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return $prefix . $datePart . '-' . $rand;
}

function getActiveCashShift(int $branchId, ?int $userId = null): ?array {
    $db = getDB();
    $sql = "SELECT * FROM cash_shifts WHERE branch_id = ? AND status = 'open'";
    $params = [$branchId];
    if ($userId !== null) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    $sql .= " ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $shift = $stmt->fetch();
    return $shift ?: null;
}

function sendOrderToKitchen(PDO $db, int $orderId): array {
    // Fetch stations for items in this order
    $stmt = $db->prepare("
        SELECT DISTINCT kitchen_station_id 
        FROM order_items 
        WHERE order_id = ? AND kitchen_station_id IS NOT NULL
    ");
    $stmt->execute([$orderId]);
    $stations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $createdTickets = [];
    foreach ($stations as $stId) {
        $ticketNumber = 'KOT-' . date('ymd') . '-' . $orderId . '-' . $stId;
        
        // Check if ticket exists
        $tCheck = $db->prepare("SELECT id FROM kitchen_tickets WHERE order_id = ? AND kitchen_station_id = ?");
        $tCheck->execute([$orderId, $stId]);
        $ticketId = $tCheck->fetchColumn();

        if (!$ticketId) {
            $tStmt = $db->prepare("
                INSERT INTO kitchen_tickets (ticket_number, order_id, kitchen_station_id, ticket_type, status, created_at)
                VALUES (?, ?, ?, 'initial', 'pending', NOW())
            ");
            $tStmt->execute([$ticketNumber, $orderId, $stId]);
            $ticketId = (int)$db->lastInsertId();
        }

        // Link items
        $itemQuery = $db->prepare("SELECT id FROM order_items WHERE order_id = ? AND kitchen_station_id = ?");
        $itemQuery->execute([$orderId, $stId]);
        $orderItemIds = $itemQuery->fetchAll(PDO::FETCH_COLUMN);

        $linkStmt = $db->prepare("INSERT IGNORE INTO kitchen_ticket_items (kitchen_ticket_id, order_item_id, status) VALUES (?, ?, 'pending')");
        foreach ($orderItemIds as $oiId) {
            $linkStmt->execute([$ticketId, $oiId]);
        }
        $createdTickets[] = $ticketNumber;
    }

    // Update order kitchen status to preparing if still pending
    $db->prepare("UPDATE orders SET kitchen_status = 'preparing' WHERE id = ? AND kitchen_status IN ('pending', 'none')")->execute([$orderId]);

    return $createdTickets;
}
