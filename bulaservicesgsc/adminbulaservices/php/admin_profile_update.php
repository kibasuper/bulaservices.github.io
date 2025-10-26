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

  $adminId = (int)$_SESSION['admin_id'];
  $first   = trim((string)($in['first_name'] ?? ''));
  $last    = trim((string)($in['last_name'] ?? ''));
  $user    = trim((string)($in['username'] ?? ''));
  $email   = trim((string)($in['email'] ?? ''));
  $contact = trim((string)($in['contact_number'] ?? ''));
  $current = (string)($in['current_password'] ?? '');

  if ($first === '' || $last === '' || $user === '' || $email === '') {
    throw new InvalidArgumentException('First name, last name, username, and email are required.');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Invalid email address.');
  }
  if ($current === '') {
    throw new InvalidArgumentException('Current password is required.');
  }

  $db = getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Fetch current + verify password
  $stmt = $db->prepare("SELECT password_hash FROM admins WHERE admin_id = ? LIMIT 1");
  $stmt->execute([$adminId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || !password_verify($current, (string)$row['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']);
    exit;
  }

  // Uniqueness checks
  $uStmt = $db->prepare("SELECT admin_id FROM admins WHERE username = ? AND admin_id <> ? LIMIT 1");
  $uStmt->execute([$user, $adminId]);
  if ($uStmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>'Username is already taken.']);
    exit;
  }

  $eStmt = $db->prepare("SELECT admin_id FROM admins WHERE email = ? AND admin_id <> ? LIMIT 1");
  $eStmt->execute([$email, $adminId]);
  if ($eStmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>'Email is already taken.']);
    exit;
  }

  // Update
  $upd = $db->prepare("
    UPDATE admins
       SET first_name = :first,
           last_name  = :last,
           username   = :user,
           email      = :email,
           contact_number = :contact
     WHERE admin_id = :id
     LIMIT 1
  ");
  $upd->execute([
    ':first' => $first,
    ':last'  => $last,
    ':user'  => $user,
    ':email' => $email,
    ':contact' => ($contact !== '' ? $contact : null),
    ':id'    => $adminId
  ]);

  echo json_encode(['success'=>true]);

} catch (InvalidArgumentException $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
  error_log('admin_profile_update: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
