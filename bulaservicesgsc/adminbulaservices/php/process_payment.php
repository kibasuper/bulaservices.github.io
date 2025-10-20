<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

try {
  $db = getDBConnection();

  $raw = file_get_contents('php://input');
  $input = $raw ? json_decode($raw, true) : null;
  if (!$input || !is_array($input)) throw new Exception('Invalid payload');

  $receiptNumber = trim((string)($input['receiptNumber'] ?? ''));
  $cashGiven     = (float)($input['cashGiven'] ?? 0);
  $totalAmount   = (float)($input['totalAmount'] ?? 0);
  $items         = $input['items'] ?? [];

  if ($receiptNumber === '' || $totalAmount <= 0 || !is_array($items) || count($items) === 0) {
    throw new Exception('Missing required payment data');
  }

  // Global customer fields (may help resolve user)
  $globalEmail   = trim((string)($input['email'] ?? ''));
  $globalName    = trim((string)($input['customerName'] ?? ''));
  $globalContact = trim((string)($input['customerContact'] ?? ''));

  $splitName = function (string $full): array {
    $full = trim($full);
    if ($full === '') return ['Walk-in', 'Customer'];
    $parts = preg_split('/\s+/', $full, 2);
    return [ $parts[0] ?? $full, $parts[1] ?? '' ];
  };

  // Resolve user id for this cart
  $resolveUserId = function(PDO $db, array $item) use ($splitName, $globalEmail, $globalName, $globalContact): int {
    $id  = isset($item['id'])   ? (int)$item['id']   : 0;
    $ref = isset($item['code']) ? (string)$item['code'] : '';

    // 1) Direct from service_requests (id or reference)
    if ($id > 0) {
      $stmt = $db->prepare("SELECT user_id FROM service_requests WHERE id = ? LIMIT 1");
      $stmt->execute([$id]);
      $uid = $stmt->fetchColumn();
      if ($uid) return (int)$uid;
    }
    if ($ref !== '') {
      $stmt = $db->prepare("SELECT user_id FROM service_requests WHERE reference_number = ? LIMIT 1");
      $stmt->execute([$ref]);
      $uid = $stmt->fetchColumn();
      if ($uid) return (int)$uid;
    }

    // 2) Pull name/contact from reservations (id or ref)
    $res = null;
    if ($id > 0) {
      $stmt = $db->prepare("SELECT resident_name, contact_number FROM reservations WHERE id = ? LIMIT 1");
      $stmt->execute([$id]);
      $res = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$res && $ref !== '') {
      $stmt = $db->prepare("SELECT resident_name, contact_number FROM reservations WHERE reference_number = ? LIMIT 1");
      $stmt->execute([$ref]);
      $res = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $residentName  = trim((string)($res['resident_name'] ?? ''));
    $contactNumber = trim((string)($res['contact_number'] ?? ''));

    // Prefer global overrides if locals are empty
    if ($globalName !== '')    $residentName  = $residentName  !== '' ? $residentName  : $globalName;
    if ($globalContact !== '') $contactNumber = $contactNumber !== '' ? $contactNumber : $globalContact;

    // 3) Match by email
    if ($globalEmail !== '') {
      $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$globalEmail]);
      $u = $stmt->fetchColumn();
      if ($u) return (int)$u;
    }

    // 4) Match by contact
    if ($contactNumber !== '') {
      $stmt = $db->prepare("SELECT id FROM users WHERE contact_number = ? LIMIT 1");
      $stmt->execute([$contactNumber]);
      $u = $stmt->fetchColumn();
      if ($u) return (int)$u;
    }

    // 5) Create new user
    if ($residentName === '') $residentName = 'Walk-in Customer';
    [$first, $last] = $splitName($residentName);
    $insEmail   = $globalEmail !== '' ? $globalEmail : '';
    $insContact = $contactNumber !== '' ? $contactNumber : ($globalContact !== '' ? $globalContact : '');
    if ($insContact === null) $insContact = '';
    $insAddr    = '';

    $stmt = $db->prepare("
      INSERT INTO users (first_name, last_name, email, contact_number, address)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$first, $last, $insEmail, $insContact, $insAddr]);

    return (int)$db->lastInsertId();
  };

  // Resolve a single resident (all items must map to same user)
  $userIds = [];
  foreach ($items as $it) $userIds[] = $resolveUserId($db, $it);
  $userIds = array_values(array_unique(array_map('intval', $userIds)));
  if (count($userIds) === 0) throw new Exception('Failed to resolve user for payment');
  if (count($userIds) > 1) {
    echo json_encode(['success'=>false,'message'=>'This cart has items from different residents. Please process separate receipts.']);
    exit;
  }

  $userId    = (int)$userIds[0];
  $cashierId = (int)$_SESSION['admin_id'];
  $changeAmt = max(0, $cashGiven - $totalAmount);

  $db->beginTransaction();

  // payments insert
  $stmt = $db->prepare("INSERT INTO payments (receipt_number, user_id, cashier_id, total_amount, cash_given, change_amount) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->execute([$receiptNumber, $userId, $cashierId, $totalAmount, $cashGiven, $changeAmt]);
  $paymentId = (int)$db->lastInsertId();

  $stmtItem = $db->prepare("INSERT INTO payment_items (payment_id, request_type, request_id, amount) VALUES (?, ?, ?, ?)");

  // mark SERVICE REQUESTS as PAID
  $stmtPaySR = $db->prepare("
    UPDATE service_requests
       SET status = 'paid',
           paid_at = NOW(),
           processed_date = COALESCE(processed_date, NOW())
     WHERE id = ?
  ");

  // mark RESERVATIONS as PAID
  $stmtPayRS = $db->prepare("
    UPDATE reservations
       SET status = 'paid',
           paid_at = NOW()
     WHERE id = ?
  ");

  foreach ($items as $it) {
    $id   = isset($it['id'])   ? (int)$it['id']   : 0;
    $ref  = isset($it['code']) ? (string)$it['code'] : '';
    $type = strtolower(str_replace(' ', '_', (string)($it['type'] ?? '')));
    $amt  = (float)($it['amount'] ?? 0);

    // Decide origin: reservations if code starts with RES- or type says gym_reservation; else service_requests
    $isReservation = (stripos($ref, 'RES-') === 0) || ($type === 'gym_reservation');

    if ($isReservation) {
      // ---- RESERVATIONS path (do not probe SR by id) ----
      if ($id <= 0 && $ref !== '') {
        $stmt = $db->prepare("SELECT id FROM reservations WHERE reference_number = ? LIMIT 1");
        $stmt->execute([$ref]);
        $rid = $stmt->fetchColumn();
        if ($rid) $id = (int)$rid;
      }
      if ($id <= 0) throw new Exception('Unable to resolve reservation id');

      $stmtItem->execute([$paymentId, $type, $id, $amt]);
      $stmtPayRS->execute([$id]); // -> paid

    } else {
      // ---- SERVICE REQUESTS path (do not probe reservations by id) ----
      if ($id > 0) {
        $stmt = $db->prepare("SELECT 1 FROM service_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) $id = 0;
      }
      if ($id <= 0 && $ref !== '') {
        $stmt = $db->prepare("SELECT id FROM service_requests WHERE reference_number = ? LIMIT 1");
        $stmt->execute([$ref]);
        $sid = $stmt->fetchColumn();
        if ($sid) $id = (int)$sid;
      }
      if ($id <= 0) throw new Exception('Unable to resolve service request id');

      $stmtItem->execute([$paymentId, $type, $id, $amt]);
      $stmtPaySR->execute([$id]); // -> paid
    }
  }

  $db->commit();

  echo json_encode([
    'success'       => true,
    'paymentId'     => $paymentId,
    'change'        => $changeAmt,
    'receiptNumber' => $receiptNumber
  ]);

} catch (Throwable $e) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  error_log("Payment process error: ".$e->getMessage());
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
