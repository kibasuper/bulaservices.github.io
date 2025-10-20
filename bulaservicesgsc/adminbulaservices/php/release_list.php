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
  $db = isset($db) && $db instanceof PDO ? $db : getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $q_norm = strtolower(preg_replace('/[^a-z0-9]/i','', $q));
  $like = '%'.$q.'%';

  $items = [];
  $push = static function(array $r) use (&$items) {
    $key = 'service:'.$r['id'];
    if (!isset($items[$key])) {
      $items[$key] = [
        'source'   => 'service',
        'id'       => (int)$r['id'],
        'code'     => (string)$r['code'],
        'type'     => (string)$r['type'],
        'paid_at'  => (string)($r['paid_at'] ?? ''),
        'amount'   => (float)($r['amount'] ?? 0),
        'customer' => (string)$r['customer'],
      ];
    }
  };

  // 1) If searching by receipt, map to non-gym, paid & unclaimed service_requests
  if ($q !== '') {
    $stmt = $db->prepare("SELECT id FROM payments WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 30");
    $stmt->execute([$like]);
    $payIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($payIds) {
      $in = implode(',', array_fill(0, count($payIds), '?'));
      $sql = "
        SELECT sr.id,
               COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
               CASE 
                 WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
                 WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
                 WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
                 WHEN sr.service_type='business_permit'    THEN 'Business Permit'
                 ELSE 'Other Service'
               END AS type,
               sr.paid_at,
               sr.amount,
               CONCAT(u.first_name,' ',u.last_name) AS customer
        FROM payment_items pi
        JOIN service_requests sr ON sr.id = pi.request_id
        JOIN users u ON u.id = sr.user_id
        WHERE pi.payment_id IN ($in)
          AND LOWER(sr.status) = 'paid'
          AND (sr.claimed_at IS NULL OR sr.claimed_at = '0000-00-00 00:00:00')
          AND LOWER(COALESCE(sr.service_type,'')) <> 'gym'
      ";
      $stmt = $db->prepare($sql);
      foreach ($payIds as $i=>$id) $stmt->bindValue($i+1, $id, PDO::PARAM_INT);
      $stmt->execute();
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $push($r);
    }
  }

  // 2) Code/name search (non-gym, paid & unclaimed)
  if ($q !== '') {
    $stmt = $db->prepare("
      SELECT sr.id,
             COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
             CASE 
               WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
               WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
               WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
               WHEN sr.service_type='business_permit'    THEN 'Business Permit'
               ELSE 'Other Service'
             END AS type,
             sr.paid_at,
             sr.amount,
             CONCAT(u.first_name,' ',u.last_name) AS customer
      FROM service_requests sr
      JOIN users u ON u.id = sr.user_id
      WHERE LOWER(sr.status) = 'paid'
        AND (sr.claimed_at IS NULL OR sr.claimed_at = '0000-00-00 00:00:00')
        AND LOWER(COALESCE(sr.service_type,'')) <> 'gym'
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
      SELECT sr.id,
             COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
             CASE 
               WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
               WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
               WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
               WHEN sr.service_type='business_permit'    THEN 'Business Permit'
               ELSE 'Other Service'
             END AS type,
             sr.paid_at,
             sr.amount,
             CONCAT(u.first_name,' ',u.last_name) AS customer
      FROM service_requests sr
      JOIN users u ON u.id = sr.user_id
      WHERE LOWER(sr.status) = 'paid'
        AND (sr.claimed_at IS NULL OR sr.claimed_at = '0000-00-00 00:00:00')
        AND LOWER(COALESCE(sr.service_type,'')) <> 'gym'
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
