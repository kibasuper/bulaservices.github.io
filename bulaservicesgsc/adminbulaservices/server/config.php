<?php
/**
 * Admin Configuration File
 * 
 * Central configuration and initialization for admin-facing functionality
 */

declare(strict_types=1);

if (!function_exists('userUrl')) {
    function userUrl(string $path = ''): string {
        return site_url('userbulaservices/' . ltrim($path, '/'));
    }
}

if (!function_exists('adminUrl')) {
    function adminUrl(string $path = ''): string {
        return site_url('adminbulaservices/' . ltrim($path, '/'));
    }
}


// ---------------- Session Handling ----------------
// Use a unique session name for admin panel
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ADMIN_BULA_SESSID');
    session_start();
} else {
    // If already active, verify it’s the correct one
    if (session_name() !== 'ADMIN_BULA_SESSID') {
        session_write_close();
        session_name('ADMIN_BULA_SESSID');
        session_start();
    }
}

// ---------------- Error Reporting ----------------
error_reporting(E_ALL);
ini_set('display_errors', '1'); // ⚠️ Disable in production
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/admin_errors.log');

set_exception_handler(function ($e) {
    echo "<script>console.error('Uncaught Exception: " . addslashes($e->getMessage()) . "');</script>";
    http_response_code(500);
    exit();
});

set_error_handler(function ($severity, $message, $file, $line) {
    echo "<script>console.error('PHP Error: " . addslashes($message) . " in $file on line $line');</script>";
    http_response_code(500);
    exit();
});

// ---------------- Includes ----------------
require_once __DIR__ . '/../../server/db_connection.php';
$db = getDBConnection();
require_once __DIR__ . '/../../server/auth_functions.php'; // can reuse user functions

// ---------------- Base URL Config ----------------
define('ADMIN_BASE_URL', '/bulaservicesgsc/adminbulaservices/');
define('USER_BASE_URL', '/bulaservicesgsc/userbulaservices/');
define('BASE_PATH', realpath(__DIR__ . '/../'));

// ---------------- App Settings ----------------
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 minutes
define('SESSION_TIMEOUT', 1800);       // 30 minutes

// === Public upload targets for Announcement images (MAIN site) ===
define('PUBLIC_WEB_ROOT', '/var/www/bulaservices/data/www/bulaservicesgsc.com'); // filesystem root of MAIN site
define('PUBLIC_BASE_URL',  'https://bulaservicesgsc.com');                        // public origin of MAIN site


// ---------------- Security Headers ----------------
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
    "style-src  'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
    "connect-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; " .
    "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
    
    "img-src  'self' data: blob: https://ui-avatars.com https://bulaservicesgsc.com https://admin.bulaservicesgsc.com; " .
    "object-src 'none'; " .
    "frame-src 'self'; " .
    "child-src 'self'; " .
    "media-src 'self' data: blob:;"
);



header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ---------------- Utility Functions ----------------

/**
 * Verify admin access with multiple security checks
 */
function ensureAdminAccess(): void {
    $currentFile = basename($_SERVER['PHP_SELF']);
    $isLoginPage = in_array($currentFile, ['index.php', 'login.php'], true);

    // Check login
    if (!isLoggedInAdmin()) {
        if (!$isLoginPage) {
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect('index.php');
        }
        return;
    }

    // Session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        if (!$isLoginPage) {
            redirect('index.php?timeout=1');
        }
        return;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();

    // Verify session consistency (IP + user agent)
    if (!isset($_SESSION['admin_ip'], $_SESSION['admin_agent'])) {
        session_unset();
        session_destroy();
        if (!$isLoginPage) {
            redirect('index.php?security=1');
        }
        return;
    }

    $currentIpPrefix = implode('.', array_slice(explode('.', $_SERVER['REMOTE_ADDR']), 0, 2));
    $sessionIpPrefix = implode('.', array_slice(explode('.', $_SESSION['admin_ip']), 0, 2));

    if ($currentIpPrefix !== $sessionIpPrefix || $_SESSION['admin_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        if (!$isLoginPage) {
            redirect('index.php?security=1');
        }
    }
}

/**
 * Check if admin is logged in
 */
function isLoggedInAdmin(): bool {
    return !empty($_SESSION['admin_id']);
}

/**
 * Build absolute URL for admin pages
 */
function adminUrl(string $path = ''): string {
    return ADMIN_BASE_URL . ltrim($path, '/');
}

/**
 * Get current admin ID safely
 */
function getCurrentAdminId(): ?int {
    return $_SESSION['admin_id'] ?? null;
}

// ---------------- Timeout Warning ----------------
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity'] > (SESSION_TIMEOUT - 300))) { // 5 min before timeout
    if (!isAjaxRequest() && basename($_SERVER['PHP_SELF']) !== 'logout.php') {
        $_SESSION['show_timeout_warning'] = true;
    }
}

/**
 * Check if current request is AJAX
 */
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}


// ---- Project paths (so all scripts agree on where /uploads lives) ----
if (!defined('PROJECT_ROOT')) {
    // config.php is .../bulaservicesgsc.com/bulaservicesgsc/userbulaservices/server/config.php
    // -> dirname(__DIR__) = .../userbulaservices
    // -> dirname(dirname(__DIR__)) = .../bulaservicesgsc   (this folder contains /uploads)
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', PROJECT_ROOT . '/uploads');  // /var/www/.../bulaservicesgsc/uploads
}