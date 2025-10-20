<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Return JSON error
function jerr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Must be logged in from the login step that showed the modal
    if (empty($_SESSION['admin_id'])) {
        jerr('Session expired. Please log in again.', 401);
    }

    $adminId         = (int)$_SESSION['admin_id'];
    $currentPassword = trim((string)($_POST['current_password'] ?? ''));
    $newPassword     = trim((string)($_POST['new_password'] ?? ''));
    $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        jerr('Please fill in all fields.', 422);
    }
    if ($newPassword !== $confirmPassword) {
        jerr('New password and confirm password do not match.', 422);
    }
    if (strlen($newPassword) < 8) {
        jerr('New password must be at least 8 characters.', 422);
    }

    $db = getDBConnection();

    // Get the current hash for this logged-in admin
    $q = $db->prepare("SELECT password_hash FROM admins WHERE admin_id = :id LIMIT 1");
    $q->execute([':id' => $adminId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jerr('Account not found.', 404);
    }

    // Verify current password
    if (!password_verify($currentPassword, $row['password_hash'])) {
        jerr('Current password is incorrect.', 401);
    }

    // Hash new password and update
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $u = $db->prepare("
        UPDATE admins
        SET password_hash = :h,
            must_change_password = 0,
            password_changed_at = NOW()
        WHERE admin_id = :id
        LIMIT 1
    ");
    $u->execute([':h' => $newHash, ':id' => $adminId]);

    if ($u->rowCount() < 1) {
        // This means nothing changed; extremely rare, but report it so we can debug
        jerr('Password was not updated. Please try again.', 500);
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Log and return a safe message
    error_log('[CHANGE_PASSWORD] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    jerr('Server error. Please try again.', 500);
}
