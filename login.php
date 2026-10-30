<?php
// login.php - Secure User Login Page
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = post('csrf_token');
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = post('username');
        $password = post('password');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            if (loginUser($username, $password)) {
                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials or inactive account.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?= htmlspecialchars(getSetting('business_name', 'Spice & Savor')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="login-card p-4 p-md-5">
    <div class="text-center mb-4">
        <div class="bg-danger text-white d-inline-flex p-3 rounded-4 mb-2 shadow">
            <i class="bi bi-fire fs-1"></i>
        </div>
        <h4 class="fw-bold mb-1"><?= htmlspecialchars(getSetting('business_name', 'Spice & Savor Bistro')) ?></h4>
        <p class="text-muted small">Restaurant Management & POS System</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>login.php">
        <?= csrfField(); ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Username or Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-secondary"></i></span>
                <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="e.g. admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold text-muted">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-secondary"></i></span>
                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold shadow-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to POS
        </button>

        <div class="mt-4 p-3 bg-light rounded-3 small text-muted border">
            <div class="fw-bold mb-1 text-dark">Default Credentials:</div>
            <div>Username: <code>admin</code></div>
            <div>Password: <code>admin123</code></div>
        </div>
    </form>
</div>
</body>
</html>
