<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$DEBUG = isset($_GET['debug']) && $_GET['debug'] === '1';

/** 1) Boot the app (starts the session with the right cookie) */
require_once __DIR__ . '/../server/config.php';
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

/** 2) DB connection */
try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'message'=>'Database connection failed',
        'detail'=>$DEBUG ? $e->getMessage() : null
    ]);
    exit;
}

/** 3) Session identity helpers */
function sess_pick(...$keys) {
    foreach ($keys as $k) {
        if (isset($_SESSION[$k]) && trim((string)$_SESSION[$k]) !== '') return $_SESSION[$k];
    }
    return '';
}
$userId   = (int)($_SESSION['user_id'] ?? 0);
$fullName = trim((string)(
    sess_pick('full_name', 'name') ?:
    (trim((string)($_SESSION['first_name'] ?? '')).' '.trim((string)($_SESSION['last_name'] ?? '')))
));
$contact  = trim((string)sess_pick('contact_number','contact','phone','mobile'));
$email    = trim((string)sess_pick('email','user_email'));

// If identity is completely missing, block
if ($userId <= 0 && $fullName === '' && $contact === '' && $email === '') {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Not logged in']);
  exit;
}

/** 4) Utils */
function norm_name(?string $s): string {
  $s = trim((string)$s);
  $s = preg_replace('/\s+/', ' ', $s);
  return mb_strtolower($s);
}
function norm_phone(?string $s): string { return preg_replace('/\D+/', '', (string)$s); }
function join_name(?string $first, ?string $last): string {
  $first = trim((string)$first); $last = trim((string)$last);
  $full = trim("$first $last");
  return $full !== '' ? $full : trim($first.$last);
}
function toDateStr($v) {
  if ($v === null || $v === '') return null;
  $ts = strtotime((string)$v);
  return $ts ? date('Y-m-d H:i:s', $ts) : (string)$v;
}
function fileTypeFromPath(?string $path): string {
  if (!$path) return 'alt';
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (in_array($ext, ['pdf'])) return 'pdf';
  if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) return 'image';
  return 'alt';
}
function hasColumn(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $st->execute([$table, $col]);
  return (bool)$st->fetchColumn();
}
function buildOrder(PDO $pdo, string $table, array $candidates): string {
  $have = [];
  foreach ($candidates as $c) { if (hasColumn($pdo,$table,$c)) $have[] = $c; }
  if ($have) {
    $exprs = array_map(fn($c) => "`$c`", $have);
    return 'ORDER BY ' . implode(', ', $exprs) . ' DESC';
  }
  return 'ORDER BY `id` DESC';
}

/* -------- lenient identity helpers -------- */
function hydrateIdentityFromUsers(PDO $pdo, array $me): array {
  if (!empty($me['user_id'])) {
    try {
      $st = $pdo->prepare("SELECT first_name, last_name, contact_number, email FROM users WHERE id = ? LIMIT 1");
      $st->execute([$me['user_id']]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $name  = norm_name(join_name($row['first_name'] ?? '', $row['last_name'] ?? ''));
        $phone = norm_phone($row['contact_number'] ?? '');
        $email = mb_strtolower(trim((string)($row['email'] ?? '')));
        if ($me['name']  === '' && $name  !== '') $me['name']  = $name;
        if ($me['phone'] === '' && $phone !== '') $me['phone'] = $phone;
        if ($me['email'] === '' && $email !== '') $me['email'] = $email;
      }
    } catch (Throwable $e) { /* ignore */ }
  }
  return $me;
}
function phonesEqualLoose(string $a, string $b): bool {
  $a = norm_phone($a); $b = norm_phone($b);
  if ($a === '' || $b === '') return false;
  foreach ([11,10,9] as $len) {
    if (strlen($a) >= $len && strlen($b) >= $len) {
      if (substr($a, -$len) === substr($b, -$len)) return true;
    }
  }
  return $a === $b;
}
function namesLikelySame(string $a, string $b): bool {
  $a = norm_name($a); $b = norm_name($b);
  if ($a === '' || $b === '') return false;
  if ($a === $b) return true;
  $ta = preg_split('/\s+/', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $tb = preg_split('/\s+/', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  if (!$ta || !$tb) return false;
  $set = array_flip($tb);
  $common = 0;
  foreach ($ta as $t) if (isset($set[$t])) $common++;
  return $common >= 2;
}

/* -------- reservations (gym) time helpers -------- */
function parseReservationWindow(array $r): array {
  // Derive start/end from reservation_date + time_slots JSON (hours)
  $date = (string)($r['reservation_date'] ?? '');
  $slotsJson = (string)($r['time_slots'] ?? '');
  $tz = new DateTimeZone('Asia/Manila');

  $hours = [];
  if ($slotsJson !== '') {
    $arr = json_decode($slotsJson, true);
    if (is_array($arr)) {
      foreach ($arr as $s) {
        if (is_array($s) && isset($s['hour'])) {
          $hours[] = (int)$s['hour'];
        } elseif (is_string($s) && preg_match('/(\d{1,2}):00/', $s, $m)) {
          $hours[] = (int)$m[1];
        }
      }
    }
  }
  $hours = array_values(array_unique(array_filter($hours, fn($h)=>$h>=0 && $h<=23)));
  sort($hours);

  if ($date === '' || empty($hours)) {
    return [null, null]; // unknown
  }
  $minH = min($hours);
  $maxH = max($hours) + 1; // last hour boundary

  $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:00:00', $date, $minH), $tz);
  $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:00:00', $date, $maxH), $tz);
  return [$start ?: null, $end ?: null];
}

/* -------- gym lifecycle mapping -------- */
function mapReservationLifecycle(array $r): string {
  $tz       = new DateTimeZone('Asia/Manila');
  $now      = new DateTimeImmutable('now', $tz);

  $raw      = strtolower(trim((string)($r['status'] ?? '')));
  $claimed  = toDateStr($r['claimed_at'] ?? null);
  $paid     = toDateStr($r['paid_at'] ?? null);

  // rejected/cancelled wins
  if (in_array($raw, ['rejected','cancelled','canceled','void'], true)) return 'rejected';
  // claimed => completed
  if ($claimed !== null) return 'completed';

  [$start, $end] = parseReservationWindow($r);
  if (!$start || !$end) {
    // Fallback to raw flags if timing unknown
    if ($paid !== null) return 'incoming';
    if (in_array($raw, ['approved','processing','in_progress','for_processing','paid'], true)) return 'approved';
    return 'waiting_approval';
  }

  if ($now < $start) {
    if ($paid !== null) return 'incoming';      // paid but not started
    if (in_array($raw, ['approved','processing','in_progress','for_processing','paid'], true)) return 'approved';
    return 'waiting_approval';
  }

  if ($now >= $start && $now < $end) {
    return 'ongoing';
  }

  // past the scheduled window → completed
  return 'completed';
}

/* -------- certificates lifecycle mapping (NEW) -------- */
function computeServiceStatusSR(array $r): string {
  $raw = strtolower(trim((string)($r['status'] ?? '')));

  $approved = toDateStr($r['approved_date'] ?? null);
  $paid     = toDateStr($r['paid_at']       ?? null);
  $claimed  = toDateStr($r['claimed_at']    ?? null);

  // explicit terminal states
  if (in_array($raw, ['rejected','cancelled','canceled','void'], true)) return 'rejected';
  if ($claimed) return 'completed';

  // paid but not yet claimed => waiting to be released
  if ($paid) return 'paid';

  // admin approved (or raw approved-ish) => approved
  if ($approved || in_array($raw, ['approved','processing','in_progress','for_processing'], true)) {
    return 'approved';
  }

  // default
  return 'waiting_approval';
}

/** 5) Action */
$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
$action = $_GET['action'] ?? ($body['action'] ?? 'list');
if ($action !== 'list') {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Invalid action']);
  exit;
}

/** 6) Normalized identity (for matching) */
$me = [
  'user_id' => $userId,
  'name'    => norm_name($fullName),
  'phone'   => norm_phone($contact),
  'email'   => mb_strtolower($email),
];
// Enrich session identity from users table if any field is missing
if ($me['name'] === '' || $me['phone'] === '' || $me['email'] === '') {
  $me = hydrateIdentityFromUsers($pdo, $me);
}

$items = [];
$debug = ['session'=>$me];

/** 7) SERVICE REQUESTS (certificates) */
try {
  $sr_has_user_id        = hasColumn($pdo,'service_requests','user_id');
  $sr_has_requester_name = hasColumn($pdo,'service_requests','requester_name') || hasColumn($pdo,'service_requests','requestor_name');
  $sr_has_contact_number = hasColumn($pdo,'service_requests','contact_number');

  $srOrder = buildOrder($pdo, 'service_requests', [
    'claimed_at','paid_at','processed_date','approved_date','request_date','created_at'
  ]);

  $joinUsers = $sr_has_user_id ? "LEFT JOIN users u ON u.id = sr.user_id" : "LEFT JOIN users u ON 1=0";
  $sql = "SELECT sr.*, 
                 u.first_name AS u_first_name, 
                 u.last_name  AS u_last_name, 
                 u.contact_number AS u_contact, 
                 u.email AS u_email
          FROM service_requests sr
          $joinUsers
          $srOrder
          LIMIT 500";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $matched = 0;
  foreach ($rows as $r) {
    $match = false;

    // 1) user_id match
    if ($me['user_id'] > 0 && $sr_has_user_id && (int)($r['user_id'] ?? 0) === $me['user_id']) {
      $match = true;
    }

    // 2) users table match (lenient)
    if (!$match) {
      $uName   = norm_name(join_name($r['u_first_name'] ?? '', $r['u_last_name'] ?? ''));
      $uPhone  = norm_phone($r['u_contact'] ?? '');
      $uEmail  = mb_strtolower(trim((string)($r['u_email'] ?? '')));
      if ($uName !== ''  && $me['name']  !== '' && namesLikelySame($uName, $me['name'])) $match = true;
      if (!$match && $uPhone !== '' && $me['phone'] !== '' && phonesEqualLoose($uPhone, $me['phone'])) $match = true;
      if (!$match && $uEmail !== '' && $me['email'] !== '' && $uEmail === $me['email']) $match = true;
    }

    // 3) service_requests row match (lenient)
    if (!$match && $sr_has_requester_name) {
      $reqName = '';
      if (isset($r['requester_name'])) $reqName = norm_name((string)$r['requester_name']);
      if (!$reqName && isset($r['requestor_name'])) $reqName = norm_name((string)$r['requestor_name']);
      if ($reqName !== '' && $me['name'] !== '' && namesLikelySame($reqName, $me['name'])) $match = true;
    }
    if (!$match && $sr_has_contact_number) {
      $reqPhone = norm_phone($r['contact_number'] ?? '');
      if ($reqPhone !== '' && $me['phone'] !== '' && phonesEqualLoose($reqPhone, $me['phone'])) $match = true;
    }

    if (!$match) continue;
    $matched++;

    $rawStatus = strtolower(trim((string)($r['status'] ?? '')));
    $status    = computeServiceStatusSR($r);

    $ref       = (string)($r['reference_number'] ?? $r['id'] ?? '');
    $submitted = toDateStr($r['request_date'] ?? $r['created_at'] ?? null) ?? '—';
    $updated   = toDateStr($r['claimed_at'] ?? $r['paid_at'] ?? $r['processed_date'] ?? $r['approved_date'] ?? $r['request_date'] ?? $r['created_at'] ?? null) ?? $submitted;
    $estimated = toDateStr($r['pickup_date'] ?? null);
    $docPath   = (string)($r['document_path'] ?? '');

    $docs = $docPath !== '' ? [[ 'name' => basename($docPath), 'type' => fileTypeFromPath($docPath) ]] : [];

    $timeline = [];
    if (!empty($r['admin_notes']))     $timeline[] = ['date'=>$updated, 'content'=> (string)$r['admin_notes']];
    if (!empty($r['rejected_reason'])) $timeline[] = ['date'=>$updated, 'content'=> 'Rejected: '.(string)$r['rejected_reason']];

    $stype = (string)($r['service_type'] ?? 'Service Request');
    // normalize some common names
    $pretty = [
      'barangay_clearance' => 'Barangay Clearance',
      'indigency'          => 'Certificate of Indigency',
      'residency'          => 'Certificate of Residency',
      'business_permit'    => 'Business Permit',
      'gym'                => 'Gym Reservation',
    ];
    $type  = $pretty[strtolower($stype)] ?? ucwords(str_replace(['_','-'],' ', $stype));

    $items[] = [
      'id'        => (int)$r['id'],
      'type'      => $type ?: 'Service Request',
      'status'    => $status,      // waiting_approval | approved | paid | completed | rejected
      'raw_status'=> $rawStatus,   // for debugging / validation
      'submitted' => $submitted,
      'updated'   => $updated,
      'officer'   => 'Barangay Staff',
      'reference' => $ref ?: (string)($r['id'] ?? ''),
      'estimated' => $estimated,
      'documents' => $docs,
      'timeline'  => $timeline,
    ];
  }

  if ($DEBUG) {
    $debug['service_requests'] = [
      'order_by' => $srOrder,
      'read'     => count($rows),
      'matched'  => $matched,
      'have_cols'=> [
        'user_id'=>$sr_has_user_id,
        'requester_name'=>hasColumn($pdo,'service_requests','requester_name'),
        'requestor_name'=>hasColumn($pdo,'service_requests','requestor_name'),
        'contact_number'=>$sr_has_contact_number,
      ],
    ];
  }
} catch (Throwable $e) {
  if ($DEBUG) $debug['service_requests_error'] = $e->getMessage();
}

/** 8) RESERVATIONS (gym) */
try {
  $rv_has_user_id = hasColumn($pdo,'reservations','user_id');

  $rvOrder = buildOrder($pdo, 'reservations', [
    'claimed_at','paid_at','reservation_date','created_at','updated_at'
  ]);

  // If we have user_id, join users so we can match by users.{name, phone, email} too
  $joinUsers = $rv_has_user_id ? "LEFT JOIN users u ON u.id = r.user_id" : "LEFT JOIN users u ON 1=0";
  $sql = "SELECT r.*, 
                 u.first_name AS u_first_name, 
                 u.last_name  AS u_last_name, 
                 u.contact_number AS u_contact,
                 u.email AS u_email
          FROM reservations r
          $joinUsers
          $rvOrder
          LIMIT 500";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $matched = 0;
  foreach ($rows as $r) {
    $match = false;

    // 1) user_id match (strongest)
    if ($rv_has_user_id && $me['user_id'] > 0 && (int)($r['user_id'] ?? 0) === $me['user_id']) {
      $match = true;
    }

    // 2) users table match (lenient)
    if (!$match) {
      $uName   = norm_name(join_name($r['u_first_name'] ?? '', $r['u_last_name'] ?? ''));
      $uPhone  = norm_phone($r['u_contact'] ?? '');
      $uEmail  = mb_strtolower(trim((string)($r['u_email'] ?? '')));
      if ($uName !== ''  && $me['name']  !== '' && namesLikelySame($uName, $me['name'])) $match = true;
      if (!$match && $uPhone !== '' && $me['phone'] !== '' && phonesEqualLoose($uPhone, $me['phone'])) $match = true;
      if (!$match && $uEmail !== '' && $me['email'] !== '' && $uEmail === $me['email']) $match = true;
    }

    // 3) their own columns (lenient)
    if (!$match) {
      $rName  = norm_name((string)($r['resident_name'] ?? ''));
      $rPhone = norm_phone((string)($r['contact_number'] ?? ''));
      if ($rName !== '' && $me['name'] !== '' && namesLikelySame($rName, $me['name'])) $match = true;
      if (!$match && $rPhone !== '' && $me['phone'] !== '' && phonesEqualLoose($rPhone, $me['phone'])) $match = true;
    }

    if (!$match) continue;
    $matched++;

    $status    = mapReservationLifecycle($r);
    $rawStatus = strtolower(trim((string)($r['status'] ?? '')));
    $ref       = (string)($r['reference_number'] ?? $r['id'] ?? '');
    $submitted = toDateStr($r['created_at'] ?? $r['reservation_date'] ?? null) ?? '—';
    $updated   = toDateStr($r['claimed_at'] ?? $r['paid_at'] ?? $r['updated_at'] ?? $r['reservation_date'] ?? $r['created_at'] ?? null) ?? $submitted;

    $timeline = [];
    if (!empty($r['notes'])) $timeline[] = ['date'=>$updated, 'content'=>(string)$r['notes']];

    // Time slots pretty print (JSON or string)
    $slotsNice = '';
    if (!empty($r['time_slots'])) {
      $dec = json_decode((string)$r['time_slots'], true);
      if (is_array($dec)) {
        $parts = [];
        foreach ($dec as $s) {
          if (is_array($s) && isset($s['time'])) $parts[] = (string)$s['time'];
          elseif (is_array($s) && isset($s['hour'])) {
            $h = (int)$s['hour'];
            $fmt = function(int $x){ $ampm = $x >= 12 ? 'PM' : 'AM'; $dh = $x>12 ? $x-12 : ($x===0?12:$x); return "{$dh}:00 {$ampm}"; };
            $parts[] = $fmt($h).' - '.$fmt($h+1);
          } elseif (is_string($s) && trim($s) !== '') $parts[] = trim($s);
        }
        $slotsNice = implode(', ', $parts);
      } else {
        $slotsNice = (string)$r['time_slots'];
      }
    }
    if ($slotsNice !== '') $timeline[] = ['date'=>$updated, 'content'=>"Time Slots: ".$slotsNice];

    $activity = (string)($r['activity'] ?? '');
    $type = 'Gym Reservation'.($activity !== '' ? ' - '.ucwords($activity) : '');

    $items[] = [
      'id'        => (int)$r['id'],
      'type'      => $type,
      'status'    => $status,      // waiting_approval | approved | incoming | ongoing | completed | rejected
      'raw_status'=> $rawStatus,   // for debugging
      'submitted' => $submitted,
      'updated'   => $updated,
      'officer'   => 'Barangay Staff',
      'reference' => $ref ?: (string)($r['id'] ?? ''),
      'estimated' => toDateStr($r['reservation_date'] ?? null),
      'documents' => [],
      'timeline'  => $timeline,
    ];
  }

  if ($DEBUG) {
    $debug['reservations'] = [
      'order_by' => $rvOrder,
      'read'     => count($rows),
      'matched'  => $matched,
      'have_cols'=> ['user_id'=>$rv_has_user_id],
    ];
  }
} catch (Throwable $e) {
  if ($DEBUG) $debug['reservations_error'] = $e->getMessage();
}

/** 9) Sort & respond */
usort($items, function($a,$b){
  return (strtotime($b['submitted'] ?? '0') <=> strtotime($a['submitted'] ?? '0'))
      ?: (strtotime($b['updated']   ?? '0') <=> strtotime($a['updated']   ?? '0'));
});

$out = ['success'=>true, 'data'=>['items'=>$items]];
if ($DEBUG) $out['debug'] = $debug;

echo json_encode($out);
