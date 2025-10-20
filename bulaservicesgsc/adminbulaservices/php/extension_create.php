<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

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
function calcTieredAmount(DateTime $start, DateTime $end): float {
  // 07:00–17:00 => 200/hr, 17:00–22:00 => 300/hr
  $total = 0.0;
  $cur = clone $start;
  while ($cur < $end) {
    $h = (int)$cur->format('H');
    $total += ($h >= 7 && $h < 17) ? 200 : 300;
    $cur->modify('+1 hour');
  }
  return $total;
}

try {
  date_default_timezone_set('Asia/Manila');
  $db = getDBConnection();

  $in     = json_decode(file_get_contents('php://input'), true);
  $baseId = (int)($in['base_id'] ?? 0);
  $hours  = max(1, min(2, (int)($in['hours'] ?? 1))); // only 1 or 2 hours

  if ($baseId <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid reservation']); exit; }

  // Detect schema
  $hasStart = colExists($db, 'reservations', 'start_time');
  $hasEnd   = colExists($db, 'reservations', 'end_time');
  $hasDate  = colExists($db, 'reservations', 'reservation_date');
  $hasSlots = colExists($db, 'reservations', 'time_slots');

  // Load base reservation (no user_id needed)
  if ($hasStart && $hasEnd) {
    $stmt = $db->prepare("
      SELECT id, reference_number, start_time, end_time, resident_name, contact_number, status
      FROM reservations
      WHERE id = ? LIMIT 1
    ");
  } else {
    if (!$hasDate || !$hasSlots) {
      echo json_encode(['success'=>false,'message'=>'reservations table missing required columns']); exit;
    }
    $stmt = $db->prepare("
      SELECT id, reference_number, reservation_date, time_slots, resident_name, contact_number, status
      FROM reservations
      WHERE id = ? LIMIT 1
    ");
  }
  $stmt->execute([$baseId]);
  $base = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$base) { echo json_encode(['success'=>false,'message'=>'Reservation not found']); exit; }

  // Compute base start/end
  if ($hasStart && $hasEnd && isset($base['start_time'], $base['end_time'])) {
    $baseStart = new DateTime($base['start_time']);
    $baseEnd   = new DateTime($base['end_time']);
    $baseDate  = $baseStart->format('Y-m-d');
  } else {
    $hrs = parseHours($base['time_slots'] ?? '');
    if (empty($hrs)) { echo json_encode(['success'=>false,'message'=>'Base reservation has no hours']); exit; }
    $minH = min($hrs);
    $maxH = max($hrs) + 1; // last slot end
    $baseDate  = (string)$base['reservation_date'];
    $baseStart = new DateTime(sprintf('%s %02d:00:00', $baseDate, $minH));
    $baseEnd   = new DateTime(sprintf('%s %02d:00:00', $baseDate, $maxH));
  }

  // Only today's sessions can be extended
  $today = date('Y-m-d');
  if ($baseDate !== $today) {
    echo json_encode(['success'=>false,'message'=>'Only today\'s reservations can be extended']); exit;
  }

  // Must be paid-ish
  $paidish = ['paid','in_progress','released','claimed','completed'];
  if (!in_array(strtolower((string)$base['status']), $paidish, true)) {
    echo json_encode(['success'=>false,'message'=>'Reservation must be paid']); exit;
  }

  // Effective end = base end + approved/paid extensions
  $eff = clone $baseEnd;
  // Pull existing extensions (if table exists it will, but just in case)
  $qext = $db->prepare("
    SELECT start_time, end_time
    FROM reservation_extensions
    WHERE reservation_id = ? AND LOWER(status) IN ('approved','paid','completed','released','claimed')
    ORDER BY start_time
  ");
  $qext->execute([$baseId]);
  while ($e = $qext->fetch(PDO::FETCH_ASSOC)) {
    $ee = new DateTime($e['end_time']);
    if ($ee > $eff) $eff = $ee;
  }

  $start = clone $eff;
  $end   = (clone $start)->modify("+{$hours} hour");

  // Business hours 07:00–22:00
  $open  = new DateTime($baseDate.' 07:00:00');
  $close = new DateTime($baseDate.' 22:00:00');
  if ($start < $open || $end > $close) {
    echo json_encode(['success'=>false,'message'=>'Extension must be within 07:00–22:00']); exit;
  }

  // Overlap check vs other extensions
  $chk = $db->prepare("
    SELECT 1 FROM reservation_extensions
    WHERE reservation_id = ?
      AND LOWER(status) IN ('approved','paid','completed','released','claimed')
      AND NOT (? >= end_time OR ? <= start_time)
    LIMIT 1
  ");
  $chk->execute([$baseId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
  if ($chk->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Overlaps an existing extension']); exit; }

  // Tiered amount
  $amount = calcTieredAmount($start, $end);

  // Insert extension as 'approved' (to be paid at Cashier)
  $db->beginTransaction();
  $ins = $db->prepare("
    INSERT INTO reservation_extensions (reservation_id, start_time, end_time, amount, status, approved_at)
    VALUES (?, ?, ?, ?, 'approved', NOW())
  ");
  $ins->execute([$baseId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $amount]);
  $extId = (int)$db->lastInsertId();
  $ref   = 'EXT-'.$baseId.'-'.date('YmdHis');
  $db->commit();

  echo json_encode(['success'=>true,'id'=>$extId,'code'=>$ref,'amount'=>$amount]);

} catch (Throwable $e) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  error_log('extension_create: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
