<?php
// ─── Authentication Helper ───────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => isset($_SERVER['HTTPS']),
]);

function is_logged_in(): bool
{
    return !empty($_SESSION['authenticated']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
