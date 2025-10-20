<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../server/config.php';

try {
    $pdo = getDBConnection();

    // One input field that can be email OR username
    $identifier = sanitizeInput($_POST['email'] ?? $_POST['identifier'] ?? ''); // adapt to your HTML name
    $password   = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        echo json_encode(['success'=>false,'message'=>'Email/Username and password are required']); exit;
    }

    $byEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower(trim($identifier)) : null;

    if ($byEmail) {
        $stmt = $pdo->prepare("SELECT id, email, username, password, first_name, last_name, is_active, email_verified, status
                               FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$byEmail]);
    } else {
        // username path (DB collation is case-insensitive by default)
        $stmt = $pdo->prepare("SELECT id, email, username, password, first_name, last_name, is_active, email_verified, status
                               FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([trim($identifier)]);
    }

    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success'=>false,'message'=>'Invalid credentials']); exit;
    }

    if (!(int)$user['is_active']) {
        echo json_encode(['success'=>false,'message'=>'Account is deactivated. Please contact administrator.']); exit;
    }

    if (!(int)$user['email_verified'] || $user['status'] !== 'active') {
        echo json_encode(['success'=>false,'message'=>'Please verify your email before logging in.']); exit;
    }

    if (!password_verify($password, $user['password'])) {
        $pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id=?")->execute([$user['id']]);
        echo json_encode(['success'=>false,'message'=>'Invalid credentials']); exit;
    }

    // success
    loginUser((int)$user['id'], 'user');
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name']  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    $pdo->prepare("UPDATE users SET last_login=NOW(), failed_login_attempts=0 WHERE id=?")->execute([$user['id']]);

    echo json_encode(['success'=>true,'message'=>'Login successful!','redirect'=>'/home.php']);
} catch (Throwable $e) {
    error_log('login_api error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error. Please try again.']);
}
