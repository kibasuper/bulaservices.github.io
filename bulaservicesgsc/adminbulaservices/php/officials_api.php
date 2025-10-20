<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ---------- Access control ---------- */
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

/* ---------- DB ---------- */
$db = getDBConnection();

/* ---------- helpers ---------- */
function ok($data) { echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }
function err($msg, int $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

function send_text_mail(string $to, string $subject, string $body): bool {
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
  $headers .= "From: Barangay Bula <no-reply@bulaservicesgsc.com>\r\n";
  $headers .= "Reply-To: Barangay Bula <no-reply@bulaservicesgsc.com>\r\n";
  return @mail($to, $subject, $body);
}

/* ---------- router ---------- */
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);

switch ($action) {

  /* ===== LIST ===== */
  case 'list': {
    $sql = "SELECT a.admin_id, a.username, a.email, a.first_name, a.last_name, a.role, a.is_active, 
                   a.last_login, a.created_at, p.position
            FROM admins a
            LEFT JOIN officials_profile p ON p.admin_id = a.admin_id
            ORDER BY a.role DESC, a.first_name, a.last_name";
    $rows = $db->query($sql)->fetchAll();
    ok(['items' => $rows]);
  }

  /* ===== CREATE ===== */
  case 'create': {
    $username   = trim((string)($_POST['username'] ?? ''));
    $email      = trim((string)($_POST['email'] ?? ''));
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name  = trim((string)($_POST['last_name'] ?? ''));
    $role       = trim((string)($_POST['role'] ?? 'kagawad'));
    $position   = trim((string)($_POST['position'] ?? ''));

    if ($username === '' || $email === '' || $first_name === '' || $last_name === '' || $position === '') {
      err('Missing required fields', 422);
    }

    // Optional profile fields
    $age            = isset($_POST['age']) ? (int)$_POST['age'] : null;
    $sex            = $_POST['sex']        ?? null;
    $religion       = $_POST['religion']   ?? null;
    $address        = $_POST['address']    ?? null;
    $contact_number = $_POST['contact_number'] ?? null;
    $term_start     = $_POST['term_start'] ?? null;
    $term_end       = $_POST['term_end']   ?? null;

    // Ensure unique username/email
    $chk = $db->prepare("SELECT 1 FROM admins WHERE username=:u OR email=:e LIMIT 1");
    $chk->execute([':u' => $username, ':e' => $email]);
    if ($chk->fetchColumn()) err('Username or email already exists', 409);

    // Default password and must_change_password flag
    $defaultPassword = 'Bula@2025';
    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $mustChange = ($role === 'superadmin' || strtolower($position) === 'punong barangay') ? 0 : 1;

    // Insert into admins
    $ins = $db->prepare("
      INSERT INTO admins (
        username, password_hash, email, first_name, last_name, role,
        contact_number, is_active, must_change_password, created_at
      )
      VALUES (
        :u, :h, :e, :f, :l, :r,
        :c, 1, :m, NOW()
      )
    ");
    $ins->execute([
      ':u' => $username,
      ':h' => $hash,
      ':e' => $email,
      ':f' => $first_name,
      ':l' => $last_name,
      ':r' => $role,
      ':c' => $contact_number,
      ':m' => $mustChange
    ]);

    $admin_id = (int)$db->lastInsertId();

    // Optional photo upload
    $photo_url = null;
    if (!empty($_FILES['photo']) && is_array($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      try { $photo_url = uploadProfilePicture($_FILES['photo']); }
      catch (Throwable $e) { error_log('Photo upload error: '.$e->getMessage()); }
    }

    // Insert into officials_profile
    $p = $db->prepare("
      INSERT INTO officials_profile
      (admin_id, position, term_start, term_end, age, sex, religion, address, contact_number, photo_url, created_at, updated_at)
      VALUES
      (:id,:pos,:ts,:te,:age,:sex,:rel,:addr,:phone,:photo,NOW(),NOW())
    ");
    $p->execute([
      ':id'    => $admin_id,
      ':pos'   => $position,
      ':ts'    => $term_start,
      ':te'    => $term_end,
      ':age'   => $age ?: null,
      ':sex'   => $sex ?: null,
      ':rel'   => $religion ?: null,
      ':addr'  => $address ?: null,
      ':phone' => $contact_number ?: null,
      ':photo' => $photo_url
    ]);

    // Email account details
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $loginUrl = $scheme . '://' . $host . dirname($_SERVER['SCRIPT_NAME']) . '/../index.php';
    $loginUrl = preg_replace('#/+#','/',$loginUrl);

    $subject = "Your Barangay Bula Admin Account";
    $bodyTxt = "Hello {$first_name} {$last_name},\n\n".
               "Your admin account has been created.\n\n".
               "Login URL: {$loginUrl}\n".
               "Username: {$username}\n".
               "Email: {$email}\n".
               "Default Password: {$defaultPassword}\n\n".
               "For security, you are required to change your password upon first login.\n\n".
               "Regards,\nBarangay Bula";
    @send_text_mail($email, $subject, $bodyTxt);

    ok([
      'id'               => $admin_id,
      'username'         => $username,
      'email'            => $email,
      'default_password' => $defaultPassword,
      'photo_url'        => $photo_url
    ]);
  }

  /* ===== GET ONE ===== */
  case 'get_official': {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) err('Invalid ID', 422);

    $a = $db->prepare("SELECT admin_id, username, email, first_name, last_name, role, is_active, last_login, created_at
                       FROM admins WHERE admin_id=:id");
    $a->execute([':id' => $id]);
    $admin = $a->fetch();
    if (!$admin) err('Official not found', 404);

    $p = $db->prepare("SELECT position, term_start, term_end, age, sex, religion, address, contact_number, photo_url, created_at, updated_at
                       FROM officials_profile WHERE admin_id=:id");
    $p->execute([':id' => $id]);
    $profile = $p->fetch() ?: [];

    $adminOut = [
      'id'          => (int)$admin['admin_id'],
      'username'    => $admin['username'],
      'email'       => $admin['email'],
      'first_name'  => $admin['first_name'],
      'last_name'   => $admin['last_name'],
      'role'        => $admin['role'],
      'status'      => ((int)$admin['is_active'] === 1 ? 'active' : 'suspended'),
      'last_login'  => $admin['last_login'],
      'created_at'  => $admin['created_at']
    ];

    ok(['admin' => $adminOut, 'profile' => $profile]);
  }

  /* ===== TOGGLE ACTIVE/SUSPENDED ===== */
  case 'toggle': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) err('Invalid ID', 422);
    $db->exec("UPDATE admins SET is_active = 1 - is_active WHERE admin_id = {$id}");
    ok(['message' => 'toggled']);
  }

  /* ===== RESET PASSWORD ===== */
  case 'reset': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) err('Invalid ID', 422);

    $q = $db->prepare("SELECT email, username, first_name, last_name FROM admins WHERE admin_id=:id");
    $q->execute([':id' => $id]);
    $u = $q->fetch();
    if (!$u) err('Official not found', 404);

    $temp = 'Bula@2025';
    $hash = password_hash($temp, PASSWORD_DEFAULT);
    $up = $db->prepare("UPDATE admins SET password_hash=:h, must_change_password=1 WHERE admin_id=:id");
    $up->execute([':h' => $hash, ':id' => $id]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $loginUrl = $scheme . '://' . $host . dirname($_SERVER['SCRIPT_NAME']) . '/../index.php';
    $loginUrl = preg_replace('#/+#','/',$loginUrl);

    $subject = "Barangay Bula Password Reset";
    $bodyTxt = "Hello {$u['first_name']} {$u['last_name']},\n\n".
               "Your admin password has been reset.\n\n".
               "Login URL: {$loginUrl}\n".
               "Username: {$u['username']}\n".
               "Temporary Password: {$temp}\n\n".
               "You will be required to change your password upon next login.\n\n".
               "Regards,\nBarangay Bula";
    @send_text_mail($u['email'], $subject, $bodyTxt);

    ok(['message' => 'reset emailed']);
  }

  /* ===== UPDATE EMAIL ===== */
  case 'update_email': {
    $id    = (int)($_POST['id'] ?? 0);
    $email = trim((string)($_POST['email'] ?? ''));
    if ($id <= 0 || $email === '') err('Invalid input', 422);

    $chk = $db->prepare("SELECT 1 FROM admins WHERE email=:e AND admin_id<>:id");
    $chk->execute([':e' => $email, ':id' => $id]);
    if ($chk->fetchColumn()) err('Email already in use', 409);

    $u = $db->prepare("UPDATE admins SET email=:e WHERE admin_id=:id");
    $u->execute([':e' => $email, ':id' => $id]);

    ok(['message' => 'email updated']);
  }

  /* ===== PHOTO ===== */
  case 'update_photo': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) err('Invalid ID', 422);
    if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      err('No file uploaded', 422);
    }

    try { $newUrl = uploadProfilePicture($_FILES['photo']); }
    catch (Throwable $e) { err('Upload failed: ' . $e->getMessage(), 500); }

    $old = $db->prepare("SELECT photo_url FROM officials_profile WHERE admin_id=:id");
    $old->execute([':id' => $id]);
    $prev = $old->fetchColumn();
    if ($prev && $prev !== $newUrl) {
      $fsPath = $_SERVER['DOCUMENT_ROOT'] . $prev;
      if (is_file($fsPath)) @unlink($fsPath);
    }

    $up = $db->prepare("UPDATE officials_profile SET photo_url=:u, updated_at=NOW() WHERE admin_id=:id");
    $up->execute([':u' => $newUrl, ':id' => $id]);

    ok(['photo_url' => $newUrl]);
  }

  case 'remove_photo': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) err('Invalid ID', 422);

    $q = $db->prepare("SELECT photo_url FROM officials_profile WHERE admin_id=:id");
    $q->execute([':id' => $id]);
    $prev = $q->fetchColumn();

    if ($prev) {
      $fsPath = $_SERVER['DOCUMENT_ROOT'] . $prev;
      if (is_file($fsPath)) @unlink($fsPath);
    }

    $up = $db->prepare("UPDATE officials_profile SET photo_url=NULL, updated_at=NOW() WHERE admin_id=:id");
    $up->execute([':id' => $id]);

    ok(['message' => 'photo removed']);
  }

  default:
    err('Unknown action', 400);
}
