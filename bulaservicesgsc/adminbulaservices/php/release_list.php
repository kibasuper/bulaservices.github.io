<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');
// Prevent any caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

try {
  $db = getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $q_norm = strtolower(preg_replace('/[^a-z0-9]/i','', $q));
  $like = '%'.$q.'%';

  // Consistent label mapping for new & existing service types
  $typeCaseSql = "
    CASE 
      WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
      WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
      WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
      WHEN sr.service_type='business_permit'    THEN 'Business Permit'
      WHEN sr.service_type='cedula'             THEN 'Community Tax Certificate (Cedula)'
      WHEN sr.service_type='ivs'                THEN 'IVS'
      WHEN sr.service_type='low_income'         THEN 'Low Income Certificate'
      WHEN sr.service_type='proof_income'       THEN 'Proof of Income Certificate'
      WHEN sr.service_type='gym'                THEN 'Gym Service'
      ELSE 'Other Service'
    END
  ";

  // Collector that also keeps the canonical request_type for the POS → server roundtrip
  $items = [];
  $push = static function(array $r) use (&$items) {
    $key = 'service:'.$r['id'];
    if (!isset($items[$key])) {
      $items[$key] = [
        'source'       => 'service',
        'id'           => (int)$r['id'],
        'code'         => (string)$r['code'],
        'type'         => (string)$r['type_label'],     // human-readable label
        'request_type' => (string)$r['request_type'],   // canonical enum value
        'paid_at'      => (string)($r['paid_at'] ?? ''),
        'amount'       => (float)($r['amount'] ?? 0),
        'customer'     => (string)$r['customer'],
      ];
    }
  };

  // Common WHERE parts: paid & unclaimed, not gym, not already completed/rejected/etc.
  $wherePaidUnclaimed = "
    sr.paid_at IS NOT NULL
    AND (sr.claimed_at IS NULL OR sr.claimed_at = '0000-00-00 00:00:00')
    AND LOWER(COALESCE(sr.service_type,'')) <> 'gym'
    AND LOWER(COALESCE(sr.status,'')) NOT IN ('completed','rejected','cancelled','canceled','void','settled')
  ";

  // 1) Search by receipt number → pull all related service_requests that are paid & unclaimed
  if ($q !== '') {
    $stmt = $db->prepare("SELECT id FROM payments WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 30");
    $stmt->execute([$like]);
    $payIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($payIds) {
      $in = implode(',', array_fill(0, count($payIds), '?'));
      $sql = "
        SELECT 
          sr.id,
          COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
          {$typeCaseSql} AS type_label,
          sr.service_type AS request_type,
          sr.paid_at,
          sr.amount,
          CONCAT(u.first_name,' ',u.last_name) AS customer
        FROM payment_items pi
        JOIN service_requests sr ON sr.id = pi.request_id
        JOIN users u ON u.id = sr.user_id
        WHERE pi.payment_id IN ($in)
          AND {$wherePaidUnclaimed}
      ";
      $stmt = $db->prepare($sql);
      foreach ($payIds as $i=>$id) $stmt->bindValue($i+1, $id, PDO::PARAM_INT);
      $stmt->execute();
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $push($r);
    }
  }

  // 2) Code/name search (paid & unclaimed, non-gym)
  if ($q !== '') {
    $stmt = $db->prepare("
      SELECT 
        sr.id,
        COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
        {$typeCaseSql} AS type_label,
        sr.service_type AS request_type,
        sr.paid_at,
        sr.amount,
        CONCAT(u.first_name,' ',u.last_name) AS customer
      FROM service_requests sr
      JOIN users u ON u.id = sr.user_id
      WHERE {$wherePaidUnclaimed}
        AND (
          LOWER(REPLACE(REPLACE(COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)),'-',''),' ','')) LIKE CONCAT('%', ?, '%')
          OR u.first_name LIKE ?
          OR u.last_name LIKE ?
          OR CONCAT(u.first_name,' ',u.last_name) LIKE ?
        )
      ORDER BY sr.paid_at DESC
      LIMIT 300
    ");
    $stmt->execute([$q_norm, $like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $push($r);
  }

  // 3) Latest paid & unclaimed docs (non-gym)
  if ($q === '' || empty($items)) {
    $stmt = $db->query("
      SELECT 
        sr.id,
        COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
        {$typeCaseSql} AS type_label,
        sr.service_type AS request_type,
        sr.paid_at,
        sr.amount,
        CONCAT(u.first_name,' ',u.last_name) AS customer
      FROM service_requests sr
      JOIN users u ON u.id = sr.user_id
      WHERE {$wherePaidUnclaimed}
      ORDER BY sr.paid_at DESC
      LIMIT 150
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $push($r);
  }

  $list = array_values($items);
  usort($list, fn($a,$b) => strcmp(($b['paid_at'] ?? ''), ($a['paid_at'] ?? '')));

  echo json_encode(['success'=>true,'items'=>$list], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('release_list(docs-only): '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
