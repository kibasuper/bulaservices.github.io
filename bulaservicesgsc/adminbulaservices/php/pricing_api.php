<?php
declare(strict_types=1);

/**
 * adminbulaservices/php/pricing_api.php
 * Admin-only API for managing certificate prices and gym rates.
 */
require_once __DIR__ . '/../server/config.php'; // sessions + global handlers + DB + auth

// Force JSON responses here without touching global config
header('Content-Type: application/json');

// Local API-oriented error/exception handlers (override config's HTML ones for this file only)
set_exception_handler(function (Throwable $e) {
  error_log('[pricing_api] Exception: ' . $e->getMessage());
  if (!headers_sent()) http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
  exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
  $msg = "[pricing_api] PHP Error [$severity]: $message in $file:$line";
  error_log($msg);
  if (!headers_sent()) http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
  exit;
});

function respond(array $payload, int $code = 200): void {
  if (!headers_sent()) http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function fail(string $msg, int $code = 400): void { respond(['ok' => false, 'error' => $msg], $code); }
function require_admin(): void {
  if (empty($_SESSION['admin_id'])) fail('Unauthorized', 401);
}
function check_csrf(?string $token): void {
  if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
    fail('Invalid CSRF token', 403);
  }
}
function json_input(): array {
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = $_POST ?: [];
  return $data;
}

require_admin();

// DB connection (errors caught by local exception handler)
$db = getDBConnection();

// Ensure tables exist (idempotent)
$db->exec("
  CREATE TABLE IF NOT EXISTS certificate_pricing (
    type_code  VARCHAR(50) NOT NULL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$db->exec("
  CREATE TABLE IF NOT EXISTS gym_pricing (
    id            TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    morning_rate  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    evening_rate  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ===================== LOAD (GET) ===================== */
if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $certs = $db->query("SELECT type_code, name, price FROM certificate_pricing ORDER BY name ASC")->fetchAll();
  $gym   = $db->query("SELECT morning_rate, evening_rate FROM gym_pricing WHERE id = 1 LIMIT 1")->fetch() ?: null;

  respond(['ok' => true, 'data' => [
    'certificates' => $certs,
    'gym'          => $gym
  ]]);
}

/* ===================== SAVE CERTS (POST) ===================== */
if ($action === 'save_certs' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_input();
  check_csrf($in['csrf'] ?? null);

  $certs = $in['certs'] ?? null;
  if (!is_array($certs)) fail('Invalid payload');

  // Validate rows
  $seen = [];
  foreach ($certs as $idx => $c) {
    $code  = strtolower(trim((string)($c['type_code'] ?? '')));
    $name  = trim((string)($c['name'] ?? ''));
    $price = $c['price'] ?? null;

    if (!preg_match('/^[a-z0-9_-]{2,50}$/', $code)) {
      fail("Invalid code at row ".($idx+1).". Use lowercase letters, numbers, dash/underscore (2–50 chars).");
    }
    if (isset($seen[$code])) fail("Duplicate code: {$code}");
    $seen[$code] = true;

    if ($name === '' || mb_strlen($name) < 2)       fail("Name required for code {$code}");
    if (!is_numeric($price) || (float)$price < 0)   fail("Invalid price for code {$code}");

    // normalize
    $certs[$idx]['type_code'] = $code;
    $certs[$idx]['name']      = $name;
    $certs[$idx]['price']     = number_format((float)$price, 2, '.', '');
  }

  // Upsert + remove missing (mirror table with client payload)
  $db->beginTransaction();
  try {
    $curr   = $db->query("SELECT type_code FROM certificate_pricing")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $keep   = [];

    $up = $db->prepare("
      INSERT INTO certificate_pricing (type_code, name, price)
      VALUES (:code, :name, :price)
      ON DUPLICATE KEY UPDATE
        name  = VALUES(name),
        price = VALUES(price)
    ");

    foreach ($certs as $c) {
      $up->execute([
        ':code'  => $c['type_code'],
        ':name'  => $c['name'],
        ':price' => $c['price'],
      ]);
      $keep[$c['type_code']] = true;
    }

    if ($curr) {
      $toDelete = array_values(array_diff($curr, array_keys($keep)));
      if ($toDelete) {
        $ph  = implode(',', array_fill(0, count($toDelete), '?'));
        $del = $db->prepare("DELETE FROM certificate_pricing WHERE type_code IN ($ph)");
        $del->execute($toDelete);
      }
    }

    $db->commit();
  } catch (Throwable $e) {
    $db->rollBack();
    error_log('[pricing_api] save_certs tx error: '.$e->getMessage());
    fail('Database error', 500);
  }

  respond(['ok' => true, 'data' => ['saved' => true]]);
}

/* ===================== SAVE GYM (POST) ===================== */
if ($action === 'save_gym' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_input();
  check_csrf($in['csrf'] ?? null);

  $morning = $in['morning_rate'] ?? null;
  $evening = $in['evening_rate'] ?? null;

  if (!is_numeric($morning) || (float)$morning < 0) fail('Invalid morning rate');
  if (!is_numeric($evening) || (float)$evening < 0) fail('Invalid evening rate');

  $m = number_format((float)$morning, 2, '.', '');
  $e = number_format((float)$evening, 2, '.', '');

  $stmt = $db->prepare("
    INSERT INTO gym_pricing (id, morning_rate, evening_rate)
    VALUES (1, :m, :e)
    ON DUPLICATE KEY UPDATE
      morning_rate = VALUES(morning_rate),
      evening_rate = VALUES(evening_rate)
  ");
  if (!$stmt->execute([':m' => $m, ':e' => $e])) {
    fail('Database error', 500);
  }

  respond(['ok' => true, 'data' => ['saved' => true]]);
}

/* Fallback */
fail('Invalid action', 400);
