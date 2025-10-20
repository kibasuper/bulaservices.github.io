<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

function colExists(PDO $db, string $table, string $col): bool {
  $q = $db->prepare("
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
    LIMIT 1
  ");
  $q->execute([$table, $col]);
  return (bool)$q->fetchColumn();
}

try {
  date_default_timezone_set('Asia/Manila');
  $db = getDBConnection();

  $from   = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
  $to     = isset($_GET['to'])   ? trim((string)$_GET['to'])   : '';
  $type   = isset($_GET['type']) ? strtolower((string)$_GET['type']) : 'all'; // gym|cert|all

  $dateCols = ['created_at','paid_at','updated_at','payment_date','timestamp','date_created'];
  $payDateCol = null;
  foreach ($dateCols as $c) { if (colExists($db,'payments',$c)) { $payDateCol=$c; break; } }

  $where = [];
  $params = [];
  if ($payDateCol) {
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) {
      $where[] = "DATE(p.`$payDateCol`) >= ?"; $params[] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) {
      $where[] = "p.`$payDateCol` <= ?"; $params[] = $to.' 23:59:59';
    }
  }

  // Build SQL with cashier (ca), releaser (ra), and approver (ap) names
  $sql = "
    SELECT
      p.id                  AS payment_id,
      p.receipt_number,
      p.user_id,
      p.cashier_id,
      p.total_amount,
      p.cash_given,
      p.change_amount,
      ".($payDateCol ? "p.`$payDateCol`" : "p.id")." AS payment_order_key,

      -- cashier
      ca.first_name         AS cashier_first,
      ca.last_name          AS cashier_last,

      -- link items
      pi.id                 AS payment_item_id,
      pi.request_type,
      pi.request_id,

      -- service requests (certificates)
      sr.id                 AS sr_id,
      sr.reference_number   AS sr_ref,
      sr.status             AS sr_status,
      sr.service_type       AS sr_service_type,
      sr.purpose            AS sr_purpose,
      sr.claimed_by         AS sr_claimed_by,
      sr.claimed_at         AS sr_claimed_at,
      sr.approved_by        AS sr_approved_by,
      sr.approved_date      AS sr_approved_date,

      -- approver
      ap.first_name         AS approver_first,
      ap.last_name          AS approver_last,

      -- releaser
      ra.first_name         AS releaser_first,
      ra.last_name          AS releaser_last,

      -- gym reservations
      rs.id                 AS rs_id,
      rs.reference_number   AS rs_ref,
      rs.status             AS rs_status,
      rs.resident_name      AS rs_resident_name,
      rs.contact_number     AS rs_contact_number,

      -- users (fallback display)
      u.first_name          AS u_first,
      u.last_name           AS u_last,
      u.contact_number      AS u_contact
    FROM payments p
    JOIN payment_items pi         ON pi.payment_id = p.id
    LEFT JOIN service_requests sr ON sr.id = pi.request_id
    LEFT JOIN admins ra           ON ra.admin_id = sr.claimed_by       -- releaser
    LEFT JOIN admins ap           ON ap.admin_id = sr.approved_by      -- approver
    LEFT JOIN reservations rs     ON rs.id = pi.request_id
    LEFT JOIN users u             ON u.id = p.user_id
    LEFT JOIN admins ca           ON ca.admin_id = p.cashier_id        -- cashier
  ";

  if (!empty($where)) $sql .= " WHERE ".implode(" AND ", $where);
  if ($type === 'gym')  { $sql .= (empty($where)?" WHERE ":" AND ")." rs.id IS NOT NULL "; }
  if ($type === 'cert') { $sql .= (empty($where)?" WHERE ":" AND ")." sr.id IS NOT NULL "; }

  $sql .= " ORDER BY ".($payDateCol ? "p.`$payDateCol` DESC" : "p.id DESC").", pi.id ASC";

  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // group -> payments, add cashier name and per-line approver/releaser
  $byPay = [];
  foreach ($rows as $r) {
    $pid = (int)$r['payment_id'];
    if (!isset($byPay[$pid])) {
      $name = $r['rs_resident_name'] ?: trim(($r['u_first']??'').' '.($r['u_last']??''));
      if ($name==='') $name='Walk-in Customer';
      $contact = $r['rs_contact_number'] ?: ($r['u_contact'] ?? '');

      $cashierName = trim((($r['cashier_first'] ?? '').' '.($r['cashier_last'] ?? '')));
      if ($cashierName === '') $cashierName = 'Unknown';

      $byPay[$pid] = [
        'payment_id'         => $pid,
        'receipt_number'     => $r['receipt_number'],
        'payment_date'       => $payDateCol ? $r['payment_order_key'] : null,
        'customer_name'      => $name,
        'customer_contact'   => (string)$contact,
        'total_amount'       => (float)$r['total_amount'],
        'processed_by'       => (int)$r['cashier_id'],
        'processed_by_name'  => $cashierName,

        // will be summarized across SR lines
        'approved_by'        => null,
        'approved_by_name'   => null,
        'approved_at'        => null,
        'released_by_summary'=> null,
        'released_at_summary'=> null,

        'requests'           => [],

        // flags to derive overall payment_status
        '_all_paid'          => true,
        '_any_approved'      => false,
        '_any_rejected'      => false,

        // collectors for summarization
        '_approver_names'    => [],
        '_approver_dates'    => [],
        '_releaser_names'    => [],
        '_releaser_dates'    => [],
      ];
    }

    $isGym   = !empty($r['rs_id']);
    $lineRef = $isGym ? $r['rs_ref'] : $r['sr_ref'];
    $lineSta = strtolower((string)($isGym ? $r['rs_status'] : $r['sr_status']));
    $svcType = $isGym ? 'Gym' : ($r['sr_service_type'] ?: 'Certificate');
    $desc    = $isGym
      ? ('Gym Reservation '.($r['rs_ref'] ? '#'.$r['rs_ref'] : ''))
      : ($r['sr_purpose'] ?: $svcType);

    $paidish     = ['paid','completed','released','claimed'];
    $approvedish = ['approved','processing'];
    $rejectedish = ['rejected','cancelled','canceled','void'];

    if (!in_array($lineSta,$paidish,true))        $byPay[$pid]['_all_paid']     = false;
    if (in_array($lineSta,$approvedish,true))     $byPay[$pid]['_any_approved'] = true;
    if (in_array($lineSta,$rejectedish,true))     $byPay[$pid]['_any_rejected'] = true;

    // ---- approver (SR only) ----
    if (!$isGym && !empty($r['sr_approved_by'])) {
      $an = trim((($r['approver_first'] ?? '').' '.($r['approver_last'] ?? '')));
      if ($an !== '') $byPay[$pid]['_approver_names'][$an] = true; // unique set
      if (!empty($r['sr_approved_date'])) $byPay[$pid]['_approver_dates'][] = $r['sr_approved_date'];
    }

    // ---- released by (SR only) ----
    $releasedByName = null;
    $releasedAt     = null;
    if (!$isGym) {
      $rf = trim((($r['releaser_first'] ?? '').' '.($r['releaser_last'] ?? '')));
      if ($rf !== '') $releasedByName = $rf;
      if (!empty($r['sr_claimed_at'])) $releasedAt = $r['sr_claimed_at'];

      if ($releasedByName) $byPay[$pid]['_releaser_names'][$releasedByName] = true;
      if ($releasedAt)     $byPay[$pid]['_releaser_dates'][] = $releasedAt;
    }

    $byPay[$pid]['requests'][] = [
      'transaction_no'    => $lineRef ?: null,
      'service_type'      => $svcType,
      'description'       => $desc,
      'status'            => $lineSta ?: 'pending',
      'released_by_name'  => $releasedByName,   // null for gym
      'released_at'       => $releasedAt,
      'claimed_by_admin'  => $r['sr_claimed_by'] ?: null,
      'claimed_at'        => $r['sr_claimed_at'] ?: null
    ];
  }

  // finalize payment status and summarize actors
  $out = [];
  foreach ($byPay as $p) {
    if ($p['_any_rejected'])      { $p['payment_status'] = 'rejected'; }
    elseif ($p['_all_paid'])      { $p['payment_status'] = 'completed'; }
    elseif ($p['_any_approved'])  { $p['payment_status'] = 'approved'; }
    else                          { $p['payment_status'] = 'pending'; }

    // summarize approver names/dates
    $approverNames = array_keys($p['_approver_names']);
    if (count($approverNames) > 1) {
      $p['approved_by_name'] = 'Multiple';
    } elseif (count($approverNames) === 1) {
      $p['approved_by_name'] = $approverNames[0];
    }
    if (!empty($p['_approver_dates'])) {
      rsort($p['_approver_dates']);
      $p['approved_at'] = $p['_approver_dates'][0]; // most recent
    }

    // summarize releasers
    $releaserNames = array_keys($p['_releaser_names']);
    if (count($releaserNames) > 1) {
      $p['released_by_summary'] = 'Multiple';
    } elseif (count($releaserNames) === 1) {
      $p['released_by_summary'] = $releaserNames[0];
    }
    if (!empty($p['_releaser_dates'])) {
      rsort($p['_releaser_dates']);
      $p['released_at_summary'] = $p['_releaser_dates'][0]; // most recent
    }

    unset(
      $p['_all_paid'], $p['_any_approved'], $p['_any_rejected'],
      $p['_approver_names'], $p['_approver_dates'],
      $p['_releaser_names'], $p['_releaser_dates']
    );

    $out[] = $p;
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('get_transactions fatal: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
