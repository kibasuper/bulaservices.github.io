<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

$DEBUG = isset($_GET['debug']) && $_GET['debug'] === '1';

if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Unauthorized (no admin session)']);
  exit;
}

try {
  $db = isset($db) && $db instanceof PDO ? $db : getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $raw = file_get_contents('php://input') ?: '';
  $input = json_decode($raw, true);
  if (!is_array($input)) {
    $input = $_POST ?: [];
    if (isset($input['items']) && is_string($input['items'])) {
      $dec = json_decode($input['items'], true);
      if (json_last_error() === JSON_ERROR_NONE) $input['items'] = $dec;
    }
  }

  if (empty($input['items']) || !is_array($input['items'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid payload: items[] required']);
    exit;
  }

  $adminId = (int)$_SESSION['admin_id'];
  $nowSql  = date('Y-m-d H:i:s');

  // --- Fetch the display name of the current admin (so we return a name, not just an ID)
  $stmtMe = $db->prepare("SELECT first_name, last_name, username FROM admins WHERE admin_id = ? LIMIT 1");
  $stmtMe->execute([$adminId]);
  $me = $stmtMe->fetch(PDO::FETCH_ASSOC) ?: [];
  $meName = trim(
    (($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''))
  );
  if ($meName === '') { $meName = (string)($me['username'] ?? ('Admin #'.$adminId)); }

  // Look up by id/ref
  $stmtGetById = $db->prepare("
    SELECT id, reference_number, service_type, status, claimed_at
    FROM service_requests
    WHERE id = ?
    LIMIT 1
  ");
  $stmtGetByRef = $db->prepare("
    SELECT id, reference_number, service_type, status, claimed_at
    FROM service_requests
    WHERE reference_number = ?
    LIMIT 1
  ");

  // Use distinct placeholders to avoid HY093
  $stmtMark = $db->prepare("
    UPDATE service_requests
       SET status = 'completed',
           claimed_at = :claimed_at,
           claimed_by = :claimed_by,
           claim_notes = CASE
                           WHEN :notes1 <> '' THEN
                             CONCAT(
                               COALESCE(claim_notes,''),
                               CASE WHEN claim_notes IS NULL OR claim_notes='' THEN '' ELSE '\n' END,
                               :notes2
                             )
                           ELSE claim_notes
                         END
     WHERE id = :id
       AND LOWER(COALESCE(service_type,'')) <> 'gym'
       AND LOWER(COALESCE(status,'')) = 'paid'
       AND (claimed_at IS NULL OR claimed_at = '0000-00-00 00:00:00')
     LIMIT 1
  ");

  $results = [];
  $updatedCount = 0;

  foreach ($input['items'] as $ix => $item) {
    $result = ['index'=>$ix, 'ok'=>false];

    $source = strtolower((string)($item['source'] ?? 'service'));
    $id     = isset($item['id']) ? (int)$item['id'] : 0;
    $ref    = trim((string)($item['ref'] ?? $item['reference'] ?? $item['code'] ?? ''));
    $notes  = trim((string)($item['notes'] ?? ''));

    if ($source !== 'service') {
      $result['error'] = "Unsupported source '{$source}' (only 'service' allowed).";
      $results[] = $result; continue;
    }

    // fetch row (by id or ref)
    $row = null;
    if ($id > 0) {
      $stmtGetById->execute([$id]);
      $row = $stmtGetById->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif ($ref !== '') {
      $stmtGetByRef->execute([$ref]);
      $row = $stmtGetByRef->fetch(PDO::FETCH_ASSOC) ?: null;
    } else {
      $result['error'] = 'Missing id/ref for item.';
      $results[] = $result; continue;
    }

    if (!$row) {
      $result['error'] = 'Request not found.';
      $results[] = $result; continue;
    }

    $rid      = (int)$row['id'];
    $stype    = strtolower((string)($row['service_type'] ?? ''));
    $status   = strtolower((string)($row['status'] ?? ''));
    $claimed  = $row['claimed_at'] ?? null;

    if ($stype === 'gym') {
      $result['error'] = "Gym reservations are not claimed here.";
      $results[] = $result; continue;
    }
    if ($status !== 'paid') {
      $result['error'] = "Not claimable (status is '{$status}', expected 'paid').";
      $results[] = $result; continue;
    }
    if (!empty($claimed) && $claimed !== '0000-00-00 00:00:00') {
      $result['error'] = 'Already claimed.';
      $results[] = $result; continue;
    }

    // mark claimed
    $stmtMark->execute([
      ':claimed_at' => $nowSql,
      ':claimed_by' => $adminId,
      ':notes1'     => $notes,
      ':notes2'     => $notes,
      ':id'         => $rid,
    ]);

    if ($stmtMark->rowCount() > 0) {
      $result['ok']               = true;
      $result['released_by_id']   = $adminId;       // keep id if the UI needs it
      $result['released_by_name'] = $meName;        // <-- NAME for display
      $result['released_at']      = $nowSql;
      $updatedCount++;
    } else {
      $result['error'] = 'Update failed (row not affected).';
    }

    $results[] = $result;
  }

  $payload = [
    'success'          => true,
    'updated'          => $updatedCount,
    'released_by_id'   => $adminId,
    'released_by_name' => $meName,   // <-- include at top-level too
    'results'          => $results
  ];
  if ($DEBUG) {
    $payload['debug'] = [
      'admin_id' => $adminId,
      'admin_name' => $meName,
      'received' => $input,
      'now' => $nowSql
    ];
  }
  echo json_encode($payload);

} catch (Throwable $e) {
  error_log('release_claim error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
