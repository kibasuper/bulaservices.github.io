<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

try {
  $pdo = getDBConnection();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $q          = trim((string)($_GET['q'] ?? ''));
  $gender     = strtolower(trim((string)($_GET['gender'] ?? 'all')));
  $residency  = strtolower(trim((string)($_GET['residency'] ?? 'all')));
  $status     = strtolower(trim((string)($_GET['status'] ?? 'all')));
  $page       = max(1, (int)($_GET['page'] ?? 1));
  $perPage    = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
  $offset     = ($page-1)*$perPage;

  // Optional columns
  $hasGender   = colExists($pdo,'users','gender');
  $hasDob      = colExists($pdo,'users','date_of_birth');
  $hasResident = colExists($pdo,'users','resident_type');
  $hasPic      = colExists($pdo,'users','profile_picture');
  $hasStatus   = colExists($pdo,'users','status');
  $hasAccStat  = colExists($pdo,'users','account_status');
  $hasIsActive = colExists($pdo,'users','is_active');
  $hasSusp     = colExists($pdo,'users','is_suspended');

  $where = [];
  $args  = [];

  if ($q !== '') {
    $where[] = "(CONCAT(TRIM(COALESCE(first_name,'')),' ',TRIM(COALESCE(last_name,''))) LIKE :q
                 OR email LIKE :q
                 OR contact_number LIKE :q
                 OR address LIKE :q)";
    $args[':q'] = '%'.$q.'%';
  }
  if ($gender !== 'all' && $hasGender) {
    $where[] = "LOWER(COALESCE(gender,'')) = :g";
    $args[':g'] = $gender;
  }
  if ($residency !== 'all' && $hasResident) {
    $where[] = "LOWER(COALESCE(resident_type,'resident')) = :r";
    $args[':r'] = $residency;
  }
  // status filter (Active/Suspended)
  if ($status !== 'all') {
    if ($status === 'active') {
      $clauses = [];
      if ($hasStatus)   $clauses[] = "LOWER(COALESCE(status,'')) IN ('','active')";
      if ($hasAccStat)  $clauses[] = "LOWER(COALESCE(account_status,'')) IN ('','active')";
      if ($hasIsActive) $clauses[] = "COALESCE(is_active,1) = 1";
      if ($hasSusp)     $clauses[] = "COALESCE(is_suspended,0) = 0";
      if ($clauses) $where[] = "(".implode(' AND ', $clauses).")";
    } else if ($status === 'suspended') {
      $clauses = [];
      if ($hasStatus)   $clauses[] = "LOWER(COALESCE(status,'')) = 'suspended'";
      if ($hasAccStat)  $clauses[] = "LOWER(COALESCE(account_status,'')) = 'suspended'";
      if ($hasIsActive) $clauses[] = "COALESCE(is_active,1) = 0";
      if ($hasSusp)     $clauses[] = "COALESCE(is_suspended,0) = 1";
      if ($clauses) $where[] = "(".implode(' OR ', $clauses).")";
      else $where[] = "0=1"; // no way to detect suspended if no columns
    }
  }

  $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

  // Count
  $st = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
  $st->execute($args);
  $total = (int)$st->fetchColumn();

  // Page
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
          FROM users
          $whereSql
          ORDER BY id DESC
          LIMIT $perPage OFFSET $offset";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as &$r) {
    $r['profile_picture_url'] = null;
    if ($hasPic && !empty($r['profile_picture'])) {
      $r['profile_picture_url'] = '/uploads/profile_pictures/'.basename((string)$r['profile_picture']);
    }
    // normalize status => account_status
    $r['account_status'] = normalizeStatus($r, $hasStatus, $hasAccStat, $hasIsActive, $hasSusp);
  }

  echo json_encode([
    'success'=>true,
    'items'=>$rows,
    'meta'=>[
      'total'=>$total,
      'page'=>$page,
      'perPage'=>$perPage,
      'hasPrev'=>$page>1,
      'hasNext'=>($offset+$perPage) < $total
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('residents_list: '.$e->getMessage());
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
