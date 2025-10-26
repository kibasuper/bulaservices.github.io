<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

try {
  $raw = file_get_contents('php://input') ?: '';
  $in = json_decode($raw, true);
  if (!is_array($in)) { throw new InvalidArgumentException('Invalid payload'); }

  $adminId  = (int)$_SESSION['admin_id'];
  $current  = (string)($in['current_password'] ?? '');
  $newPw    = (string)($in['new_password'] ?? '');

  if ($current === '' || $newPw === '') {
    throw new InvalidArgumentException('Current password and new password are required.');
  }
  if (strlen($newPw) < 8) {
    throw new InvalidArgumentException('New password must be at least 8 characters.');
  }

  $db = getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $db->prepare("SELECT password_hash FROM admins WHERE admin_id = ? LIMIT 1");
  $stmt->execute([$adminId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || !password_verify($current, (string)$row['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']);
    exit;
  }

  // If same as current, reject
  if (password_verify($newPw, (string)$row['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'New password must be different from current password.']);
    exit;
  }

  $newHash = password_hash($newPw, PASSWORD_DEFAULT);

  $upd = $db->prepare("
    UPDATE admins
       SET password_hash = :hash,
           must_change_password = 0,
           password_changed_at = NOW()
     WHERE admin_id = :id
     LIMIT 1
  ");
  $upd->execute([':hash'=>$newHash, ':id'=>$adminId]);

  echo json_encode(['success'=>true]);

} catch (InvalidArgumentException $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
  error_log('admin_change_password: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
