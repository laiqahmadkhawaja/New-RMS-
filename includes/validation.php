<?php
// includes/validation.php - Input Sanitization & Validation Helpers

function cleanInput(?string $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function post(string $key, $default = null) {
    if (!isset($_POST[$key])) return $default;
    if (is_array($_POST[$key])) return $_POST[$key];
    return trim($_POST[$key]);
}

function get(string $key, $default = null) {
    if (!isset($_GET[$key])) return $default;
    if (is_array($_GET[$key])) return $_GET[$key];
    return trim($_GET[$key]);
}

function isPost(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function isGet(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
}

function toDecimal($value, int $decimals = 2): float {
    return round((float)$value, $decimals);
}

function validateRequired(array $fields, array $data): array {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $errors[] = "$label is required.";
        }
    }
    return $errors;
}
