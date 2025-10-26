<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
require_once __DIR__ . '/../server/file_urls.php'; 
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

try {
  $pdo = getDBConnection();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Collect POST JSON body
  $raw = file_get_contents('php://input') ?: '';
  $in = json_decode($raw, true);
  if (!is_array($in)) $in = $_POST;

  $first = trim((string)($in['first_name'] ?? ''));
  $last  = trim((string)($in['last_name'] ?? ''));
  $email = trim((string)($in['email'] ?? ''));
  $contact = trim((string)($in['contact_number'] ?? ''));
  $address = trim((string)($in['address'] ?? ''));
  $gender  = strtolower(trim((string)($in['gender'] ?? '')));
  $dob     = trim((string)($in['date_of_birth'] ?? ''));
  $residentType = strtolower(trim((string)($in['resident_type'] ?? 'resident')));

  if ($first === '' || $last === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'First and Last name are required']); exit;
  }

  // optional columns probe
  $hasGender   = colExists($pdo,'users','gender');
  $hasDob      = colExists($pdo,'users','date_of_birth');
  $hasResident = colExists($pdo,'users','resident_type');

  $cols = ['first_name','last_name','email','contact_number','address'];
  $vals = [$first, $last, $email, $contact, $address];

  if ($hasGender)   { $cols[]='gender'; $vals[] = ($gender==='male'||$gender==='female')? $gender : null; }
  if ($hasDob)      { $cols[]='date_of_birth'; $vals[] = ($dob !== '' ? $dob : null); }
  if ($hasResident) { $cols[]='resident_type'; $vals[] = in_array($residentType,['resident','outsider'],true) ? $residentType : 'resident'; }

  $place = implode(',', array_fill(0, count($cols), '?'));
  $sql = "INSERT INTO users (".implode(',', $cols).") VALUES ($place)";
  $st = $pdo->prepare($sql);
  $st->execute($vals);
  $id = (int)$pdo->lastInsertId();

  echo json_encode(['success'=>true,'id'=>$id]);

} catch (Throwable $e) {
  error_log('resident_create: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}

function colExists(PDO $pdo, string $table, string $col): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $q->execute([$table,$col]);
  return (bool)$q->fetchColumn();
}
