<?php
// config/app.php - Application Configuration & Session Setup

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

date_default_timezone_set('Asia/Karachi');

define('APP_NAME', 'Spice & Savor RMS');
define('APP_VERSION', '1.0.0');

// Dynamic base URL detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim(explode('/modules', explode('/ajax', $scriptDir)[0])[0], '/');

define('BASE_URL', $protocol . $host . $basePath . '/');
define('ROOT_PATH', realpath(__DIR__ . '/..') . '/');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
