<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';

header('Content-Type: application/json; charset=utf-8');
// Prevent any caching so the browser never reuses stale prices
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $db = getDBConnection();
    if ($db instanceof PDO) {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // 1) Certificates
    // Only accept the keys your front-end actually uses, in lower-case.
    $allowed = [
        'bc','bp','cedula','indigency','ivs','lic','pic','residency'
    ];

    $cert = [];
    $stmt = $db->query("SELECT type_code, price FROM certificate_pricing");
    foreach ($stmt as $r) {
        $key = strtolower(trim((string)$r['type_code']));
        $val = (string)($r['price'] ?? '');

        // normalize number, reject non-numerics
        $val = str_replace([',',' '], '', $val);
        if ($key !== '' && in_array($key, $allowed, true) && is_numeric($val)) {
            $cert[$key] = (float)$val;
        }
    }

    // 2) Gym (single-row table)
    $gym = [];
    $stmt2 = $db->query("SELECT morning_rate, evening_rate FROM gym_pricing ORDER BY id ASC LIMIT 1");
    if ($row = $stmt2->fetch()) {
        $m = isset($row['morning_rate']) ? str_replace([',',' '], '', (string)$row['morning_rate']) : '';
        $e = isset($row['evening_rate']) ? str_replace([',',' '], '', (string)$row['evening_rate']) : '';
        if (is_numeric($m)) $gym['gym_morning'] = (float)$m;
        if (is_numeric($e)) $gym['gym_evening'] = (float)$e;
    }

    // 3) Build final map exactly as front-end expects
    $prices = $cert + $gym; // '+' preserves existing string keys; no reindexing

    echo json_encode([
        'success' => true,
        'prices'  => $prices,
        'keys'    => array_keys($prices),   // helpful for quick client-side sanity checks
        'updated' => date('c')
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('get_public_prices error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
