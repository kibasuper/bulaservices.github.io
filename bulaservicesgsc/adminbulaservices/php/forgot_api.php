<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
// Use the existing user mailer
require_once __DIR__ . '/../../userbulaservices/php/mailer.php';

header('Content-Type: application/json; charset=utf-8');

// Error handling
set_exception_handler(function($e) { 
    error_log("Admin Forgot Password Exception: " . $e->getMessage());
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Server error occurred']); 
    exit; 
});

set_error_handler(function($severity, $message, $file, $line) {
    error_log("Admin Forgot Password Error: $message in $file on line $line");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    exit;
});

function outputResponse($success, $message = '') {
    http_response_code($success ? 200 : 400);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputResponse(false, 'Method not allowed');
}

$email = trim($_POST['email'] ?? '');

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Generic response to prevent email enumeration
    outputResponse(true, 'If your email exists in our system, a password reset link has been sent.');
}

try {
    $db = getDBConnection();
    
    // Look up admin by email (case-insensitive)
    $stmt = $db->prepare("SELECT admin_id, username, email FROM admins WHERE LOWER(email) = LOWER(?) AND is_active = '1' LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always return generic response to prevent email enumeration
    if (!$admin) {
        outputResponse(true, 'If your email exists in our system, a password reset link has been sent.');
    }

    // Check for recent reset requests (throttling: 1 request per 5 minutes)
    $throttleStmt = $db->prepare("SELECT reset_token_expires FROM admins WHERE admin_id = ?");
    $throttleStmt->execute([$admin['admin_id']]);
    $adminData = $throttleStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminData && !empty($adminData['reset_token_expires'])) {
        $expires = strtotime($adminData['reset_token_expires']);
        if ($expires && (time() - $expires) < -300) { // If token expires in more than 5 minutes
            outputResponse(true, 'If your email exists in our system, a password reset link has been sent.');
        }
    }

    // Generate secure reset token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes expiration

    // Store token hash in database
    $updateStmt = $db->prepare("UPDATE admins SET reset_token_hash = ?, reset_token_expires = ? WHERE admin_id = ?");
    $updateStmt->execute([$tokenHash, $expiresAt, $admin['admin_id']]);

    // Send reset email using the admin-specific function from user mailer
    $result = sendAdminPasswordResetLink($admin['email'], $admin['username'], $rawToken);
    
    if (!$result['ok']) {
        error_log("Failed to send admin reset email to {$admin['email']}: " . ($result['error'] ?? 'Unknown error'));
    }

    // Generic success response
    outputResponse(true, 'If your email exists in our system, a password reset link has been sent.');

} catch (Throwable $e) {
    error_log("Admin forgot password error: " . $e->getMessage());
    // Still return generic response even on error
    outputResponse(true, 'If your email exists in our system, a password reset link has been sent.');
}