<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

function tableExists(PDO $db, string $table): bool {
  $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
  $q->execute([$table]);
  return (bool)$q->fetchColumn();
}
function colExists(PDO $db, string $table, string $col): bool {
  $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $q->execute([$table,$col]);
  return (bool)$q->fetchColumn();
}
function parseHours(?string $json): array {
  $hrs = [];
  if ($json) {
    $arr = json_decode($json, true);
    if (is_array($arr)) {
      foreach ($arr as $s) {
        if (is_array($s) && isset($s['hour']) && is_numeric($s['hour'])) $hrs[] = (int)$s['hour'];
        elseif (is_string($s) && preg_match('/(\d{1,2}):00/', $s, $m)) $hrs[] = (int)$m[1];
      }
    }
  }
  $hrs = array_values(array_unique($hrs));
  sort($hrs);
  return $hrs;
}

try {
  date_default_timezone_set('Asia/Manila');
  $db = getDBConnection();

  // inputs
  $q          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $daysAhead  = isset($_GET['days_ahead']) ? max(1, min(30, (int)$_GET['days_ahead'])) : 14; // default 14 days
  $limitRows  = 500;

  $today = new DateTime('today');
  $until = (clone $today)->modify("+{$daysAhead} days");
  $now   = new DateTime();

  $hasStart = colExists($db, 'reservations', 'start_time');
  $hasEnd   = colExists($db, 'reservations', 'end_time');
  $hasDate  = colExists($db, 'reservations', 'reservation_date');
  $hasSlots = colExists($db, 'reservations', 'time_slots');
  $hasExtT  = tableExists($db, 'reservation_extensions');

  // statuses we consider for display (intake is already done)
  $paidish = ['paid','in_progress','released','claimed']; // NOT completed

  // Build query according to schema; fetch within [today, today+N] range
  $params = [];
  if ($hasStart && $hasEnd) {
    $sql = "
      SELECT id, reference_number, resident_name, contact_number, start_time, end_time, status
      FROM reservations
      WHERE DATE(start_time) >= ? AND DATE(start_time) <= ?
        AND TRIM(LOWER(COALESCE(status,''))) IN (" . implode(',', array_fill(0,count($paidish),'?')). ")
    ";
    $params[] = $today->format('Y-m-d');
    $params[] = $until->format('Y-m-d');
    foreach ($paidish as $s) $params[] = $s;

    if ($q !== '') {
      $like = '%'.$q.'%';
      $sql .= " AND (reference_number LIKE ? OR resident_name LIKE ? OR contact_number LIKE ?)";
      array_push($params, $like, $like, $like);
    }
    $sql .= " ORDER BY start_time ASC, id ASC LIMIT {$limitRows}";
  } else {
    if (!$hasDate || !$hasSlots) throw new Exception('reservations table missing required columns');
    $sql = "
      SELECT id, reference_number, resident_name, contact_number, reservation_date, time_slots, status
      FROM reservations
      WHERE reservation_date >= ? AND reservation_date <= ?
        AND TRIM(LOWER(COALESCE(status,''))) IN (" . implode(',', array_fill(0,count($paidish),'?')). ")
    ";
    $params[] = $today->format('Y-m-d');
    $params[] = $until->format('Y-m-d');
    foreach ($paidish as $s) $params[] = $s;

    if ($q !== '') {
      $like = '%'.$q.'%';
      $sql .= " AND (COALESCE(reference_number, CONCAT('RES-', id)) LIKE ? OR resident_name LIKE ? OR contact_number LIKE ?)";
      array_push($params, $like, $like, $like);
    }
    $sql .= " ORDER BY reservation_date ASC, id ASC LIMIT {$limitRows}";
  }

  $st = $db->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $itemsUpcoming = [];
  $itemsOngoing  = [];

  foreach ($rows as $r) {
    // compute base start/end from either schema
    if ($hasStart && $hasEnd) {
      $baseStart = new DateTime($r['start_time']);
      $baseEnd   = new DateTime($r['end_time']);
      $theDate   = substr($r['start_time'],0,10);
      $timesLbl  = $baseStart->format('H:i').'–'.$baseEnd->format('H:i');
    } else {
      $hrs = parseHours($r['time_slots'] ?? '');
      if (empty($hrs)) continue;
      $minH = min($hrs);
      $maxH = max($hrs) + 1;
      $theDate   = $r['reservation_date'];
      $baseStart = new DateTime(sprintf('%s %02d:00:00', $theDate, $minH));
      $baseEnd   = new DateTime(sprintf('%s %02d:00:00', $theDate, $maxH));
      $timesLbl  = sprintf('%02d:00–%02d:00', $minH, $maxH);
    }

    // compute effective end from extensions (approved/paid/etc)
    $effectiveEnd = clone $baseEnd;
    $exts = [];
    if ($hasExtT) {
      $qext = $db->prepare("
        SELECT id, start_time, end_time, status
        FROM reservation_extensions
        WHERE reservation_id = ? AND DATE(start_time) = ? AND TRIM(LOWER(status)) IN ('approved','paid','released','claimed')
        ORDER BY start_time
      ");
      $qext->execute([(int)$r['id'], $theDate]);
      $exts = $qext->fetchAll(PDO::FETCH_ASSOC);
      foreach ($exts as $e) {
        $ee = new DateTime($e['end_time']);
        if ($ee > $effectiveEnd) $effectiveEnd = $ee;
      }
    }

    // filter out finished (now >= effective_end)
    if ($now >= $effectiveEnd) {
      continue; // do NOT include finished rows
    }

    // status + timers
    if ($now < $baseStart) {
      $status = 'upcoming';
      $secondsToStart = $baseStart->getTimestamp() - $now->getTimestamp();
      $secondsToEnd   = null;
    } else {
      $status = 'ongoing';
      $secondsToStart = null;
      $secondsToEnd   = $effectiveEnd->getTimestamp() - $now->getTimestamp();
    }

    // 07:00–22:00 timeline (visual only)
    $open  = new DateTime($theDate.' 07:00:00');
    $close = new DateTime($theDate.' 22:00:00');
    $timeline = [];
    for ($t = clone $open; $t < $close; $t->modify('+1 hour')) {
      $t2   = (clone $t)->modify('+1 hour');
      $busy = false;
      if (!($t2 <= $baseStart || $t >= $baseEnd)) $busy = true;
      if (!$busy && !empty($exts)) {
        foreach ($exts as $e) {
          $es = new DateTime($e['start_time']); $ee = new DateTime($e['end_time']);
          if (!($t2 <= $es || $t >= $ee)) { $busy = true; break; }
        }
      }
      $timeline[] = ['label'=>$t->format('H:i').'-'.$t2->format('H:i'), 'busy'=>$busy];
    }

    $rowOut = [
      'id'              => (int)$r['id'],
      'code'            => (string)($r['reference_number'] ?? ('RES-'.$r['id'])),
      'customer'        => (string)($r['resident_name'] ?? '—'),
      'contact'         => (string)($r['contact_number'] ?? ''),
      'date'            => date('M d, Y', strtotime($theDate)),
      'times'           => $timesLbl,

      'status'          => $status, // 'upcoming' | 'ongoing'
      'seconds_to_start'=> $secondsToStart,
      'seconds_to_end'  => $secondsToEnd,

      // Bands for quoting
      'rate_day'        => 200,
      'rate_night'      => 300,
      'day_range'       => '07:00–17:00',
      'night_range'     => '17:00–22:00',

      'timeline'        => $timeline,
      'start_time'      => $baseStart->format('Y-m-d H:i:s'),
      'end_time'        => $baseEnd->format('Y-m-d H:i:s'),
      'effective_end'   => $effectiveEnd->format('Y-m-d H:i:s'),
    ];

    if ($status === 'ongoing') $itemsOngoing[] = $rowOut;
    else                       $itemsUpcoming[] = $rowOut;
  }

  // sort: ongoing by time left asc, upcoming by starts in asc
  usort($itemsOngoing,  fn($a,$b)=>($a['seconds_to_end']   <=> $b['seconds_to_end']));
  usort($itemsUpcoming, fn($a,$b)=>($a['seconds_to_start'] <=> $b['seconds_to_start']));

  echo json_encode([
    'success'    => true,
    'now_server' => $now->format('Y-m-d H:i:s'),
    'items'      => array_merge($itemsOngoing, $itemsUpcoming)
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('extension_list fatal: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
