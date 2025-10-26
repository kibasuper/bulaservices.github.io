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

// Toggle to true TEMPORARILY to see raw SQL errors in JSON while debugging
$DEBUG_ERRORS = false;

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

  // ---- Discover columns via SHOW COLUMNS (no INFORMATION_SCHEMA dependency) ----
  $cols = [];           // map: lower(field) => true
  $colsInfo = [];       // map: original field name => info (including Key)
  $anyField = null;     // first column name, as absolute fallback
  $primaryKeys = [];    // list of fields with Key == 'PRI'

  try {
    $cst = $pdo->query("SHOW COLUMNS FROM `users`");
    foreach ($cst as $row) {
      $field = (string)$row['Field'];
      $cols[strtolower($field)] = true;
      $colsInfo[$field] = $row;
      if ($anyField === null) $anyField = $field;
      if (isset($row['Key']) && strtoupper((string)$row['Key']) === 'PRI') {
        $primaryKeys[] = $field;
      }
    }
  } catch (Throwable $e) {
    // continue with empty $cols if SHOW COLUMNS fails
    $cols = [];
    $colsInfo = [];
  }

  $has = fn(string $c) => isset($cols[strtolower($c)]);

  // Prefer primary key for ORDER BY; else id/user_id; else first column; else omit ORDER BY
  $orderCol = null;
  if (!empty($primaryKeys))      $orderCol = $primaryKeys[0];
  elseif ($has('id'))            $orderCol = 'id';
  elseif ($has('user_id'))       $orderCol = 'user_id';
  elseif ($anyField !== null)    $orderCol = $anyField;

  // Common fields
  $first      = $has('first_name') ? 'first_name' : null;
  $last       = $has('last_name')  ? 'last_name'  : null;
  $genderCol  = $has('gender')     ? 'gender'     : null;

  // Residency / account type variants
  $resTypeCol = null;
  foreach (['resident_type','user_type','account_type'] as $c) { if ($has($c)) { $resTypeCol = $c; break; } }

  $picCol     = $has('profile_picture') ? 'profile_picture' : null;
  $emailCol   = $has('email')           ? 'email'           : null;
  $phoneCol   = $has('contact_number')  ? 'contact_number'  : null;
  $addrCol    = $has('address')         ? 'address'         : null;

  // status variants
  $statusCols = [
    'status'          => $has('status'),
    'account_status'  => $has('account_status'),
    'is_active'       => $has('is_active'),
    'is_suspended'    => $has('is_suspended'),
  ];

  // ---- WHERE (only existing columns) ----
  $where = [];
  $args  = [];

  if ($q !== '') {
    $likeParts = [];
    $qp = []; // placeholder => value
    $i = 0;

    if ($first && $last) {
      // CONCAT(full name) LIKE :qX  (single placeholder for the concat)
      $ph = ':q' . $i++;
      $likeParts[] = "(CONCAT(TRIM(COALESCE(`$first`,'')),' ',TRIM(COALESCE(`$last`,''))) LIKE $ph)";
      $qp[$ph] = '%'.$q.'%';
    } elseif ($first) {
      $ph = ':q' . $i++;
      $likeParts[] = "TRIM(COALESCE(`$first`,'')) LIKE $ph";
      $qp[$ph] = '%'.$q.'%';
    } elseif ($last) {
      $ph = ':q' . $i++;
      $likeParts[] = "TRIM(COALESCE(`$last`,'')) LIKE $ph";
      $qp[$ph] = '%'.$q.'%';
    }

    if ($emailCol) {
      $ph = ':q' . $i++;
      $likeParts[] = "`$emailCol` LIKE $ph";
      $qp[$ph] = '%'.$q.'%';
    }
    if ($phoneCol) {
      $ph = ':q' . $i++;
      $likeParts[] = "`$phoneCol` LIKE $ph";
      $qp[$ph] = '%'.$q.'%';
    }
    if ($addrCol) {
      $ph = ':q' . $i++;
      $likeParts[] = "`$addrCol` LIKE $ph";
      $qp[$ph] = '%'.$q.'%';
    }

    if ($likeParts) {
      $where[] = '(' . implode(' OR ', $likeParts) . ')';
      // merge unique placeholders into $args
      foreach ($qp as $k => $v) { $args[$k] = $v; }
    } else {
      // No searchable columns exist -> force empty result to avoid SQL errors
      $where[] = '0=1';
    }
  }

  if ($gender !== 'all' && $genderCol) {
    $where[] = "LOWER(COALESCE(`$genderCol`,'')) = :g";
    $args[':g'] = $gender;
  }

  if ($residency !== 'all' && $resTypeCol) {
    $where[] = "LOWER(COALESCE(`$resTypeCol`,'resident')) = :r";
    $args[':r'] = $residency;
  }

  if ($status !== 'all') {
    $activeClauses = [];
    $suspendedClauses = [];

    if ($statusCols['status'])          { $activeClauses[]    = "LOWER(COALESCE(`status`,'')) IN ('','active')";          $suspendedClauses[] = "LOWER(COALESCE(`status`,''))='suspended'"; }
    if ($statusCols['account_status'])  { $activeClauses[]    = "LOWER(COALESCE(`account_status`,'')) IN ('','active')";  $suspendedClauses[] = "LOWER(COALESCE(`account_status`,''))='suspended'"; }
    if ($statusCols['is_active'])       { $activeClauses[]    = "COALESCE(`is_active`,1)=1";                              $suspendedClauses[] = "COALESCE(`is_active`,1)=0"; }
    if ($statusCols['is_suspended'])    { $activeClauses[]    = "COALESCE(`is_suspended`,0)=0";                           $suspendedClauses[] = "COALESCE(`is_suspended`,0)=1"; }

    if ($status === 'active' && $activeClauses) {
      $where[] = '(' . implode(' AND ', $activeClauses) . ')';
    } elseif ($status === 'suspended') {
      if ($suspendedClauses) {
        $where[] = '(' . implode(' OR ', $suspendedClauses) . ')';
      } else {
        $where[] = '0=1';
      }
    }
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // ---- COUNT ----
  $countSql = "SELECT COUNT(*) FROM `users` $whereSql";
  $st = $pdo->prepare($countSql);
  $st->execute($args);
  $total = (int)$st->fetchColumn();

  // ---- SELECT (only needed fields) ----
  $select = [];

  // Expose a stable id field
  if ($orderCol !== null) {
    $select[] = "`$orderCol` AS id";
  } else {
    $select[] = "0 AS id";
  }

  if ($first) $select[] = "TRIM(COALESCE(`$first`,'')) AS first_name";
  if ($last)  $select[] = "TRIM(COALESCE(`$last`,''))  AS last_name";

  if ($first && $last) {
    $select[] = "CONCAT(TRIM(COALESCE(`$first`,'')),' ',TRIM(COALESCE(`$last`,''))) AS full_name";
  } elseif ($first) {
    $select[] = "TRIM(COALESCE(`$first`,'')) AS full_name";
  } elseif ($last) {
    $select[] = "TRIM(COALESCE(`$last`,''))  AS full_name";
  } else {
    $select[] = "'' AS full_name";
  }

  if ($genderCol)  $select[] = "`$genderCol` AS gender";
  if ($resTypeCol) $select[] = "`$resTypeCol` AS resident_type";
  if ($picCol)     $select[] = "`$picCol` AS profile_picture";
  foreach ($statusCols as $c => $present) {
    if ($present) $select[] = "`$c`";
  }

  $orderSql = $orderCol !== null ? "ORDER BY `$orderCol` DESC" : "";

  $sql = "SELECT " . implode(', ', $select) . "
          FROM `users`
          $whereSql
          $orderSql
          LIMIT $perPage OFFSET $offset";

  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Normalize rows for frontend
foreach ($rows as &$r) {
    // picture URL: build via admin_upload_url()
    $raw = $picCol ? ($r['profile_picture'] ?? null) : null;
    if ($raw) {
        $rel = ltrim((string)$raw, '/');
        if (strpos($rel, 'uploads/profile_pictures/') === 0) {
            $rel = substr($rel, strlen('uploads/')); // -> "profile_pictures/xxx.jpg"
        } elseif (strpos($rel, 'profile_pictures/') !== 0) {
            $rel = 'profile_pictures/' . basename($rel);
        }
        $r['profile_picture_url'] = admin_upload_url($rel); // <-- IMPORTANT
    } else {
        $r['profile_picture_url'] = null;
    }

    // normalize account_status
    $r['account_status'] = normalizeStatus($r, $statusCols);

    // ensure keys
    if (!array_key_exists('resident_type',$r)) $r['resident_type'] = null;
    if (!array_key_exists('gender',$r))        $r['gender'] = null;
}

  echo json_encode([
    'success' => true,
    'items'   => $rows,
    'meta'    => [
      'total'   => $total,
      'page'    => $page,
      'perPage' => $perPage,
      'hasPrev' => $page > 1,
      'hasNext' => ($offset + $perPage) < $total
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('residents_list: '.$e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success'=>false,
    'message'=>$DEBUG_ERRORS ? ('Server error: '.$e->getMessage()) : 'Server error'
  ]);
}

// ---- helpers ----
function normalizeStatus(array $r, array $present): string {
  $s = 'active';
  if (!empty($present['status'])         && isset($r['status'])         && strtolower((string)$r['status']) === 'suspended') $s = 'suspended';
  if (!empty($present['account_status']) && isset($r['account_status']) && strtolower((string)$r['account_status']) === 'suspended') $s = 'suspended';
  if (!empty($present['is_active'])      && isset($r['is_active'])      && (int)$r['is_active'] === 0) $s = 'suspended';
  if (!empty($present['is_suspended'])   && isset($r['is_suspended'])   && (int)$r['is_suspended'] === 1) $s = 'suspended';
  return $s;
}
