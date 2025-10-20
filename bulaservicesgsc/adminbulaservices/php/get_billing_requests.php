<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

$DEBUG = isset($_GET['debug']) && $_GET['debug'] === '1';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    date_default_timezone_set('Asia/Manila');
    $db = getDBConnection();
    if ($db instanceof PDO) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $q_norm = strtolower(preg_replace('/[^a-z0-9]/i', '', $q));
    $likeContains = '%' . $q . '%';
    $hasQ = ($q === '' ? 0 : 1);

    $out = [];

    // ===== Inclusion rules (now also require paid_at IS NULL) =====
    $srInclusion = "
      (
        LOWER(TRIM(COALESCE(sr.status,''))) IN ('approved','processing')
        OR sr.approved_date IS NOT NULL
      )
      AND sr.paid_at IS NULL
      AND LOWER(TRIM(COALESCE(sr.status,''))) NOT IN ('paid','completed','rejected','cancelled','canceled','void','settled')
    ";

    $rsInclusion = "
      LOWER(TRIM(COALESCE(r.status,''))) IN ('approved','processing')
      AND r.paid_at IS NULL
      AND LOWER(TRIM(COALESCE(r.status,''))) NOT IN ('paid','completed','rejected','cancelled','canceled','void','settled')
    ";

    // ---------- SERVICE REQUESTS ----------
    $sqlSR = "
        SELECT 
            sr.id,
            COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)) AS code,
            CASE 
                WHEN sr.service_type='barangay_clearance' THEN 'Barangay Clearance'
                WHEN sr.service_type='indigency'          THEN 'Certificate of Indigency'
                WHEN sr.service_type='residency'          THEN 'Certificate of Residency'
                WHEN sr.service_type='business_permit'    THEN 'Business Permit'
                WHEN sr.service_type='gym'                THEN 'Gym Membership'
                ELSE 'Other Service'
            END AS type,
            COALESCE(sr.processed_date, sr.approved_date, sr.request_date) AS datetime,
            sr.status,
            sr.amount,
            u.first_name, u.last_name, u.contact_number, u.address
        FROM service_requests sr
        LEFT JOIN users u ON u.id = sr.user_id
        WHERE {$srInclusion}
          AND (
                :hasq = 0 OR
                LOWER(REPLACE(REPLACE(COALESCE(sr.reference_number, CONCAT('REQ-', sr.id)),'-',''),' ','')) LIKE CONCAT('%', :qnorm, '%')
             OR u.first_name LIKE :qlike1
             OR u.last_name  LIKE :qlike2
             OR CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) LIKE :qlike3
          )
        ORDER BY COALESCE(sr.processed_date, sr.approved_date, sr.request_date, sr.id) DESC
        LIMIT 300
    ";
    $stmt = $db->prepare($sqlSR);
    $stmt->execute([
        ':hasq'   => $hasQ,
        ':qnorm'  => $q_norm,
        ':qlike1' => $likeContains,
        ':qlike2' => $likeContains,
        ':qlike3' => $likeContains,
    ]);
    $svcRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($svcRows as $r) {
        $fname = trim((string)($r['first_name'] ?? ''));
        $lname = trim((string)($r['last_name'] ?? ''));
        $name  = trim(($fname . ' ' . $lname)) ?: 'Unknown';
        $addr  = trim((string)($r['address'] ?? ''));
        $details = trim(implode(' — ', array_filter([$name, $addr])));

        $out[] = [
            'id' => (string)$r['id'],
            'code' => (string)$r['code'],
            'type' => (string)$r['type'],
            'details' => $details !== '' ? $details : 'N/A',
            'datetime' => (string)$r['datetime'],
            'status' => (string)($r['status'] ?? 'Processing'), // show DB status
            'amount' => (float)($r['amount'] ?? 0),
            'customer_name' => $name,
            'customer_contact' => (string)($r['contact_number'] ?? ''),
            '_raw_status' => $DEBUG ? (string)($r['status'] ?? '') : null
        ];
    }

    // ---------- RESERVATIONS ----------
    $sqlRes = "
        SELECT
            r.id,
            COALESCE(r.reference_number, CONCAT('RES-', r.id)) AS code,
            r.activity,
            r.reservation_date,
            r.time_slots,
            r.total_amount,
            r.status,
            r.resident_name,
            r.contact_number,
            COALESCE(r.created_at, r.reservation_date, NOW()) AS created_at
        FROM reservations r
        WHERE {$rsInclusion}
          AND (
                :hasq2 = 0 OR
                LOWER(REPLACE(REPLACE(COALESCE(r.reference_number, CONCAT('RES-', r.id)),'-',''),' ','')) LIKE CONCAT('%', :qnorm2, '%')
             OR r.resident_name LIKE :qlike4
          )
        ORDER BY COALESCE(r.created_at, r.reservation_date, r.id) DESC
        LIMIT 300
    ";
    $stmt = $db->prepare($sqlRes);
    $stmt->execute([
        ':hasq2'  => $hasQ,
        ':qnorm2' => $q_norm,
        ':qlike4' => $likeContains,
    ]);
    $gymRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fmtHour = static function (int $h): string {
        $to12 = static fn(int $x) => ($x % 12) === 0 ? 12 : ($x % 12);
        $ampm = static fn(int $x) => $x >= 12 ? 'PM' : 'AM';
        return sprintf('%d:00 %s - %d:00 %s', $to12($h), $ampm($h), $to12($h+1), $ampm($h+1));
    };

    foreach ($gymRows as $r) {
        $slots = [];
        if (!empty($r['time_slots'])) {
            $decoded = json_decode($r['time_slots'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $s) {
                    if (is_array($s) && isset($s['time']) && is_string($s['time']) && trim($s['time']) !== '') {
                        $slots[] = trim($s['time']);
                    } elseif (is_array($s) && isset($s['hour']) && is_numeric($s['hour'])) {
                        $slots[] = $fmtHour((int)$s['hour']);
                    } elseif (is_string($s) && trim($s) !== '') {
                        $slots[] = trim($s);
                    }
                }
            }
        }

        $dateLabel = $r['reservation_date'] ? date('M d, Y', strtotime($r['reservation_date'])) : '—';
        $activity  = $r['activity'] ?: 'Gym Reservation';
        $detailStr = $activity . ' • ' . $dateLabel . (count($slots) ? ' • ' . implode(' | ', $slots) : '');
        $name = trim((string)($r['resident_name'] ?? '')) ?: 'Unknown';

        $out[] = [
            'id'       => (string)$r['id'],
            'code'     => (string)$r['code'],
            'type'     => 'Gym Reservation',
            'details'  => $detailStr,
            'datetime' => (string)$r['created_at'],
            'status'   => (string)($r['status'] ?? 'Processing'), // show DB status
            'amount'   => (float)($r['total_amount'] ?? 0),
            'customer_name' => $name,
            'customer_contact' => (string)($r['contact_number'] ?? ''),
            '_raw_status' => $DEBUG ? (string)($r['status'] ?? '') : null
        ];
    }

    // newest first
    usort($out, static function(array $a, array $b): int {
        return strcmp($b['datetime'] ?? '', $a['datetime'] ?? '');
    });

    $resp = ['success' => true, 'requests' => $out];
    if ($DEBUG) {
        $resp['debug'] = [
            'returned' => count($out),
            'note' => 'Both inclusions require paid_at IS NULL to hide settled items.',
        ];
    }

    echo json_encode($resp, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Billing fetch error: ' . $e->getMessage());
    $msg = $DEBUG ? ('Server error: ' . $e->getMessage()) : 'Server error';
    echo json_encode(['success' => false, 'message' => $msg]);
}
