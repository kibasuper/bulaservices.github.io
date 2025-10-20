<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../server/config.php';
require_once __DIR__ . '/mailer.php';

try {
    $pdo = getDBConnection();

    // Read POST or JSON
    $input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim($input['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']); exit;
    }

    // find unverified user
    $stmt = $pdo->prepare("SELECT id, email_verified, status FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        echo json_encode(['success'=>false,'message'=>'Account not found.']); exit;
    }
    if ((int)$u['email_verified'] === 1 || $u['status'] === 'active') {
        echo json_encode(['success'=>false,'message'=>'Account already verified.']); exit;
    }

    $pdo->beginTransaction();

    // mark terms accepted (DB time)
    $pdo->prepare("UPDATE users SET terms_accepted_at = NOW() WHERE id=?")->execute([(int)$u['id']]);

    // rate-limit: 1 per minute (DB time)
    $recent = $pdo->prepare("
        SELECT 1 FROM email_verifications
        WHERE user_id=? AND created_at > (NOW() - INTERVAL 1 MINUTE)
        LIMIT 1
    ");
    $recent->execute([(int)$u['id']]);
    if ($recent->fetch()) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'We sent a verification recently. Please check Inbox/Spam.'
        ]);
        exit;
    }

    // issue token (store HASH, send RAW)
    $rawToken  = bin2hex(random_bytes(32));   // 64 hex chars to the user
    $tokenHash = hash('sha256', $rawToken);   // store only the hash

    // Invalidate previous unconsumed tokens for this user
    $pdo->prepare("UPDATE email_verifications SET consumed_at = NOW() WHERE user_id=? AND consumed_at IS NULL")
        ->execute([(int)$u['id']]);

    // Insert with DB-side expiry (avoids PHP/DB timezone mismatch)
    $pdo->prepare("
        INSERT INTO email_verifications (user_id, token, sent_to, expires_at)
        VALUES (?, ?, ?, NOW() + INTERVAL 30 MINUTE)
    ")->execute([(int)$u['id'], $tokenHash, $email]);

    $pdo->commit();

    // send (mailer must build: https://bulaservicesgsc.com/php/verify.php?token=...)
    $send = sendVerificationLink($email, '', $rawToken);
    if (!$send['ok']) {
        error_log('Verification email send failure (tos_accept): '.$email.' => '.($send['error'] ?? 'unknown'));
        echo json_encode(['success'=>false,'message'=>'Could not send verification email. Try again later.']); exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification link sent. Please check your email.'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('tos_accept_api error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error. Please try again.']);
}
