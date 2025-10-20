<?php
require_once __DIR__ . '/db_connection.php';

/* ------------ Sanitization ------------ */
function sanitizeInput($data) {
    if (is_array($data)) return array_map('sanitizeInput', $data);
    $data = trim((string)$data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/* ------------ Authentication ------------ */
/**
 * Keep this simple to avoid false negatives right after login.
 * If we have a user_id in session, user is logged in.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['role'] ?? null) === 'admin');
}

/**
 * Generic redirect (compat). Prefer redirect_root() when possible.
 */
function redirect(string $url, int $statusCode = 303): void {
    $safeUrl = preg_replace('/[\r\n]+/', '', trim($url));
    if (!preg_match('#^https?://#i', $safeUrl)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $safeUrl = $scheme . '://' . $host . '/' . ltrim($safeUrl, '/');
    }
    if (!headers_sent()) {
        header("Location: " . filter_var($safeUrl, FILTER_SANITIZE_URL), true, $statusCode);
        exit();
    }
    echo '<script>window.location.href=' . json_encode($safeUrl) . ';</script>';
    exit();
}

/* ------------ Session / login helpers ------------ */
function regenerateSession(): void {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

/** On successful login, store minimal state */
function loginUser(int $userId, string $role): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = $userId;
    $_SESSION['role']          = $role;
    $_SESSION['last_activity'] = time();
    generateCsrfToken();
}

/* ------------ File upload (profile picture) ------------ */
define('PROFILE_PIC_DIR', __DIR__ . '/../uploads/profile_pictures/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif'
]);

function uploadProfilePicture(array $file): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error: ' . $file['error']);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, ALLOWED_FILE_TYPES)) {
        throw new RuntimeException('Invalid file type. Allowed: ' . implode(', ', array_keys(ALLOWED_FILE_TYPES)));
    }
    if (!is_dir(PROFILE_PIC_DIR)) {
        mkdir(PROFILE_PIC_DIR, 0755, true);
    }
    $extension   = ALLOWED_FILE_TYPES[$mime];
    $filename    = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = PROFILE_PIC_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to move uploaded file');
    }
    return '/uploads/profile_pictures/' . $filename;
}

/* ------------ CSRF ------------ */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ------------ NO extra session_start here ------------
   Session is fully controlled by /server/config.php.
------------------------------------------------------- */
