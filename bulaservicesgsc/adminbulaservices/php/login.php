<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Neutralize global handlers from config.php so this endpoint can return JSON instead of hard-exiting on notices.
if (function_exists('restore_error_handler')) restore_error_handler();
if (function_exists('restore_exception_handler')) restore_exception_handler();


function jerr(string $msg, int $code = 500): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1) Input
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));
    if ($username === '' || $password === '') {
        jerr('Please enter both username and password.', 400);
    }

    // 2) DB
    $db = getDBConnection();

    // 3) Check required column exists (must_change_password)
    //    If this fails, it’ll throw and we’ll see the exact error.
    $db->query("SELECT must_change_password FROM admins LIMIT 1");

    // 4) Fetch user by username or email
 // Fetch admin by username or email (use distinct params to avoid HY093)
$stmt = $db->prepare("
    SELECT admin_id, username, email, password_hash, first_name, last_name, role,
           is_active, must_change_password
    FROM admins
    WHERE username = :u1 OR email = :u2
    LIMIT 1
");
$stmt->execute([':u1' => $username, ':u2' => $username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$admin) {
        jerr('Account not found.', 401);
    }
    if ((int)$admin['is_active'] !== 1) {
        jerr('Your account has been suspended. Please contact the administrator.', 403);
    }
    if (!password_verify($password, $admin['password_hash'])) {
        jerr('Incorrect password.', 401);
    }

    // 5) Update last_login (test write access)
    $upd = $db->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = :id");
    $upd->execute([':id' => $admin['admin_id']]);

    // 6) Start session
    $_SESSION['admin_id']       = (int)$admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name']     = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
    $_SESSION['admin_role']     = $admin['role'];
    $_SESSION['last_activity']  = time();

    // 7) Return JSON
    echo json_encode([
        'success' => true,
        'mustChangePassword' => ((int)($admin['must_change_password'] ?? 0) === 1),
        'role' => $admin['role'],
        'name' => $_SESSION['admin_name']
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Log full details to admin_errors.log (as configured in config.php)
    error_log('[LOGIN] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    // Show a useful error to the client so we can fix fast
    jerr('DEBUG: ' . $e->getMessage(), 500);
}
