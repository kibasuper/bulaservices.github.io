<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
require_once __DIR__ . '/../server/file_urls.php'; 
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Invalid id']);
  exit;
}

try {
  $pdo = getDBConnection();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Detect optional columns
  $hasGender    = colExists($pdo,'users','gender');
  $dobCol = null;
  foreach (['date_of_birth','birth_date','birthdate','dob'] as $c) { if (colExists($pdo,'users',$c)) { $dobCol = $c; break; } }
  $resTypeCol = null;
  foreach (['resident_type','user_type','account_type'] as $c) { if (colExists($pdo,'users',$c)) { $resTypeCol = $c; break; } }
  $hasPic      = colExists($pdo,'users','profile_picture');
  $hasStatus   = colExists($pdo,'users','status');
  $hasAccStat  = colExists($pdo,'users','account_status');
  $hasIsActive = colExists($pdo,'users','is_active');
  $hasSusp     = colExists($pdo,'users','is_suspended');
  $hasEmail    = colExists($pdo,'users','email');
  $hasContact  = colExists($pdo,'users','contact_number');
  $hasAddress  = colExists($pdo,'users','address');
  $hasCreated  = colExists($pdo,'users','created_at');

  $yearCol = null;
  foreach (['year_started_staying','year_started','started_year','year_of_stay'] as $colName) {
    if (colExists($pdo,'users',$colName)) { $yearCol = $colName; break; }
  }

  // Build SELECT
  $select = [
    "id",
    "TRIM(COALESCE(first_name,'')) AS first_name",
    "TRIM(COALESCE(last_name,''))  AS last_name",
    "CONCAT(TRIM(COALESCE(first_name,'')),' ',TRIM(COALESCE(last_name,''))) AS full_name"
  ];
  if ($hasEmail)   $select[] = "email";
  if ($hasContact) $select[] = "contact_number";
  if ($hasAddress) $select[] = "address";
  if ($hasCreated) $select[] = "created_at";
  if ($hasGender)  $select[] = "gender";
  if ($dobCol)     $select[] = "`$dobCol` AS date_of_birth";
  if ($resTypeCol) $select[] = "`$resTypeCol` AS resident_type";
  if ($hasPic)     $select[] = "profile_picture";
  if ($hasStatus)  $select[] = "status";
  if ($hasAccStat) $select[] = "account_status";
  if ($hasIsActive)$select[] = "is_active";
  if ($hasSusp)    $select[] = "is_suspended";
  if ($yearCol)    $select[] = "`$yearCol` AS year_started_staying";

  $sql = "SELECT ".implode(", ", $select)." FROM users WHERE id = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    echo json_encode(['success'=>false,'message'=>'Not found']);
    exit;
  }

  // --- PROFILE PICTURE URL (admin-served) ---
  $row['profile_picture_url'] = null;
  if ($hasPic && !empty($row['profile_picture'])) {
    $raw = (string)$row['profile_picture'];

    // If DB has an absolute URL already, keep it
    if (preg_match('#^https?://#i', $raw)) {
      $row['profile_picture_url'] = $raw;
    } else {
      // Normalize relative variants into "profile_pictures/filename.ext"
      // Accept:
      //   "profile_pictures/xxx.jpg"
      //   "/uploads/profile_pictures/xxx.jpg"
      //   "uploads/profile_pictures/xxx.jpg"
      //   "xxx.jpg" (bare filename)
      $rel = ltrim($raw, '/');
      if (strpos($rel, 'uploads/profile_pictures/') === 0) {
        $rel = substr($rel, strlen('uploads/')); // -> "profile_pictures/xxx.jpg"
      } elseif (strpos($rel, 'profile_pictures/') !== 0) {
        // treat as bare filename
        $rel = 'profile_pictures/' . basename($rel);
      }
      $row['profile_picture_url'] = admin_upload_url($rel); // => /php/serve_upload_admin.php?file=profile_pictures/xxx.jpg
    }
  }

  // normalize account_status
  $row['account_status'] = normalizeStatus($row, $hasStatus, $hasAccStat, $hasIsActive, $hasSusp);

  // Ensure keys exist for frontend
  $row += [
    'date_of_birth'        => $row['date_of_birth']        ?? null,
    'resident_type'        => $row['resident_type']        ?? null,
    'year_started_staying' => $row['year_started_staying'] ?? null,
    'address'              => $row['address']              ?? null,
    'created_at'           => $row['created_at']           ?? null,
    'email'                => $row['email']                ?? null,
  ];

  echo json_encode(['success'=>true,'item'=>$row]);

} catch (Throwable $e) {
  error_log('resident_get: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}

// --- helpers (unchanged) ---
function colExists(PDO $pdo, string $table, string $col): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $q->execute([$table,$col]);
  return (bool)$q->fetchColumn();
}
function normalizeStatus(array $r, bool $hasStatus, bool $hasAccStat, bool $hasIsActive, bool $hasSusp): string {
  $s = 'active';
  if ($hasStatus && isset($r['status']) && strtolower((string)$r['status']) === 'suspended') $s = 'suspended';
  if ($hasAccStat && isset($r['account_status']) && strtolower((string)$r['account_status']) === 'suspended') $s = 'suspended';
  if ($hasIsActive && isset($r['is_active']) && (int)$r['is_active'] === 0) $s = 'suspended';
  if ($hasSusp && isset($r['is_suspended']) && (int)$r['is_suspended'] === 1) $s = 'suspended';
  return $s;
}
