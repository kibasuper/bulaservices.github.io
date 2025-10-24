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
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Forbidden']);
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

/** Schema helpers */
function profile_has_birthdate(PDO $db): bool {
  static $cached = null;
  if ($cached !== null) return $cached;
  try {
    $stmt = $db->query("SHOW COLUMNS FROM officials_profile LIKE 'birthdate'");
    $cached = ($stmt && $stmt->rowCount() > 0);
  } catch (Throwable $e) { $cached = false; }
  return $cached;
}
function profile_position_nullable(PDO $db): bool {
  static $cached = null;
  if ($cached !== null) return $cached;
  try {
    $stmt = $db->query("SHOW COLUMNS FROM officials_profile LIKE 'position'");
    $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $cached = $col ? (strtoupper((string)$col['Null']) === 'YES') : true; // assume nullable if undetermined
  } catch (Throwable $e) { $cached = true; }
  return $cached;
}

/** Compute age (years) from YYYY-MM-DD */
function compute_age(?string $ymd): ?int {
  if (!$ymd) return null;
  [$y, $m, $d] = array_pad(explode('-', $ymd), 3, null);
  if (!$y || !$m || !$d) return null;
  $y=(int)$y; $m=(int)$m; $d=(int)$d;
  $tz = new DateTimeZone('Asia/Manila');
  $today = new DateTimeImmutable('today', $tz);
  $bday = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $y, $m, $d), $tz);
  if (!$bday) return null;
  if ($bday > $today) return null;
  $age = (int)$today->format('Y') - $y;
  $anniv = $bday->setDate((int)$today->format('Y'), $m, $d);
  if ($today < $anniv) $age--;
  return ($age >= 0) ? $age : null;
}

/* ---------- router ---------- */
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);

switch ($action) {

  /* ===== LIST ===== */
  case 'list': {
    $sql = "SELECT a.admin_id, a.username, a.email, a.first_name, a.last_name, a.role, a.is_active, 
                   a.last_login, a.created_at
            FROM admins a
            ORDER BY a.role DESC, a.first_name, a.last_name";
    $rows = $db->query($sql)->fetchAll();
    ok(['items' => $rows]);
  }

  /* ===== CREATE ===== */
  case 'create': {
    // account
    $username   = trim((string)($_POST['username'] ?? ''));
    $role       = strtolower(trim((string)($_POST['role'] ?? 'staff')));

    // basic
    $email      = trim((string)($_POST['email'] ?? '')); // optional
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name  = trim((string)($_POST['last_name'] ?? ''));
    $birthdate  = trim((string)($_POST['birthdate'] ?? ''));
    $age_in     = isset($_POST['age']) ? (int)$_POST['age'] : null; // client-computed; recompute anyway
    $sex        = $_POST['sex']        ?? null;
    $religion   = $_POST['religion']   ?? null;
    $religion_other = $_POST['religion_other'] ?? null;
    $address    = $_POST['address']    ?? null;

    // phone sanitize & validate (optional but strict if provided)
    $contact_number_raw = (string)($_POST['contact_number'] ?? '');
    $contact_number = preg_replace('/\D+/', '', $contact_number_raw);
    if ($contact_number_raw !== '' && !preg_match('/^0\d{10}$/', $contact_number)) {
      err('Contact number must be exactly 11 digits and start with 0 (e.g., 09XXXXXXXXX).', 422);
    }

    if ($username === '' || $first_name === '' || $last_name === '') {
      err('Missing required fields', 422);
    }

    // normalize role
    if (!in_array($role, ['admin','staff'], true)) $role = 'staff';

    // constrain sex
    if (!in_array($sex, ['Male','Female',''], true)) $sex = null;

    // resolve religion (Other -> free text)
    if ($religion === 'Other') $religion = ($religion_other !== '' ? $religion_other : 'Other');

    // validate birthdate & compute age server-side
    $age = compute_age($birthdate);
    if ($birthdate !== '' && $age === null) {
      err('Invalid birthdate', 422);
    }

    // Ensure unique username
    $chk = $db->prepare("SELECT 1 FROM admins WHERE username=:u LIMIT 1");
    $chk->execute([':u' => $username]);
    if ($chk->fetchColumn()) err('Username already exists', 409);

    // Ensure unique email only if provided
    if ($email !== '') {
      $chk2 = $db->prepare("SELECT 1 FROM admins WHERE email=:e LIMIT 1");
      $chk2->execute([':e' => $email]);
      if ($chk2->fetchColumn()) err('Email already exists', 409);
    }

    // Default password and must_change_password flag
    $defaultPassword = 'Bula@2025';
    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $mustChange = 1; // require password change on first login

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
      ':e' => ($email !== '' ? $email : null),
      ':f' => $first_name,
      ':l' => $last_name,
      ':r' => $role,
      ':c' => ($contact_number !== '' ? $contact_number : null),
      ':m' => $mustChange
    ]);

    $admin_id = (int)$db->lastInsertId();

    // Optional photo upload
    $photo_url = null;
    if (!empty($_FILES['photo']) && is_array($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      try { $photo_url = uploadProfilePicture($_FILES['photo']); }
      catch (Throwable $e) { error_log('Photo upload error: '.$e->getMessage()); }
    }

    // Insert into officials_profile (position removed from UI; insert NULL/'' safely)
    $hasBirthdate = profile_has_birthdate($db);
    $posNullable  = profile_position_nullable($db);
    $positionVal  = $posNullable ? null : ''; // safest default for NOT NULL schema

    if ($hasBirthdate) {
      $p = $db->prepare("
        INSERT INTO officials_profile
        (admin_id, position, birthdate, age, sex, religion, address, contact_number, photo_url, created_at, updated_at)
        VALUES
        (:id,:pos,:bdate,:age,:sex,:rel,:addr,:phone,:photo,NOW(),NOW())
      ");
      $p->execute([
        ':id'    => $admin_id,
        ':pos'   => $positionVal,
        ':bdate' => ($birthdate !== '' ? $birthdate : null),
        ':age'   => $age,
        ':sex'   => $sex ?: null,
        ':rel'   => $religion ?: null,
        ':addr'  => $address ?: null,
        ':phone' => ($contact_number !== '' ? $contact_number : null),
        ':photo' => $photo_url
      ]);
    } else {
      $p = $db->prepare("
        INSERT INTO officials_profile
        (admin_id, position, age, sex, religion, address, contact_number, photo_url, created_at, updated_at)
        VALUES
        (:id,:pos,:age,:sex,:rel,:addr,:phone,:photo,NOW(),NOW())
      ");
      $p->execute([
        ':id'    => $admin_id,
        ':pos'   => $positionVal,
        ':age'   => $age,
        ':sex'   => $sex ?: null,
        ':rel'   => $religion ?: null,
        ':addr'  => $address ?: null,
        ':phone' => ($contact_number !== '' ? $contact_number : null),
        ':photo' => $photo_url
      ]);
    }

    // Email account details (send only if email provided)
    if ($email !== '') {
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
    }

    ok([
      'id'               => $admin_id,
      'username'         => $username,
      'email'            => ($email !== '' ? $email : null),
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

    $hasBirthdate = profile_has_birthdate($db);
    if ($hasBirthdate) {
      $p = $db->prepare("SELECT birthdate, age, sex, religion, address, contact_number, photo_url, created_at, updated_at
                         FROM officials_profile WHERE admin_id=:id");
    } else {
      $p = $db->prepare("SELECT age, sex, religion, address, contact_number, photo_url, created_at, updated_at
                         FROM officials_profile WHERE admin_id=:id");
    }
    $p->execute([':id' => $id]);
    $profile = $p->fetch() ?: [];
    if ($hasBirthdate && !array_key_exists('birthdate',$profile)) { $profile['birthdate'] = null; }

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

    // prevent self-deactivation (optional safety)
    if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
      err('You cannot change your own activation state.', 403);
    }

    $db->exec("UPDATE admins SET is_active = 1 - is_active WHERE admin_id = {$id}");
    ok(['message' => 'toggled']);
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
