<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';      // includes auth + DB
require_once __DIR__ . '/../server/file_urls.php';   // user_upload_url()

header('Content-Type: application/json; charset=utf-8');

/* ---------- Ensure JSON-safe error output for this API ---------- */
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error','detail'=>$e->getMessage()]);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error','detail'=>"$message in $file on line $line"]);
    exit;
});

function out(int $code, array $data){ http_response_code($code); echo json_encode($data); exit; }

ensureUserAccess();
$userId = getCurrentUserId();
if (!$userId) out(403, ['ok'=>false,'error'=>'Unauthorized']);

function fetch_user(PDO $db, int $id): ?array {
    $sql = "SELECT id, user_type, first_name, middle_name, last_name, suffix,
                   birth_place, birth_date, age, civil_status, gender, purok,
                   year_started_staying, contact_number, occupation, address,
                   email, username, profile_picture, is_active, email_verified,
                   status, last_login, created_at, updated_at
              FROM users WHERE id=:id LIMIT 1";
    $st = $db->prepare($sql);
    $st->execute([':id'=>$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

function safe_delete_upload(string $storedPath): bool {
    if (!$storedPath) return false;
    $abs = realpath(PROJECT_ROOT.'/'.ltrim($storedPath,'/'));
    $uploadsRoot = realpath(UPLOADS_DIR);
    if (!$abs || !$uploadsRoot || strpos($abs, $uploadsRoot) !== 0) return false;
    return is_file($abs) ? @unlink($abs) : false;
}

try { $db = getDBConnection(); } catch(Throwable $e){ out(500, ['ok'=>false,'error'=>'DB connection failed']); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* -------- me (GET) -------- */
if ($method==='GET' && $action==='me') {
    $u = fetch_user($db, (int)$userId);
    if (!$u) out(404, ['ok'=>false,'error'=>'User not found']);

    // live age
    if (!empty($u['birth_date'])) {
        try {
            $bd = new DateTime($u['birth_date'], new DateTimeZone('Asia/Manila'));
            $u['computed_age'] = (new DateTime('now', new DateTimeZone('Asia/Manila')))->diff($bd)->y;
        } catch(Throwable $e) { $u['computed_age'] = null; }
    } else { $u['computed_age'] = null; }

    // serve profile picture through gate
    $u['profile_picture_url'] = user_upload_url($u['profile_picture'] ?? null);

    out(200, ['ok'=>true,'data'=>$u,'csrf'=>generateCsrfToken()]);
}

/* -------- POST: CSRF check -------- */
if ($method==='POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) out(400, ['ok'=>false,'error'=>'Invalid CSRF token']);
}

/* -------- update (POST) | requires confirm_password -------- */
if ($method==='POST' && $action==='update') {
    $confirmPw = (string)($_POST['confirm_password'] ?? '');
    if ($confirmPw === '') out(422, ['ok'=>false,'error'=>'Please enter your password to save changes']);

    // Verify current password
    $stPw = $db->prepare("SELECT password FROM users WHERE id=:id LIMIT 1");
    $stPw->execute([':id'=>$userId]);
    $rowPw = $stPw->fetch(PDO::FETCH_ASSOC);
    if (!$rowPw || !password_verify($confirmPw, (string)$rowPw['password'])) {
        out(403, ['ok'=>false,'error'=>'Incorrect password']);
    }

    // Fetch current to allow safe merge
    $current = fetch_user($db, (int)$userId);
    if (!$current) out(404, ['ok'=>false,'error'=>'User not found']);

    // Editable whitelist + type
    // NOTE: first_name, last_name, contact_number, address are NOT NULL in schema
    $fields = [
        'first_name'            => 'str_required',
        'middle_name'           => 'str_null',
        'last_name'             => 'str_required',
        'suffix'                => 'str_null',
        'birth_place'           => 'str_null',
        'birth_date'            => 'date_null',
        'civil_status'          => 'enum_civil',
        'gender'                => 'enum_gender',
        'purok'                 => 'int_purok',          // <- CHANGED: bounded int 1..25
        'year_started_staying'  => 'int_null',
        'contact_number'        => 'str_required_phone', // 09 + 9 digits
        'occupation'            => 'str_null',
        'address'               => 'str_required',
        // email/username/password not editable here
    ];

    $patch = [];
    foreach ($fields as $key => $type) {
        if (!array_key_exists($key, $_POST)) continue; // not provided → leave unchanged
        $raw = $_POST[$key];

        // Empty string means "no change" to prevent accidental wipes
        if ($raw === '') continue;

        switch ($type) {
            case 'str_required':
                $v = sanitizeInput($raw);
                if ($v === '') out(422, ['ok'=>false,'error'=>"$key cannot be empty"]);
                $patch[$key] = $v;
                break;

            case 'str_required_phone':
                $v = sanitizeInput($raw);
                if ($v === '') out(422, ['ok'=>false,'error'=>'contact_number cannot be empty']);
                if (!preg_match('/^09\d{9}$/', $v)) out(422, ['ok'=>false,'error'=>'Invalid contact number. Use 09XXXXXXXXX']);
                $patch[$key] = $v;
                break;

            case 'str_null':
                $v = sanitizeInput($raw);
                $patch[$key] = ($v === '') ? null : $v;
                break;

            case 'int_null':
                $v = trim((string)$raw);
                if ($v !== '') {
                    $iv = (int)preg_replace('/\D+/', '', $v);
                    $yearNow = (int)date('Y');
                    if ($iv < 1900 || $iv > $yearNow) out(422, ['ok'=>false,'error'=>'Invalid year started staying']);
                    $patch[$key] = $iv;
                }
                break;

            case 'int_purok':
                $v = trim((string)$raw);
                if ($v !== '') {
                    $iv = (int)preg_replace('/\D+/', '', $v);
                    if ($iv < 1 || $iv > 25) out(422, ['ok'=>false,'error'=>'Purok must be between 1 and 25']);
                    $patch[$key] = $iv;
                }
                break;

            case 'date_null':
                $v = trim((string)$raw);
                if ($v !== '') {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) out(422, ['ok'=>false,'error'=>'Invalid birth_date (YYYY-MM-DD)']);
                    if (strtotime($v) > time()) out(422, ['ok'=>false,'error'=>'Birth date cannot be in the future']);
                    $patch[$key] = $v;
                }
                break;

            case 'enum_civil':
                $v = strtolower(trim((string)$raw));
                $allowedCivil = ['single','married','widowed','separated'];
                if ($v !== '' && !in_array($v, $allowedCivil, true)) out(422, ['ok'=>false,'error'=>'Invalid civil_status']);
                if ($v !== '') $patch[$key] = $v;
                break;

            case 'enum_gender':
                $v = strtolower(trim((string)$raw));
                $allowedGender = ['male','female'];
                if ($v !== '' && !in_array($v, $allowedGender, true)) out(422, ['ok'=>false,'error'=>'Invalid gender']);
                if ($v !== '') $patch[$key] = $v;
                break;
        }
    }

    if (empty($patch)) out(400, ['ok'=>false,'error'=>'No changes to apply']);

    // Age only if birth_date changed
    $updateAge = array_key_exists('birth_date', $patch);
    $ageVal = null;
    if ($updateAge) {
        if (!empty($patch['birth_date'])) {
            try {
                $bd = new DateTime($patch['birth_date'], new DateTimeZone('Asia/Manila'));
                $ageVal = (new DateTime('now', new DateTimeZone('Asia/Manila')))->diff($bd)->y;
            } catch (Throwable $e) { $ageVal = null; }
        } else {
            $ageVal = null;
        }
    }

    // Build dynamic UPDATE
    $sets = [];
    $params = [':id' => $userId];
    foreach ($patch as $k => $v) {
        $sets[] = "{$k} = :{$k}";
        $params[":{$k}"] = $v;
    }
    if ($updateAge) {
        $sets[] = "age = :age";
        $params[':age'] = $ageVal;
    }
    $sets[] = "updated_at = CURRENT_TIMESTAMP";
    $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id=:id LIMIT 1";

    try {
        $st = $db->prepare($sql);
        $st->execute($params);

        $u = fetch_user($db, (int)$userId);
        if ($u) {
            $u['profile_picture_url'] = user_upload_url($u['profile_picture'] ?? null);
            if (!empty($u['birth_date'])) {
                try {
                    $bd = new DateTime($u['birth_date'], new DateTimeZone('Asia/Manila'));
                    $u['computed_age'] = (new DateTime('now', new DateTimeZone('Asia/Manila')))->diff($bd)->y;
                } catch(Throwable $e) { $u['computed_age'] = null; }
            }
        }
        out(200, ['ok'=>true,'message'=>'Profile updated','data'=>$u]);
    } catch (Throwable $e) {
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $info = $e->errorInfo[1] ?? null;
            if ((int)$info === 1062) out(409, ['ok'=>false,'error'=>'Email or username already exists']);
        }
        out(500, ['ok'=>false,'error'=>'Update failed']);
    }
}

/* -------- upload_pic -------- */
if ($method==='POST' && $action==='upload_pic') {
    if (empty($_FILES['profile_picture'])) out(400, ['ok'=>false,'error'=>'No file uploaded']);

    try {
        $newPath = uploadProfilePicture($_FILES['profile_picture']); // "/uploads/profile_pictures/xxx.jpg"

        $st = $db->prepare("SELECT profile_picture FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$userId]);
        $old = $st->fetchColumn();

        $upd = $db->prepare("UPDATE users SET profile_picture=:p, updated_at=CURRENT_TIMESTAMP WHERE id=:id LIMIT 1");
        $upd->execute([':p'=>$newPath, ':id'=>$userId]);

        if ($old && $old !== $newPath) safe_delete_upload((string)$old);

        out(200, [
            'ok'      => true,
            'message' => 'Profile picture updated',
            'path'    => $newPath,
            'url'     => user_upload_url($newPath)
        ]);
    } catch(Throwable $e) {
        out(500, ['ok'=>false,'error'=>$e->getMessage()]);
    }
}

/* -------- delete_pic -------- */
if ($method==='POST' && $action==='delete_pic') {
    try {
        $st = $db->prepare("SELECT profile_picture FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$userId]);
        $old = $st->fetchColumn();

        $upd = $db->prepare("UPDATE users SET profile_picture=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id LIMIT 1");
        $upd->execute([':id'=>$userId]);

        if ($old) safe_delete_upload((string)$old);

        out(200, ['ok'=>true,'message'=>'Profile picture removed']);
    } catch(Throwable $e) {
        out(500, ['ok'=>false,'error'=>'Failed to remove profile picture']);
    }
}

/* -------- change_password (POST) -------- */
if ($method==='POST' && $action==='change_password') {
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password2'] ?? '');

    if ($old === '' || $new === '' || $new2 === '') {
        out(422, ['ok'=>false,'error'=>'All password fields are required']);
    }
    if ($new !== $new2) {
        out(422, ['ok'=>false,'error'=>'New passwords do not match']);
    }
    if (strlen($new) < 8) {
        out(422, ['ok'=>false,'error'=>'New password must be at least 8 characters']);
    }
    if ($new === $old) {
        out(422, ['ok'=>false,'error'=>'New password must be different from current password']);
    }

    $st = $db->prepare("SELECT password FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($old, (string)$row['password'])) {
        out(403, ['ok'=>false,'error'=>'Incorrect current password']);
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $db->prepare("UPDATE users SET password=:p, updated_at=CURRENT_TIMESTAMP WHERE id=:id LIMIT 1");
    $upd->execute([':p'=>$hash, ':id'=>$userId]);

    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);

    out(200, ['ok'=>true,'message'=>'Password changed successfully']);
}

/* -------- fallthrough -------- */
out(400, ['ok'=>false,'error'=>'Unknown action']);
