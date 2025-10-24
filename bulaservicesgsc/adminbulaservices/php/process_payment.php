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
  if ($db instanceof PDO) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

  $raw   = file_get_contents('php://input');
  $input = $raw ? json_decode($raw, true) : null;
  if (!$input || !is_array($input)) throw new Exception('Invalid payload');

  $receiptNumber = trim((string)($input['receiptNumber'] ?? ''));
  $cashGiven     = (float)($input['cashGiven'] ?? 0);
  $totalAmount   = (float)($input['totalAmount'] ?? 0);
  $items         = $input['items'] ?? [];

  if ($receiptNumber === '' || $totalAmount <= 0 || !is_array($items) || count($items) === 0) {
    throw new Exception('Missing required payment data');
  }

  // Optional customer hints from POS
  $globalEmail   = trim((string)($input['email'] ?? ''));
  $globalName    = trim((string)($input['customerName'] ?? ''));
  $globalContact = trim((string)($input['customerContact'] ?? ''));

  $splitName = function (string $full): array {
    $full = trim($full);
    if ($full === '') return ['Walk-in', 'Customer'];
    $parts = preg_split('/\s+/', $full, 2);
    return [ $parts[0] ?? $full, $parts[1] ?? '' ];
  };

  // Resolve one user id for the whole payment
  $resolveUserId = function(PDO $db, array $item) use ($splitName, $globalEmail, $globalName, $globalContact): int {
    $id  = isset($item['id'])   ? (int)$item['id']   : 0;
    $ref = isset($item['code']) ? (string)$item['code'] : '';

    // Prefer SR linkage
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

    // Fallback: reservation (for name/contact only)
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

    if ($globalName !== '')    $residentName  = $residentName  !== '' ? $residentName  : $globalName;
    if ($globalContact !== '') $contactNumber = $contactNumber !== '' ? $contactNumber : $globalContact;

    // Email lookup
    if ($globalEmail !== '') {
      $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$globalEmail]);
      $u = $stmt->fetchColumn();
      if ($u) return (int)$u;
    }

    // Contact lookup
    if ($contactNumber !== '') {
      $stmt = $db->prepare("SELECT id FROM users WHERE contact_number = ? LIMIT 1");
      $stmt->execute([$contactNumber]);
      $u = $stmt->fetchColumn();
      if ($u) return (int)$u;
    }

    // Create lightweight user
    if ($residentName === '') $residentName = 'Walk-in Customer';
    [$first, $last] = $splitName($residentName);
    $stmt = $db->prepare("
      INSERT INTO users (first_name, last_name, email, contact_number, address)
      VALUES (?, ?, ?, ?, '')
    ");
    $stmt->execute([$first, $last, $globalEmail, $contactNumber]);

    return (int)$db->lastInsertId();
  };

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

  // Begin TX
  $db->beginTransaction();

  // Create payment header
  $stmt = $db->prepare("
    INSERT INTO payments (receipt_number, user_id, cashier_id, total_amount, cash_given, change_amount)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([$receiptNumber, $userId, $cashierId, $totalAmount, $cashGiven, $changeAmt]);
  $paymentId = (int)$db->lastInsertId();

  // Stmts
  $stmtItem  = $db->prepare("
    INSERT INTO payment_items (payment_id, request_type, request_id, amount)
    VALUES (?, ?, ?, ?)
  ");

  $stmtPaySR = $db->prepare("
    UPDATE service_requests
       SET paid_at = NOW(),
           processed_date = COALESCE(processed_date, NOW())
     WHERE id = ?
  ");

  $stmtPayRS = $db->prepare("
    UPDATE reservations
       SET status = 'paid',
           paid_at = NOW()
     WHERE id = ?
  ");

  $VALID_SR_TYPES = [
    'barangay_clearance','business_permit','indigency','residency',
    'cedula','ivs','low_income','proof_income','gym','other'
  ];

  $labelToKey = [
    'Barangay Clearance'          => 'barangay_clearance',
    'Certificate of Indigency'    => 'indigency',
    'Certificate of Residency'    => 'residency',
    'Business Permit'             => 'business_permit',
    'Community Tax Certificate'   => 'cedula',
    'Community Tax Certificate (Cedula)' => 'cedula',
    'IVS'                         => 'ivs',
    'Low Income Certificate'      => 'low_income',
    'Proof of Income Certificate' => 'proof_income',
    'Gym Reservation'             => 'gym_reservation',
    'Gym Service'                 => 'gym',
    'Other Service'               => 'other',
  ];

  foreach ($items as $it) {
    $id   = isset($it['id'])   ? (int)$it['id']   : 0;
    $ref  = isset($it['code']) ? (string)$it['code'] : '';
    $amt  = (float)($it['amount'] ?? 0);

    $typeKey = strtolower(trim((string)($it['request_type'] ?? '')));
    if ($typeKey === '') {
      $label = trim((string)($it['type'] ?? ''));
      $typeKey = strtolower($labelToKey[$label] ?? 'other');
    }

    // Reservation ONLY if typeKey explicitly says so
    $isReservation = ($typeKey === 'gym_reservation');

    if ($isReservation) {
      if ($id <= 0 && $ref !== '') {
        $stmt = $db->prepare("SELECT id FROM reservations WHERE reference_number = ? LIMIT 1");
        $stmt->execute([$ref]);
        $rid = $stmt->fetchColumn();
        if ($rid) $id = (int)$rid;
      }
      if ($id <= 0) throw new Exception('Unable to resolve reservation id');

      $stmtItem->execute([$paymentId, 'gym_reservation', $id, $amt]);
      $stmtPayRS->execute([$id]);

    } else {
      // Service request path: resolve id, trust DB service_type
      $dbType = null;

      if ($id > 0) {
        $chk = $db->prepare("SELECT service_type FROM service_requests WHERE id = ? LIMIT 1");
        $chk->execute([$id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if ($row) $dbType = strtolower(trim((string)$row['service_type']));
        else $id = 0;
      }

      if ($id <= 0 && $ref !== '') {
        $stmt = $db->prepare("SELECT id, service_type FROM service_requests WHERE reference_number = ? LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $id = (int)$row['id'];
          $dbType = strtolower(trim((string)$row['service_type']));
        }
      }

      if ($id <= 0) throw new Exception('Unable to resolve service request id');

      if ($dbType !== null && in_array($dbType, $VALID_SR_TYPES, true)) {
        $typeKey = $dbType;
      }
      if (!in_array($typeKey, $VALID_SR_TYPES, true)) {
        $typeKey = 'other';
      }

      $stmtItem->execute([$paymentId, $typeKey, $id, $amt]);
      $stmtPaySR->execute([$id]);
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
