<?php
declare(strict_types=1);

/**
 * USER SIDE — Gymback.php
 * - Public:  action=get_slots, get_month_summary
 * - Auth:    action=create_reservation  (expects $_SESSION['user_id'])
 */
require_once __DIR__ . '/../server/config.php'; // starts session + PDO + security headers
header('Content-Type: application/json');

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('Asia/Manila');
}

/* ---------- DB helpers ---------- */
function db(): PDO { return getDBConnection(); }

/** Read gym rates (with fallback) */
function gym_rates(PDO $db): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $row = $db->query("SELECT morning_rate, evening_rate FROM gym_pricing WHERE id=1")->fetch();
  $cache = [
    'morning' => isset($row['morning_rate']) ? (float)$row['morning_rate'] : 200.0,
    'evening' => isset($row['evening_rate']) ? (float)$row['evening_rate'] : 300.0,
  ];
  return $cache;
}
function rate_for_hour(PDO $db, int $h): float {
  $r = gym_rates($db);
  return ($h < 17) ? $r['morning'] : $r['evening'];
}

/** Schema helper */
function reservations_has_user_id(PDO $db): bool {
  static $cached = null;
  if ($cached !== null) return $cached;
  try {
    $stmt = $db->query("SHOW COLUMNS FROM reservations LIKE 'user_id'");
    $cached = ($stmt && $stmt->rowCount() > 0);
  } catch (Throwable $e) { $cached = false; }
  return $cached;
}

/** User fallback profile */
function db_get_user_by_id(PDO $db, int $uid): array {
  $stmt = $db->prepare("SELECT first_name, last_name, contact_number FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$uid]);
  if ($row = $stmt->fetch()) {
    $fn = trim((string)($row['first_name'] ?? ''));
    $ln = trim((string)($row['last_name'] ?? ''));
    return [
      'full_name' => trim($fn.' '.$ln),
      'contact'   => trim((string)($row['contact_number'] ?? '')),
    ];
  }
  return [];
}

function sanitize_phone(string $s): string { return preg_replace('/[^0-9+\-\s]/', '', $s); }
function require_user_or_fail(): void {
  if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Please log in.']); exit;
  }
}

/* ---------- Input ---------- */
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || !$input) {
  $input = $_POST;
  if (isset($input['slots']) && is_string($input['slots'])) {
    $decoded = json_decode($input['slots'], true);
    if (json_last_error() === JSON_ERROR_NONE) $input['slots'] = $decoded;
  }
}
$action = $input['action'] ?? '';

try {
  $db = db();

  /* ===== get_month_summary (PUBLIC) ===== */
  if ($action === 'get_month_summary') {
    $year  = (int)($input['year'] ?? 0);
    $month = (int)($input['month'] ?? 0);

    if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
      echo json_encode(['status'=>'error','message'=>'Invalid year or month']); exit;
    }

    $start = sprintf('%04d-%02d-01', $year, $month);
    $startDT = DateTimeImmutable::createFromFormat('Y-m-d', $start, new DateTimeZone('Asia/Manila'));
    if (!$startDT) { echo json_encode(['status'=>'error','message'=>'Invalid date']); exit; }
    $endDT = $startDT->modify('last day of this month');

    $stmt = $db->prepare("
      SELECT reservation_date, time_slots
      FROM reservations
      WHERE reservation_date BETWEEN ? AND ?
        AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('cancelled','rejected')
    ");
    $stmt->execute([$startDT->format('Y-m-d'), $endDT->format('Y-m-d')]);

    $bookedMap = []; // date => set of hours
    while ($row = $stmt->fetch()) {
      $date = (string)$row['reservation_date'];
      if (!isset($bookedMap[$date])) $bookedMap[$date] = [];
      $slots = json_decode($row['time_slots'] ?? '[]', true);
      if (is_array($slots)) {
        foreach ($slots as $s) {
          if (isset($s['hour'])) {
            $bookedMap[$date][(int)$s['hour']] = true;
          }
        }
      }
    }

    $TOTAL_SLOTS = 15; // 7:00–22:00
    $days = [];
    $cursor = $startDT;
    while ($cursor <= $endDT) {
      $d = $cursor->format('Y-m-d');
      $bookedCount = isset($bookedMap[$d]) ? count($bookedMap[$d]) : 0;
      $days[] = ['date' => $d, 'booked' => $bookedCount];
      $cursor = $cursor->modify('+1 day');
    }

    $serverNow = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('c');
    $rates = gym_rates($db);

    echo json_encode([
      'status'     => 'success',
      'server_now' => $serverNow,
      'rates'      => ['morning' => (float)$rates['morning'], 'evening' => (float)$rates['evening']],
      'days'       => $days,
      'total_per_day' => $TOTAL_SLOTS
    ]);
    exit;
  }

  /* ===== get_slots (PUBLIC) ===== */
  if ($action === 'get_slots') {
    $date = trim((string)($input['date'] ?? ''));
    if ($date === '') { echo json_encode(['status'=>'error','message'=>'Missing date']); exit; }

    $stmt = $db->prepare("
      SELECT time_slots
      FROM reservations
      WHERE reservation_date = ?
        AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('cancelled','rejected')
    ");
    $stmt->execute([$date]);

    $booked = [];
    while ($row = $stmt->fetch()) {
      $slots = json_decode($row['time_slots'] ?? '[]', true);
      if (is_array($slots)) {
        foreach ($slots as $s) if (isset($s['hour'])) $booked[] = ['hour'=>(int)$s['hour']];
      }
    }

    $rates = gym_rates($db);
    $serverNow = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('c');
    echo json_encode([
      'status'     => 'success',
      'booked'     => $booked,
      'server_now' => $serverNow,
      'rates'      => ['morning' => (float)$rates['morning'], 'evening' => (float)$rates['evening']]
    ]);
    exit;
  }

  /* ===== create_reservation (LOGIN) ===== */
  if ($action === 'create_reservation') {
    require_user_or_fail();

    $userId   = (int)($_SESSION['user_id'] ?? 0);
    $fullName = trim((string)($_SESSION['full_name'] ?? (($_SESSION['first_name'] ?? '').' '.($_SESSION['last_name'] ?? ''))));
    $contact  = trim((string)($_SESSION['contact_number'] ?? $_SESSION['contact'] ?? ''));

    $date      = trim((string)($input['date'] ?? ''));
    $slots_in  = $input['slots'] ?? [];
    $activity  = trim((string)($input['activity'] ?? ''));
    $notes     = trim((string)($input['notes'] ?? ''));
    $reference = strtoupper(trim((string)($input['reference'] ?? '')));

    if ($fullName === '' || $contact === '') {
      $profile = db_get_user_by_id($db, $userId);
      if ($fullName === '') $fullName = (string)($profile['full_name'] ?? '');
      if ($contact  === '') $contact  = (string)($profile['contact'] ?? '');
    }
    if ($fullName === '' && !empty($input['resident'])) $fullName = trim((string)$input['resident']);
    if ($contact  === '' && !empty($input['contact']))  $contact  = sanitize_phone((string)$input['contact']);

    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      echo json_encode(['status'=>'error','message'=>'Invalid or missing date']); exit;
    }
    $tz    = new DateTimeZone('Asia/Manila');
    $now   = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $req   = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
    if (!$req) { echo json_encode(['status'=>'error','message'=>'Invalid date']); exit; }
    if ($req->format('Y-m-d') < $today) {
      echo json_encode(['status'=>'error','message'=>'Selected date is in the past']); exit;
    }

    if ($fullName === '' || $contact === '' || $activity === '') {
      echo json_encode(['status'=>'error','message'=>'Please complete all required fields']); exit;
    }
    if (!is_array($slots_in) || count($slots_in) === 0) {
      echo json_encode(['status'=>'error','message'=>'No time slots selected']); exit;
    }

    // normalize hours
    $hours = [];
    foreach ($slots_in as $s) {
      if (!isset($s['hour'])) continue;
      $h = (int)$s['hour'];
      if ($h < 7 || $h > 21) { echo json_encode(['status'=>'error','message'=>'Invalid slot hour selected']); exit; }
      $hours[$h] = true;
    }
    $hours = array_values(array_unique(array_map('intval', array_keys($hours))));
    sort($hours);

    if ($req->format('Y-m-d') === $today) {
      $currentHour = (int)$now->format('G');
      foreach ($hours as $h) if ($h <= $currentHour) {
        echo json_encode(['status'=>'error','message'=>"Slot {$h}:00 - ".($h+1).":00 is already in the past."]); exit;
      }
    }

    // conflicts
    $stmt = $db->prepare("
      SELECT time_slots FROM reservations
      WHERE reservation_date = ?
        AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('cancelled','rejected')
    ");
    $stmt->execute([$date]);
    $booked = [];
    while ($row = $stmt->fetch()) {
      $slots = json_decode($row['time_slots'] ?? '[]', true);
      if (is_array($slots)) foreach ($slots as $s) if (isset($s['hour'])) $booked[(int)$s['hour']] = true;
    }
    foreach ($hours as $h) if (isset($booked[$h])) {
      echo json_encode(['status'=>'error','message'=>"Slot conflict: {$h}:00 - ".($h+1).":00 already booked"]); exit;
    }

    // compute total & payload
    $rates = gym_rates($db);
    $fmt = fn(int $x) => ($x>12?($x-12):$x).":00 ".($x>=12?'PM':'AM');

    $total = 0.0; $slots_to_store = [];
    foreach ($hours as $h) {
      $rate = ($h < 17) ? (float)$rates['morning'] : (float)$rates['evening'];
      $total += $rate;
      $slots_to_store[] = [
        'hour'=>$h,'time'=>$fmt($h).' - '.$fmt($h+1),
        'rate'=>$rate,'rateType'=>($h<17?'morning':'evening')
      ];
    }

    if ($reference === '') $reference = 'GYM-'.substr(md5(uniqid('',true)),0,7);
    $tries=0;
    while ($tries<3) {
      $chk=$db->prepare("SELECT id FROM reservations WHERE reference_number=? LIMIT 1");
      $chk->execute([$reference]);
      if (!$chk->fetch()) break;
      $reference = $reference.'-'.($tries+1); $tries++;
    }

    $slots_json = json_encode($slots_to_store, JSON_UNESCAPED_UNICODE);
    $hasUid     = reservations_has_user_id($db);

    if ($hasUid) {
      $sql="INSERT INTO reservations (user_id,reservation_date,time_slots,resident_name,contact_number,activity,notes,reference_number,total_amount,status)
            VALUES (?,?,?,?,?,?,?,?,?,'pending')";
      $ok=$db->prepare($sql)->execute([$userId,$date,$slots_json,$fullName,$contact,$activity,$notes,$reference,$total]);
    } else {
      $sql="INSERT INTO reservations (reservation_date,time_slots,resident_name,contact_number,activity,notes,reference_number,total_amount,status)
            VALUES (?,?,?,?,?,?,?,?, 'pending')";
      $ok=$db->prepare($sql)->execute([$date,$slots_json,$fullName,$contact,$activity,$notes,$reference,$total]);
    }

    if ($ok) echo json_encode(['status'=>'success','message'=>'Reservation created','reference'=>$reference,'total'=>$total]);
    else     echo json_encode(['status'=>'error','message'=>'DB error']);
    exit;
  }

  echo json_encode(['status'=>'error','message'=>'Invalid action']); exit;

} catch (Throwable $e) {
  error_log('Gymback (user) error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'Server error']); exit;
}
