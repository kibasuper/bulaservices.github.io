<?php
// php/logout.php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php'; // sets the session name and starts session

header('Content-Type: application/json; charset=utf-8');

// Make sure the session is active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear all session data
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Use the active session name (config.php uses a custom one)
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy the session completely
session_destroy();

// Optional: extra hardening — avoid reusing the same ID
if (function_exists('session_create_id')) {
    session_id(session_create_id()); // noop, just ensures no old id reused
}

echo json_encode(['ok' => true]);
