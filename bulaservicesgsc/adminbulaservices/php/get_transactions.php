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
  $type   = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'all';
  $q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all';

  // pick best date column on payments table
  $dateCols = ['created_at','paid_at','updated_at','payment_date','timestamp','date_created'];
  $payDateCol = null;
  foreach ($dateCols as $c) { if (colExists($db,'payments',$c)) { $payDateCol=$c; break; } }

  $where = [];
  $params = [];
  if ($payDateCol) {
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) {
      $where[] = "DATE(p.`$payDateCol`) >= ?";
      $params[] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) {
      $where[] = "DATE(p.`$payDateCol`) <= ?";
      $params[] = $to;
    }
  }

  if ($q !== '') {
    $escaped = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q);
    $like = '%' . $escaped . '%';

    $where[] = "(
        p.receipt_number LIKE ? OR
        CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? OR
        u.first_name LIKE ? OR
        u.last_name LIKE ? OR
        sr.reference_number LIKE ? OR
        rs.reference_number LIKE ? OR
        sr.service_type LIKE ?
      )";
    for ($i=0;$i<7;$i++) $params[] = $like;
  }

  $SR_TYPES = [
    'barangay_clearance','business_permit','indigency','residency',
    'cedula','ivs','low_income','proof_income','other'
  ];

  // Note: we explicitly select sr.paid_at and rs.paid_at and sr.rejected_reason so we can
  // use them to decide statuses reliably.
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

      -- payment item
      pi.id                 AS payment_item_id,
      pi.request_type       AS pi_request_type,
      pi.request_id,

      -- service_requests (selected fields)
      sr.id                 AS sr_id,
      sr.reference_number   AS sr_ref,
      sr.status             AS sr_status,
      sr.service_type       AS sr_service_type,
      sr.purpose            AS sr_purpose,
      sr.paid_at            AS sr_paid_at,
      sr.claimed_by         AS sr_claimed_by,
      sr.claimed_at         AS sr_claimed_at,
      sr.approved_by        AS sr_approved_by,
      sr.approved_date      AS sr_approved_date,
      sr.rejected_reason    AS sr_rejected_reason,

      -- approver / releaser names
      ap.first_name         AS approver_first,
      ap.last_name          AS approver_last,
      ra.first_name         AS releaser_first,
      ra.last_name          AS releaser_last,

      -- reservations (selected fields)
      rs.id                 AS rs_id,
      rs.reference_number   AS rs_ref,
      rs.status             AS rs_status,
      rs.paid_at            AS rs_paid_at,
      rs.resident_name      AS rs_resident_name,
      rs.contact_number     AS rs_contact_number,

      -- users (fallback display)
      u.first_name          AS u_first,
      u.last_name           AS u_last,
      u.contact_number      AS u_contact
    FROM payments p
    JOIN payment_items pi
      ON pi.payment_id = p.id

    LEFT JOIN service_requests sr
      ON sr.id = pi.request_id
     AND pi.request_type IN ('".implode("','",$SR_TYPES)."')

    LEFT JOIN admins ra
      ON ra.admin_id = sr.claimed_by
    LEFT JOIN admins ap
      ON ap.admin_id = sr.approved_by

    LEFT JOIN reservations rs
      ON rs.id = pi.request_id
     AND pi.request_type = 'gym_reservation'

    LEFT JOIN users u
      ON u.id = p.user_id
    LEFT JOIN admins ca
      ON ca.admin_id = p.cashier_id
  ";

  if (!empty($where)) $sql .= " WHERE ".implode(" AND ", $where);

  if ($type === 'gym') {
    $sql .= (empty($where) ? " WHERE " : " AND ") . " pi.request_type = 'gym_reservation' ";
  } elseif (in_array($type, $SR_TYPES, true)) {
    $sql .= (empty($where) ? " WHERE " : " AND ") . " LOWER(sr.service_type) = ? ";
    $params[] = strtolower($type);
  } elseif ($type === 'other') {
    $placeholders = implode(',', array_fill(0, count($SR_TYPES), '?'));
    $sql .= (empty($where) ? " WHERE " : " AND ") . " (sr.service_type IS NULL OR LOWER(sr.service_type) NOT IN ($placeholders)) ";
    foreach ($SR_TYPES as $s) $params[] = strtolower($s);
  }

  $sql .= " ORDER BY ".($payDateCol ? "p.`$payDateCol` DESC" : "p.id DESC").", pi.id ASC";

  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // group → payments
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

        'approved_by'        => null,
        'approved_by_name'   => null,
        'approved_at'        => null,
        'released_by_summary'=> null,
        'released_at_summary'=> null,

        'requests'           => [],

        '_all_paid'          => true,
        '_any_approved'      => false,
        '_any_rejected'      => false,

        '_approver_names'    => [],
        '_approver_dates'    => [],
        '_releaser_names'    => [],
        '_releaser_dates'    => [],
      ];
    }

    // determine whether this payment_item is a gym reservation or a certificate/service request
    $isGym = (strtolower((string)$r['pi_request_type']) === 'gym_reservation');

    // Determine the raw line status more defensively:
    // prefer the explicit status column if present; otherwise infer from paid_at / other markers.
    $rawStatus = null;
    if ($isGym) {
      $rs_status = $r['rs_status'] ?? null;
      $rs_paid   = !empty($r['rs_paid_at']);
      if ($rs_status !== null && $rs_status !== '') $rawStatus = $rs_status;
      elseif ($rs_paid) $rawStatus = 'paid';
      else $rawStatus = ''; // empty -> will be treated as pending later
    } else {
      $sr_status = $r['sr_status'] ?? null;
      $sr_paid   = !empty($r['sr_paid_at']);
      if ($sr_status !== null && $sr_status !== '') $rawStatus = $sr_status;
      elseif ($sr_paid) $rawStatus = 'paid';
      else $rawStatus = ''; // empty -> pending
    }
    $lineSta = strtolower((string)$rawStatus);

    // Normalize common variations (some DB values show uppercase or synonyms)
    $normMap = [
      'paid'      => 'paid',
      'completed' => 'completed',
      'complete'  => 'completed',
      'released'  => 'completed',
      'claimed'   => 'completed',
      'approved'  => 'approved',
      'processing'=> 'approved',
      'pending'   => 'pending',
      'reject'    => 'rejected',
      'rejected'  => 'rejected',
      'cancelled' => 'rejected',
      'canceled'  => 'rejected',
      'void'      => 'rejected'
    ];
    if (isset($normMap[$lineSta])) $lineSta = $normMap[$lineSta];
    // if still empty, default to 'pending'
    if ($lineSta === '') $lineSta = 'pending';

    $svcType = $isGym ? 'Gym' : ($r['sr_service_type'] ?: 'Certificate');

    $desc = $isGym
      ? ('Gym Reservation '.($r['rs_ref'] ? '#'.$r['rs_ref'] : ''))
      : ($r['sr_purpose'] ?: $svcType);

    // categories for payment-level summary
    $paidish     = ['paid','completed'];      // any of these means the line is paid/completed
    $approvedish = ['approved'];
    $rejectedish = ['rejected'];

    if (!in_array($lineSta, $paidish, true))        $byPay[$pid]['_all_paid']     = false;
    if (in_array($lineSta, $approvedish, true))    $byPay[$pid]['_any_approved'] = true;
    if (in_array($lineSta, $rejectedish, true))    $byPay[$pid]['_any_rejected'] = true;

    // approver (SR only)
    if (!$isGym && !empty($r['sr_approved_by'])) {
      $an = trim((($r['approver_first'] ?? '').' '.($r['approver_last'] ?? '')));
      if ($an !== '') $byPay[$pid]['_approver_names'][$an] = true;
      if (!empty($r['sr_approved_date'])) $byPay[$pid]['_approver_dates'][] = $r['sr_approved_date'];
    }

    // releaser (SR only) — use claimed_by/claimed_at as releaser indicators when present
    $releasedByName = null;
    $releasedAt     = null;
    if (!$isGym) {
      $rf = trim((($r['releaser_first'] ?? '').' '.($r['releaser_last'] ?? '')));
      if ($rf !== '') $releasedByName = $rf;
      if (!empty($r['sr_claimed_at'])) $releasedAt = $r['sr_claimed_at'];

      if ($releasedByName) $byPay[$pid]['_releaser_names'][$releasedByName] = true;
      if ($releasedAt)     $byPay[$pid]['_releaser_dates'][] = $releasedAt;
    } else {
      // for gym reservations, if rs_paid_at exists we can use that as released_at summary later
      if (!empty($r['rs_paid_at'])) {
        $byPay[$pid]['_releaser_dates'][] = $r['rs_paid_at'];
        // optionally add a releaser name placeholder? leave null unless you record released_by_admin_id
      }
    }

    // include rejected_reason for visibility
    $rejectedReason = $r['sr_rejected_reason'] ?? null;

    $byPay[$pid]['requests'][] = [
      'transaction_no'    => $isGym ? ($r['rs_ref'] ?: null) : ($r['sr_ref'] ?: null),
      'service_type'      => $svcType,
      'description'       => $desc,
      'status'            => $lineSta ?: 'pending',
      'released_by_name'  => $releasedByName,
      'released_at'       => $releasedAt,
      'claimed_by_admin'  => $r['sr_claimed_by'] ?: null,
      'claimed_at'        => $r['sr_claimed_at'] ?: null,
      'rejected_reason'   => $rejectedReason ?: null,
      // raw row can be useful for debugging on client — remove if you don't want it sent
      '_raw'               => [
        'pi_request_type' => $r['pi_request_type'] ?? null,
        'sr_id' => $r['sr_id'] ?? null,
        'rs_id' => $r['rs_id'] ?? null,
        'sr_paid_at' => $r['sr_paid_at'] ?? null,
        'rs_paid_at' => $r['rs_paid_at'] ?? null
      ]
    ];
  }

  // summarize into output array
  $out = [];
  foreach ($byPay as $p) {
    if ($p['_any_rejected'])      { $p['payment_status'] = 'rejected'; }
    elseif ($p['_all_paid'])      { $p['payment_status'] = 'completed'; }
    elseif ($p['_any_approved'])  { $p['payment_status'] = 'approved'; }
    else                          { $p['payment_status'] = 'pending'; }

    $approverNames = array_keys($p['_approver_names']);
    if (count($approverNames) > 1)      $p['approved_by_name'] = 'Multiple';
    elseif (count($approverNames) === 1) $p['approved_by_name'] = $approverNames[0];

    if (!empty($p['_approver_dates'])) {
      rsort($p['_approver_dates']);
      $p['approved_at'] = $p['_approver_dates'][0];
    }

    $releaserNames = array_keys($p['_releaser_names']);
    if (count($releaserNames) > 1)      $p['released_by_summary'] = 'Multiple';
    elseif (count($releaserNames) === 1) $p['released_by_summary'] = $releaserNames[0];

    if (!empty($p['_releaser_dates'])) {
      rsort($p['_releaser_dates']);
      $p['released_at_summary'] = $p['_releaser_dates'][0];
    }

    unset($p['_all_paid'], $p['_any_approved'], $p['_any_rejected'],
          $p['_approver_names'], $p['_approver_dates'],
          $p['_releaser_names'], $p['_releaser_dates']);

    $out[] = $p;
  }

  // --------------------
  // FILTER BY STATUS (simplified)
  // --------------------
  if (!empty($status) && $status !== 'all') {
    $allowedStatus = ['completed','pending','rejected','paid'];
    if (!in_array($status, $allowedStatus, true)) {
      $out = [];
    } else {
      $map = [
        'paid'      => ['completed','paid','released','claimed'],
        'completed' => ['completed','paid','released','claimed'],
        'pending'   => ['pending'],
        'rejected'  => ['rejected','cancelled','canceled','void']
      ];
      $wanted = $map[$status] ?? [$status];
      $wantedLower = array_map('strtolower', $wanted);
      $out = array_values(array_filter($out, function ($p) use ($wantedLower) {
        $ps = strtolower((string)($p['payment_status'] ?? ''));
        return in_array($ps, $wantedLower, true);
      }));
    }
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('get_transactions fatal: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
