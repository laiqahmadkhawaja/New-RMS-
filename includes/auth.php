<?php
// includes/auth.php - Authentication & Authorization (RBAC)

require_once __DIR__ . '/../config/app.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentBranchId(): int {
    return (int)($_SESSION['branch_id'] ?? 1);
}

function currentBranchName(): string {
    return $_SESSION['branch_name'] ?? 'Main Branch';
}

function userHasPermission(string $permissionCode): bool {
    if (!isLoggedIn()) {
        return false;
    }
    // Super admin bypass
    if (!empty($_SESSION['user']['role_name']) && $_SESSION['user']['role_name'] === 'super_admin') {
        return true;
    }
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permissionCode, $permissions, true);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to continue.";
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requirePermission(string $permissionCode): void {
    requireAuth();
    if (!userHasPermission($permissionCode)) {
        http_response_code(403);
        include __DIR__ . '/../modules/errors/403.php';
        exit;
    }
}

function loginUser(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.display_name as role_display, b.name as branch_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        JOIN branches b ON u.branch_id = b.id 
        WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1 
        LIMIT 1
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate session id for security
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['branch_id'] = (int)$user['branch_id'];
        $_SESSION['branch_name'] = $user['branch_name'];
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id'],
            'role_name' => $user['role_name'],
            'role_display' => $user['role_display'],
            'branch_id' => (int)$user['branch_id'],
            'branch_name' => $user['branch_name'],
        ];

        // Load permissions
        $permStmt = $db->prepare("
            SELECT p.code 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = ?
        ");
        $permStmt->execute([$user['role_id']]);
        $_SESSION['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);

        // Update last login
        $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

        // Audit log
        require_once __DIR__ . '/audit.php';
        logAudit('login', 'users', $user['id'], null, ['username' => $username, 'status' => 'success']);

        return true;
    }

    // Failed login audit
    require_once __DIR__ . '/audit.php';
    logAudit('failed_login', 'users', null, null, ['username' => $username]);

    return false;
}

function logoutUser(): void {
    if (isLoggedIn()) {
        require_once __DIR__ . '/audit.php';
        logAudit('logout', 'users', currentUserId(), null, null);
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
