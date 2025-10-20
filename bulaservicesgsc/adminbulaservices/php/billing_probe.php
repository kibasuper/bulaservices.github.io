<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

$out = ['ok'=>false];

try {
  if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'err'=>'unauthorized']); exit;
  }
  $db = getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // recent service_requests
  $sr = $db->query("
      SELECT id, reference_number, service_type, status,
             COALESCE(processed_date, approved_date, request_date) AS dt,
             amount
      FROM service_requests
      ORDER BY id DESC
      LIMIT 30
  ")->fetchAll(PDO::FETCH_ASSOC);

  // recent reservations
  $res = $db->query("
      SELECT id, reference_number, resident_name, status,
             COALESCE(created_at, reservation_date, NOW()) AS dt,
             total_amount
      FROM reservations
      ORDER BY id DESC
      LIMIT 30
  ")->fetchAll(PDO::FETCH_ASSOC);

  // normalize a snapshot for quick view
  $norm = function($rows) {
    $o = [];
    foreach ($rows as $r) {
      $sraw = (string)($r['status'] ?? '');
      $s    = strtolower(trim($sraw));
      $o[] = [
        'id' => (int)$r['id'],
        'ref' => (string)($r['reference_number'] ?? ''),
        'status_raw' => $sraw,
        'status_norm'=> $s,
        'dt' => (string)($r['dt'] ?? ''),
        'amount' => isset($r['amount']) ? (float)$r['amount'] : (isset($r['total_amount']) ? (float)$r['total_amount'] : null),
        'service_type' => $r['service_type'] ?? null,
        'who' => $r['resident_name'] ?? null
      ];
    }
    return $o;
  };

  $out = [
    'ok' => true,
    'admin_id' => (int)$_SESSION['admin_id'],
    'service_requests' => $norm($sr),
    'reservations'     => $norm($res),
  ];
} catch (Throwable $e) {
  $out = ['ok'=>false,'err'=>$e->getMessage()];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
