<?php
/**
 * User Configuration File
 * Central configuration and initialization for user-facing functionality
 */
declare(strict_types=1);

/* ----------------------------------------------------------------
   1) CANONICAL SESSION (DO THIS FIRST — BEFORE ANY OUTPUT)
   - Use one cookie name only: BULA_SESSID
   - Expire legacy cookies (PHPSESSID / BARANGAY_BULA_SESSID) on all likely paths
   - Path=/ and Domain=bulaservicesgsc.com so the cookie works everywhere
----------------------------------------------------------------- */
$host   = 'bulaservicesgsc.com';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

/* Expire any old cookies so the browser stops sending duplicates */
$past        = gmdate('D, d M Y H:i:s T', time() - 3600);
$oldNames    = ['PHPSESSID', 'BARANGAY_BULA_SESSID'];
$oldPaths    = ['/', '/bulaservicesgsc/', '/bulaservicesgsc/userbulaservices/'];
foreach ($oldNames as $n) {
    foreach ($oldPaths as $p) {
        header(sprintf(
            'Set-Cookie: %s=deleted; Expires=%s; Max-Age=0; Path=%s; Domain=%s; %sHttpOnly; SameSite=Lax',
            $n, $past, $p, $host, $secure ? 'Secure; ' : ''
        ), false);
    }
}

/* Our one true session */
session_name('BULA_SESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $host,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/* Lock in the new cookie/id once per browser */
if (empty($_SESSION['__bootstrapped'])) {
    session_regenerate_id(true);
    $_SESSION['__bootstrapped'] = 1;
}

/* ----------------------------------------------------------------
   2) ERROR REPORTING (enable display only while debugging)
----------------------------------------------------------------- */
error_reporting(E_ALL);
ini_set('display_errors', '1'); // ⚠️ turn OFF in production
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/user_errors.log');

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

/* ----------------------------------------------------------------
   3) INCLUDES
----------------------------------------------------------------- */
require_once __DIR__ . '/../../server/db_connection.php';
require_once __DIR__ . '/../../server/auth_functions.php';

/* ----------------------------------------------------------------
   4) URL HELPERS
----------------------------------------------------------------- */
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://bulaservicesgsc.com/');
}
function site_url(string $path = ''): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}
function redirect_root(string $path): void {
    $url = site_url($path);
    header('Cache-Control: no-store');
    if (!headers_sent()) {
        header('Location: ' . $url, true, 303);
        exit;
    }
    echo '<script>location.replace(' . json_encode($url) . ')</script>';
    exit;
}

/* ----------------------------------------------------------------
   5) APP SETTINGS
----------------------------------------------------------------- */
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60);
define('SESSION_TIMEOUT', 1800);

/* ----------------------------------------------------------------
   6) SECURITY HEADERS
----------------------------------------------------------------- */
header(
  "Content-Security-Policy: " .
  "default-src 'self'; " .
  "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
  "style-src  'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
  "font-src   'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
  "img-src    'self' data: https://admin.bulaservicesgsc.com https://*.tile.openstreetmap.org; " .
  "connect-src 'self' https://bulaservicesgsc.com https://cdn.jsdelivr.net; " .
  "object-src 'none'; " .
  "base-uri 'self'; " .
  "frame-ancestors 'self'; " .
  "upgrade-insecure-requests;"
);
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

/* ----------------------------------------------------------------
   7) ACCESS CONTROL (simple + reliable)
   - Do not compare IP strictly here; we only require session user_id
   - Session timeout handled here too
----------------------------------------------------------------- */
function ensureUserAccess(): void {
    $currentFile = basename($_SERVER['PHP_SELF'] ?? '');
    $isLoginPage = in_array($currentFile, ['index.php', 'login.php'], true);

    if (!isLoggedIn()) {
        if (!$isLoginPage) {
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect_root('index.php?error=Please log in to access this page.');
        }
        return;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        if (!$isLoginPage) redirect_root('index.php?timeout=1');
        return;
    }
    $_SESSION['last_activity'] = time();
}

/* ----------------------------------------------------------------
   8) MISC HELPERS
----------------------------------------------------------------- */
function userUrl(string $path = ''): string {
    return site_url('userbulaservices/' . ltrim($path, '/'));
}
function adminUrl(string $path = ''): string {
    return site_url('adminbulaservices/' . ltrim($path, '/'));
}
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/* Optional timeout-warning flag */
if (isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity'] > (SESSION_TIMEOUT - 300))) {
    if (!isAjaxRequest() && basename($_SERVER['PHP_SELF'] ?? '') !== 'logout.php') {
        $_SESSION['show_timeout_warning'] = true;
    }
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