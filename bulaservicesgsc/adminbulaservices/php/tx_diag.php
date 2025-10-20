<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json; charset=utf-8');

$out = [
  'ok' => false,
  'db' => null,
  'tables' => [],
  'samples' => [],
  'notes' => [
    'This is a read-only diagnostic to help wire up Transactions.',
    'If counts are 0, your cashier writes to a different DB/schema.',
    'If counts > 0 but joins fail, item->request_id may not match service_requests/reservations ids.'
  ]
];

try {
  $db = isset($db) && $db instanceof PDO ? $db : getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $out['db'] = $db->query('SELECT DATABASE()')->fetchColumn();

  // helper
  $count = function(string $table) use ($db) {
    try {
      return (int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    } catch (Throwable $e) {
      return "ERR: " . $e->getMessage();
    }
  };

  $out['tables'] = [
    'payments'        => $count('payments'),
    'payment_items'   => $count('payment_items'),
    'service_requests'=> $count('service_requests'),
    'reservations'    => $count('reservations'),
    'users'           => $count('users'),
  ];

  // sample rows (latest 3)
  $samples = [];

  try {
    $samples['payments'] = $db->query("
      SELECT id, receipt_number, user_id, cashier_id, total_amount, created_at
      FROM payments ORDER BY id DESC LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $samples['payments'] = "ERR: ".$e->getMessage(); }

  try {
    $samples['payment_items'] = $db->query("
      SELECT payment_id, request_id, amount
      FROM payment_items ORDER BY payment_id DESC, request_id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $samples['payment_items'] = "ERR: ".$e->getMessage(); }

  try {
    $samples['service_requests'] = $db->query("
      SELECT id, reference_number, service_type, status, paid_at, claimed_at
      FROM service_requests ORDER BY id DESC LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $samples['service_requests'] = "ERR: ".$e->getMessage(); }

  try {
    $samples['reservations'] = $db->query("
      SELECT id, reference_number, status, paid_at, reservation_date
      FROM reservations ORDER BY id DESC LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $samples['reservations'] = "ERR: ".$e->getMessage(); }

  $out['samples'] = $samples;
  $out['ok'] = true;

} catch (Throwable $e) {
  $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
