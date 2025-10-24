<?php
declare(strict_types=1);

/**
 * ADMIN SIDE — gymsback.php
 * - Public:  action=get_slots, get_month_summary
 * - Auth:    action=create_reservation  (expects $_SESSION['admin_id'])
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

/** Schema helper: does reservations table have user_id? */
function reservations_has_user_id(PDO $db): bool {
  static $cached = null;
  if ($cached !== null) return $cached;
  try {
    $stmt = $db->query("SHOW COLUMNS FROM reservations LIKE 'user_id'");
    $cached = ($stmt && $stmt->rowCount() > 0);
  } catch (Throwable $e) { $cached = false; }
  return $cached;
}

/** Admin gate */
function require_admin_or_fail(): void {
  if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Please log in as admin.']); exit;
  }
}

/** Small util */
function sanitize_phone(string $s): string { return preg_replace('/[^0-9+\-\s]/', '', $s); }

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
      if (is_array($slots)) foreach ($slots as $s) if (isset($s['hour'])) $booked[] = ['hour'=>(int)$s['hour']];
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

  /* ===== get_month_summary (PUBLIC) =====
     Returns booked count per date for the month shown.
     Response:
       {
         status: "success",
         days: [{date:"YYYY-MM-DD", booked:<int>}, ...],
         total_per_day: 15,
         server_now: ISO8601,
         rates: {morning: number, evening: number}
       }
  */
  if ($action === 'get_month_summary') {
    $year  = (int)($input['year']  ?? 0);
    $month = (int)($input['month'] ?? 0); // 1..12

    if ($year < 2000 || $month < 1 || $month > 12) {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'Invalid year/month']); exit;
    }

    // Compute month start/end in Manila time
    $tz = new DateTimeZone('Asia/Manila');
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
    $end   = $start->modify('first day of next month');

    // Fetch all reservations of that month (excluding cancelled/rejected)
    $stmt = $db->prepare("
      SELECT reservation_date, time_slots
      FROM reservations
      WHERE reservation_date >= ? AND reservation_date < ?
        AND TRIM(LOWER(COALESCE(status,''))) NOT IN ('cancelled','rejected')
    ");
    $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d')]);

    // Count hours reserved per day
    $dayCounts = []; // 'YYYY-MM-DD' => int hours
    while ($row = $stmt->fetch()) {
      $date = (string)$row['reservation_date'];
      $slots = json_decode($row['time_slots'] ?? '[]', true);
      $count = 0;
      if (is_array($slots)) {
        foreach ($slots as $s) if (isset($s['hour'])) $count++;
      }
      if (!isset($dayCounts[$date])) $dayCounts[$date] = 0;
      $dayCounts[$date] += $count;
    }

    // Build dense array for every day of the month
    $days = [];
    for ($d = 0; ; $d++) {
      $curr = $start->modify("+{$d} day");
      if ($curr >= $end) break;
      $ymd = $curr->format('Y-m-d');
      $days[] = [
        'date'   => $ymd,
        'booked' => (int)($dayCounts[$ymd] ?? 0)
      ];
    }

    $rates = gym_rates($db);
    $serverNow = (new DateTimeImmutable('now', $tz))->format('c');

    echo json_encode([
      'status'        => 'success',
      'days'          => $days,
      'total_per_day' => 15, // slots from 07:00–22:00 are 15 hours
      'server_now'    => $serverNow,
      'rates'         => ['morning' => (float)$rates['morning'], 'evening' => (float)$rates['evening']]
    ]);
    exit;
  }

  /* ===== create_reservation (ADMIN) ===== */
  if ($action === 'create_reservation') {
    require_admin_or_fail();

    $date      = trim((string)($input['date'] ?? ''));
    $slots_in  = $input['slots'] ?? [];
    $activity  = trim((string)($input['activity'] ?? ''));
    $notes     = trim((string)($input['notes'] ?? ''));
    $reference = strtoupper(trim((string)($input['reference'] ?? '')));
    $resident  = trim((string)($input['resident'] ?? ''));
    $contact   = sanitize_phone((string)($input['contact'] ?? ''));

    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'Invalid or missing date']); exit;
    }

    $tz    = new DateTimeZone('Asia/Manila');
    $now   = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $req   = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
    if (!$req) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Invalid date']); exit; }
    if ($req->format('Y-m-d') < $today) {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'Selected date is in the past']); exit;
    }

    if ($resident === '' || $contact === '' || $activity === '') {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'Please complete all required fields']); exit;
    }
    if (!is_array($slots_in) || count($slots_in) === 0) {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'No time slots selected']); exit;
    }

    // normalize + validate hours
    $hours = [];
    foreach ($slots_in as $s) {
      if (!isset($s['hour'])) continue;
      $h = (int)$s['hour'];
      if ($h < 7 || $h > 21) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Invalid slot hour selected']); exit; }
      $hours[$h] = true;
    }
    $hours = array_values(array_unique(array_map('intval', array_keys($hours))));
    sort($hours);

    if ($req->format('Y-m-d') === $today) {
      $currentHour = (int)$now->format('G');
      foreach ($hours as $h) if ($h <= $currentHour) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>"Slot {$h}:00 - ".($h+1).":00 is already in the past."]); exit;
      }
    }

    // conflict check
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
      if (is_array($slots)) foreach ($slots as $s) if (isset($s['hour'])) $booked[(int)$s['hour']] = true;
    }
    foreach ($hours as $h) if (!empty($booked[$h])) {
      http_response_code(409);
      echo json_encode(['status'=>'error','message'=>"Slot conflict: {$h}:00 - ".($h+1).":00 already booked"]); exit;
    }

    // compute total & build stored slots with live rates
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

    // INSERT (handle schema with/without user_id)
    $hasUid = reservations_has_user_id($db);
    if ($hasUid) {
      $sql="INSERT INTO reservations
            (user_id,reservation_date,time_slots,resident_name,contact_number,activity,notes,reference_number,total_amount,status)
            VALUES (NULL,?,?,?,?,?,?,?,?,'pending')";
      $params = [$date,$slots_json,$resident,$contact,$activity,$notes,$reference,$total];
    } else {
      $sql="INSERT INTO reservations
            (reservation_date,time_slots,resident_name,contact_number,activity,notes,reference_number,total_amount,status)
            VALUES (?,?,?,?,?,?,?,?,'pending')";
      $params = [$date,$slots_json,$resident,$contact,$activity,$notes,$reference,$total];
    }

    $ok=$db->prepare($sql)->execute($params);
    if ($ok) {
      echo json_encode(['status'=>'success','message'=>'Reservation created','reference'=>$reference,'total'=>$total]);
    } else {
      http_response_code(500);
      echo json_encode(['status'=>'error','message'=>'DB error']);
    }
    exit;
  }

  http_response_code(400);
  echo json_encode(['status'=>'error','message'=>'Invalid action']); exit;

} catch (Throwable $e) {
  error_log('gymsback (admin) error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'Server error']); exit;
}
