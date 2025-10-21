<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

try {
  $pdo = getDBConnection();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $raw = file_get_contents('php://input') ?: '';
  $in = json_decode($raw, true) ?: [];
  $id = (int)($in['id'] ?? 0);
  $action = strtolower(trim((string)($in['action'] ?? '')));

  if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }
  if (!in_array($action, ['suspend','restore'], true)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid action']); exit; }

  // Optional columns
  $hasStatus   = colExists($pdo,'users','status');
  $hasAccStat  = colExists($pdo,'users','account_status');
  $hasIsActive = colExists($pdo,'users','is_active');
  $hasSusp     = colExists($pdo,'users','is_suspended');

  if (!$hasStatus && !$hasAccStat && !$hasIsActive && !$hasSusp) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'No status columns exist']); exit;
  }

  if ($action === 'suspend') {
    if ($hasStatus)   $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$id]);
    if ($hasAccStat)  $pdo->prepare("UPDATE users SET account_status='suspended' WHERE id=?")->execute([$id]);
    if ($hasIsActive) $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
    if ($hasSusp)     $pdo->prepare("UPDATE users SET is_suspended=1 WHERE id=?")->execute([$id]);
  } else { // restore
    if ($hasStatus)   $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
    if ($hasAccStat)  $pdo->prepare("UPDATE users SET account_status='active' WHERE id=?")->execute([$id]);
    if ($hasIsActive) $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$id]);
    if ($hasSusp)     $pdo->prepare("UPDATE users SET is_suspended=0 WHERE id=?")->execute([$id]);
  }

  echo json_encode(['success'=>true]);

} catch (Throwable $e) {
  error_log('resident_status: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}

function colExists(PDO $pdo, string $table, string $col): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $q->execute([$table,$col]);
  return (bool)$q->fetchColumn();
}
