<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDBConnection();

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    if ($q === '') {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit;
    }

    $q_norm = strtolower(preg_replace('/[^a-z0-9]/i', '', $q));
    $likeStarts = $q.'%';

    $suggestions = [];

    // --- reference starts-with (service_requests) ---
    $sqlRefSR = "
        SELECT 
            sr.id,
            COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
            'Service' AS source,
            CASE 
                WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
                WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
                WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
                WHEN sr.service_type='business_permit'    THEN 'Business Permit'
                WHEN sr.service_type='gym'                THEN 'Gym Membership'
                ELSE 'Other Service'
            END AS type,
            sr.request_date AS datetime,
            sr.amount,
            CONCAT(u.first_name,' ',u.last_name) AS customer
        FROM service_requests sr
        JOIN users u ON u.id = sr.user_id
        WHERE LOWER(COALESCE(sr.status,'')) = 'approved'
          AND LOWER(REPLACE(REPLACE(COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)),'-',''),' ','')) LIKE CONCAT('', ?, '%')
        ORDER BY sr.request_date DESC
        LIMIT 5
    ";
    $stmt = $db->prepare($sqlRefSR);
    $stmt->execute([$q_norm]);
    $suggestions = array_merge($suggestions, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // --- reference starts-with (reservations) ---
    $sqlRefRS = "
        SELECT
            r.id,
            COALESCE(r.reference_number, CONCAT('RES-', r.id)) AS code,
            'Reservation' AS source,
            'Gym Reservation' AS type,
            COALESCE(r.reservation_date, NOW()) AS datetime,
            r.total_amount AS amount,
            r.resident_name AS customer
        FROM reservations r
        WHERE LOWER(COALESCE(r.status,'')) = 'approved'
          AND LOWER(REPLACE(REPLACE(COALESCE(r.reference_number, CONCAT('RES-', r.id)),'-',''),' ','')) LIKE CONCAT('', ?, '%')
        ORDER BY r.id DESC
        LIMIT 5
    ";
    $stmt = $db->prepare($sqlRefRS);
    $stmt->execute([$q_norm]);
    $suggestions = array_merge($suggestions, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // --- name starts-with (service_requests) ---
    $sqlNameSR = "
        SELECT 
            sr.id,
            COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
            'Service' AS source,
            CASE 
                WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
                WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
                WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
                WHEN sr.service_type='business_permit'    THEN 'Business Permit'
                WHEN sr.service_type='gym'                THEN 'Gym Membership'
                ELSE 'Other Service'
            END AS type,
            sr.request_date AS datetime,
            sr.amount,
            CONCAT(u.first_name,' ',u.last_name) AS customer
        FROM service_requests sr
        JOIN users u ON u.id = sr.user_id
        WHERE LOWER(COALESCE(sr.status,'')) = 'approved'
          AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)
        ORDER BY sr.request_date DESC
        LIMIT 5
    ";
    $stmt = $db->prepare($sqlNameSR);
    $stmt->execute([$likeStarts, $likeStarts, $likeStarts]);
    $suggestions = array_merge($suggestions, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // --- name starts-with (reservations) ---
    $sqlNameRS = "
        SELECT
            r.id,
            COALESCE(r.reference_number, CONCAT('RES-', r.id)) AS code,
            'Reservation' AS source,
            'Gym Reservation' AS type,
            COALESCE(r.reservation_date, NOW()) AS datetime,
            r.total_amount AS amount,
            r.resident_name AS customer
        FROM reservations r
        WHERE LOWER(COALESCE(r.status,'')) = 'approved'
          AND r.resident_name LIKE ?
        ORDER BY r.id DESC
        LIMIT 5
    ";
    $stmt = $db->prepare($sqlNameRS);
    $stmt->execute([$likeStarts]);
    $suggestions = array_merge($suggestions, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // De-duplicate and cap to 10
    $seen = [];
    $out  = [];
    foreach ($suggestions as $s) {
        $key = ($s['source'] ?? '').'#'.($s['id'] ?? '');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $out[] = [
            'id'        => (int)$s['id'],
            'code'      => (string)$s['code'],
            'source'    => (string)$s['source'],
            'type'      => (string)$s['type'],
            'datetime'  => (string)$s['datetime'],
            'amount'    => (float)$s['amount'],
            'customer'  => (string)$s['customer'],
        ];
        if (count($out) >= 10) break;
    }

    echo json_encode(['success' => true, 'suggestions' => $out], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("search_suggest error: ".$e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
