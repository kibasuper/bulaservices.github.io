<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';

$raw = $_GET['token'] ?? '';

if (!$raw || !preg_match('/^[a-f0-9]{64}$/', $raw)) {
    error_log('verify.php: missing or malformed token');
    redirect_root('index.php?verify=invalid');
}

$tokenHash = hash('sha256', $raw);

try {
    $pdo = getDBConnection();

    // 1) Find active, unconsumed token
    $stmt = $pdo->prepare("
        SELECT id, user_id, expires_at, consumed_at
        FROM email_verifications
        WHERE token = ?
          AND consumed_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $ev = $stmt->fetch();

    if (!$ev) {
        // Add quick hints to logs to help you see common causes
        $dbg = $pdo->prepare("SELECT id, consumed_at, expires_at, created_at FROM email_verifications WHERE token = ? LIMIT 1");
        $dbg->execute([$tokenHash]);
        $maybe = $dbg->fetch();
        if ($maybe) {
            if (!empty($maybe['consumed_at'])) {
                error_log('verify.php: token exists but already consumed id='.(int)$maybe['id']);
            } else {
                error_log('verify.php: token exists but expired id='.(int)$maybe['id'].' exp='.$maybe['expires_at']);
            }
        } else {
            error_log('verify.php: token not found in DB');
        }
        redirect_root('index.php?verify=invalid');
    }

    // 2) Consume & verify user
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?")
        ->execute([(int)$ev['id']]);
    $pdo->prepare("UPDATE users SET email_verified = 1, verified_at = NOW(), status = 'active' WHERE id = ?")
        ->execute([(int)$ev['user_id']]);
    $pdo->commit();

    redirect_root('index.php?verify=ok');
} catch (Throwable $e) {
    error_log('verify.php error: '.$e->getMessage());
    redirect_root('index.php?verify=invalid');
}
