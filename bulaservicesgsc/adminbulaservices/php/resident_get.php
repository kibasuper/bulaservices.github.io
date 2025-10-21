<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }

try {
  $pdo = getDBConnection();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $hasGender   = colExists($pdo,'users','gender');
  $hasDob      = colExists($pdo,'users','date_of_birth');
  $hasResident = colExists($pdo,'users','resident_type');
  $hasPic      = colExists($pdo,'users','profile_picture');
  $hasStatus   = colExists($pdo,'users','status');
  $hasAccStat  = colExists($pdo,'users','account_status');
  $hasIsActive = colExists($pdo,'users','is_active');
  $hasSusp     = colExists($pdo,'users','is_suspended');

  $sql = "SELECT 
            id,
            TRIM(COALESCE(first_name,'')) AS first_name,
            TRIM(COALESCE(last_name,''))  AS last_name,
            CONCAT(TRIM(COALESCE(first_name,'')),' ',TRIM(COALESCE(last_name,''))) AS full_name,
            email, contact_number, address, created_at
            ".($hasGender?   ", gender" : "")."
            ".($hasDob?      ", date_of_birth" : "")."
            ".($hasResident? ", resident_type" : "")."
            ".($hasPic?      ", profile_picture" : "")."
            ".($hasStatus?   ", status" : "")."
            ".($hasAccStat?  ", account_status" : "")."
            ".($hasIsActive? ", is_active" : "")."
            ".($hasSusp?     ", is_suspended" : "")."
          FROM users WHERE id = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found']); exit; }

  $row['profile_picture_url'] = null;
  if ($hasPic && !empty($row['profile_picture'])) {
    $row['profile_picture_url'] = '/uploads/profile_pictures/'.basename((string)$row['profile_picture']);
  }
  $row['account_status'] = normalizeStatus($row, $hasStatus, $hasAccStat, $hasIsActive, $hasSusp);

  echo json_encode(['success'=>true,'item'=>$row]);

} catch (Throwable $e) {
  error_log('resident_get: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}

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
