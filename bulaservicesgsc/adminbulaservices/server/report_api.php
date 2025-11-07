<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

/* ============================================================
   Strict JSON-safe error handling for API
   ============================================================ */
ini_set('display_errors', '0'); // never echo raw errors for JSON endpoints
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Server error',
        // 'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/* ============================================================
   Auth & DB bootstrap
   ============================================================ */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
    exit;
}

/* ============================================================
   Helpers
   ============================================================ */
function parseDate(?string $s, ?string $fallback = null): ?string {
    if (!$s) return $fallback;
    $t = strtotime($s);
    return $t === false ? $fallback : date('Y-m-d', $t);
}
function startOfDay(string $ymd): string { return $ymd . ' 00:00:00'; }
function endOfDay(string $ymd): string   { return $ymd . ' 23:59:59'; }

/**
 * Tell if a service code is gym / reservation
 */
function is_gym_code(?string $raw): bool {
    if ($raw === null) return false;
    $k = strtolower(trim($raw));
    return in_array($k, ['gym', 'gym_reservation', 'gym reservation'], true);
}

/**
 * Canonical label for service types.
 *
 * We now include your actual DB values:
 *  - barangay_clearance
 *  - cedula
 *  - residency
 *  - proof_income
 *  - low_income
 *  - indigency
 *  - ivs
 *  - business_permit
 *  - gym / gym_reservation
 *
 * If we don't recognize it, we fallback to a readable version
 * instead of dropping it.
 */
function canon_label(string $raw): ?string {
    $k = strtolower(trim($raw));
    if ($k === '' || $k === 'other' || $k === 'others') return null;

    static $map = [
        // underscore codes in DB
        'barangay_clearance' => 'Barangay Clearance',
        'business_permit'    => 'Business Permit',
        'cedula'             => 'Community Tax Cert.',
        'indigency'          => 'Cert. of Indigency',
        'residency'          => 'Cert. of Residency',
        'low_income'         => 'Low Income Cert.',
        'proof_income'       => 'Proof of Income',
        'ivs'                => 'Individual Voluntary Statement',
        'gym'                => 'Gym Reservation',
        'gym_reservation'    => 'Gym Reservation',

        // possible text versions
        'barangay clearance'              => 'Barangay Clearance',
        'business permit'                 => 'Business Permit',
        'community tax cert.'             => 'Community Tax Cert.',
        'certificate of indigency'        => 'Cert. of Indigency',
        'certificate of residency'        => 'Cert. of Residency',
        'low income cert.'                => 'Low Income Cert.',
        'proof of income'                 => 'Proof of Income',
        'individual voluntary statement'  => 'Individual Voluntary Statement',
        'gym reservation'                 => 'Gym Reservation',
    ];

    if (isset($map[$k])) {
        return $map[$k];
    }

    // fallback so we still count unrecognized services
    return ucwords(str_replace('_', ' ', $k));
}

/**
 * Normalize statuses into buckets used in UI:
 * - approved  (completed, approved, released, ready, paid, processed...)
 * - rejected  (rejected, cancelled, denied...)
 * - pending   (pending/others)
 *
 * IMPORTANT: "completed" from your DB now maps to "approved".
 */
function bucketize(string $raw): string {
    $s = strtolower(trim($raw));

    // treat these as approved / done
    if (in_array($s, [
        'approved', 'paid', 'ready', 'released', 'completed', 'processed',
        'done', 'finished', 'complete'
    ], true)) {
        return 'approved';
    }

    // treat these as rejected
    if (in_array($s, [
        'rejected', 'cancelled', 'canceled', 'void', 'denied', 'declined'
    ], true)) {
        return 'rejected';
    }

    // else pending/ongoing
    return 'pending';
}

/* ============================================================
   Table / Column aliases
   (Adjust here if schema differs)
   ============================================================ */
$REQ_TABLE   = 'service_requests';
$REQ_TYPE    = 'service_type';
$REQ_STATUS  = 'status';
$REQ_DATE    = 'request_date';

$PAY_TABLE   = 'payments';
$PAY_ID      = 'id';
$PAY_DATE    = 'payment_date';     // <-- confirm actual column
$PAY_USER    = 'user_id';
$PAY_CASHIER = 'cashier_id';
$PAY_RECEIPT = 'receipt_number';
$PAY_TOTAL   = 'total_amount';

$PI_TABLE    = 'payment_items';
$PI_PAYID    = 'payment_id';
$PI_TYPE     = 'request_type';     // <-- confirm actual column name
$PI_AMOUNT   = 'amount';
$PI_REQID    = 'request_id';

$RS_TABLE    = 'reservations';
$RS_DATE     = 'reservation_date';
$RS_STATUS   = 'status';
$RS_TOTAL    = 'total_amount';
$RS_PAID     = 'paid_at';

$USR_TABLE   = 'users';
$USR_TYPE    = 'user_type';        // resident / outsider
$USR_GENDER  = 'gender';
$USR_BIRTH   = 'birth_date';

$ADM_TABLE   = 'admins';
$ADM_ID      = 'admin_id';         // numeric id of admin/cashier

/* ============================================================
   Global period for SUMMARY cards
   (Top cards still follow dashboard filter: This Month, etc.)
   ============================================================ */
$today      = new DateTime('today', new DateTimeZone('Asia/Manila'));
$period     = $_GET['period']     ?? 'this_month';
$startParam = $_GET['start_date'] ?? null;
$endParam   = $_GET['end_date']   ?? null;

if ($period === 'custom' && $startParam && $endParam) {
    $startYmd = parseDate($startParam);
    $endYmd   = parseDate($endParam);
} else {
    switch ($period) {
        case 'last_month':
            $startYmd = (clone $today)->modify('first day of last month')->format('Y-m-d');
            $endYmd   = (clone $today)->modify('last day of last month')->format('Y-m-d');
            break;
        case 'this_quarter':
            $m        = (int)$today->format('n');
            $q        = intdiv($m - 1, 3);
            $first    = $q * 3 + 1;
            $startYmd = sprintf('%s-%02d-01', $today->format('Y'), $first);
            $endYmd   = (new DateTime($startYmd))
                ->modify('+2 months')
                ->modify('last day of this month')
                ->format('Y-m-d');
            break;
        case 'this_year':
            $startYmd = $today->format('Y') . '-01-01';
            $endYmd   = $today->format('Y') . '-12-31';
            break;
        case 'this_month':
        default:
            $startYmd = (clone $today)->modify('first day of this month')->format('Y-m-d');
            $endYmd   = (clone $today)->modify('last day of this month')->format('Y-m-d');
    }
}
$startAt = startOfDay($startYmd);
$endAt   = endOfDay($endYmd);

/* ============================================================
   SUMMARY CARDS
   ============================================================ */
$KNOWN_SERVICES = [
    'Barangay Clearance',
    'Business Permit',
    'Community Tax Cert.',
    'Cert. of Indigency',
    'Cert. of Residency',
    'Low Income Cert.',
    'Proof of Income',
    'Individual Voluntary Statement',
    'Gym Reservation'
];

$requestsByType = [];
foreach ($KNOWN_SERVICES as $t) {
    $requestsByType[$t] = [
        'approved' => 0,
        'rejected' => 0,
        'pending'  => 0,
        'total'    => 0
    ];
}

$totalRequests    = 0;
$pendingApprovals = 0;

/* --- service_requests for summary cards --- */
try {
    $sr = $pdo->prepare("
        SELECT `$REQ_TYPE` AS t, `$REQ_STATUS` AS s, COUNT(*) AS c
        FROM `$REQ_TABLE`
        WHERE `$REQ_DATE` BETWEEN :s AND :e
        GROUP BY `$REQ_TYPE`, `$REQ_STATUS`
    ");
    $sr->execute([':s' => $startAt, ':e' => $endAt]);

    while ($r = $sr->fetch()) {
        $label  = canon_label((string)$r['t']);
        if ($label === null) continue;
        $bucket = bucketize((string)$r['s']);
        $cnt    = (int)$r['c'];

        if (!isset($requestsByType[$label])) {
            $requestsByType[$label] = [
                'approved' => 0,
                'rejected' => 0,
                'pending'  => 0,
                'total'    => 0
            ];
        }

        $requestsByType[$label][$bucket] += $cnt;
        $requestsByType[$label]['total'] += $cnt;

        $totalRequests += $cnt;
        if ($bucket === 'pending') {
            $pendingApprovals += $cnt;
        }
    }
} catch (Throwable $e) {
    // ignore for summary
}

/* --- reservations (gym) for summary cards --- */
try {
    $rs = $pdo->prepare("
        SELECT `$RS_STATUS` AS s, COUNT(*) AS c
        FROM `$RS_TABLE`
        WHERE `$RS_DATE` BETWEEN :d1 AND :d2
        GROUP BY `$RS_STATUS`
    ");
    $rs->execute([':d1' => $startYmd, ':d2' => $endYmd]);

    while ($r = $rs->fetch()) {
        $bucket = bucketize((string)$r['s']);
        $cnt    = (int)$r['c'];

        if (!isset($requestsByType['Gym Reservation'])) {
            $requestsByType['Gym Reservation'] = [
                'approved' => 0,
                'rejected' => 0,
                'pending'  => 0,
                'total'    => 0
            ];
        }

        $requestsByType['Gym Reservation'][$bucket] += $cnt;
        $requestsByType['Gym Reservation']['total'] += $cnt;

        $totalRequests += $cnt;
        if ($bucket === 'pending') {
            $pendingApprovals += $cnt;
        }
    }
} catch (Throwable $e) {
    // ignore for summary
}

/* --- revenue summary (for cards) --- */
$revenueByType = [];
$totalRevenue  = 0.0;

// Pre-seed
foreach ($KNOWN_SERVICES as $t) {
    $revenueByType[$t] = 0.0;
}

try {
    $fin = $pdo->prepare("
        SELECT pi.`$PI_TYPE` AS t,
               COALESCE(SUM(pi.`$PI_AMOUNT`),0) AS s
        FROM `$PAY_TABLE` p
        INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID` = p.`$PAY_ID`
        WHERE p.`$PAY_DATE` BETWEEN :ps AND :pe
        GROUP BY pi.`$PI_TYPE`
    ");
    $fin->execute([':ps' => $startAt, ':pe' => $endAt]);

    while ($r = $fin->fetch()) {
        $label = canon_label((string)$r['t']);
        $amt   = (float)$r['s'];

        if ($label === null) continue;
        if (!isset($revenueByType[$label])) {
            $revenueByType[$label] = 0.0;
        }

        $revenueByType[$label] += $amt;
        $totalRevenue          += $amt;
    }
} catch (Throwable $e) {
    // ignore for summary
}

/* --- resident / outsider counters --- */
$registeredResidents = 0;
$registeredOutsiders = 0;

try {
    $registeredResidents = (int)$pdo->query("
        SELECT COUNT(*) FROM `$USR_TABLE`
        WHERE `$USR_TYPE`='resident'
    ")->fetchColumn();
} catch (Throwable $e) {}

try {
    $registeredOutsiders = (int)$pdo->query("
        SELECT COUNT(*) FROM `$USR_TABLE`
        WHERE `$USR_TYPE`='outsider'
    ")->fetchColumn();
} catch (Throwable $e) {}

/* --- demographics for charts --- */
$genderDist = ['male' => 0, 'female' => 0];
try {
    $g = $pdo->query("
        SELECT LOWER(`$USR_GENDER`) AS g, COUNT(*) AS c
        FROM `$USR_TABLE`
        WHERE `$USR_TYPE`='resident'
        GROUP BY `$USR_GENDER`
    ");
    foreach ($g as $row) {
        $gg = $row['g'];
        if (isset($genderDist[$gg])) {
            $genderDist[$gg] = (int)$row['c'];
        }
    }
} catch (Throwable $e) {}

$ageGroups = ['0-17' => 0, '18-35' => 0, '36-55' => 0, '56+' => 0];
try {
    $aq = $pdo->query("
        SELECT 
          SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL
                    AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) BETWEEN 0 AND 17
              THEN 1 ELSE 0 END) a0,
          SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL
                    AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) BETWEEN 18 AND 35
              THEN 1 ELSE 0 END) a1,
          SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL
                    AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) BETWEEN 36 AND 55
              THEN 1 ELSE 0 END) a2,
          SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL
                    AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) >= 56
              THEN 1 ELSE 0 END) a3
        FROM `$USR_TABLE`
        WHERE `$USR_TYPE`='resident'
    ")->fetch();

    if ($aq) {
        $ageGroups = [
            '0-17'   => (int)$aq['a0'],
            '18-35'  => (int)$aq['a1'],
            '36-55'  => (int)$aq['a2'],
            '56+'    => (int)$aq['a3'],
        ];
    }
} catch (Throwable $e) {}

/* ============================================================
   PER-SECTION: REQUESTS (summary / daily)
   IMPORTANT CHANGE:
   If user did NOT pick a custom date in the Requests section,
   we now default to ALL TIME (1970 -> now),
   NOT just "this month".
   ============================================================ */
$req_view    = $_GET['req_view'] ?? null;
$req_service = $_GET['req_service'] ?? '';
$req_startI  = $_GET['req_start_date'] ?? null;
$req_endI    = $_GET['req_end_date']   ?? null;

$req_start   = parseDate($req_startI);
$req_end     = parseDate($req_endI);

if ($req_view) {
    // fallback range for Requests table:
    // if no user-supplied range, show ALL historical data
    if ($req_start === null && $req_end === null) {
        $rStart = '1970-01-01 00:00:00';
        $rEnd   = endOfDay((new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'));
        $rStartY = '1970-01-01';
        $rEndY   = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    } else {
        $rStart = $req_start ? startOfDay($req_start) : '1970-01-01 00:00:00';
        $rEnd   = $req_end   ? endOfDay($req_end)     : endOfDay((new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'));
        $rStartY = substr($rStart, 0, 10);
        $rEndY   = substr($rEnd,   0, 10);
    }

    $requests_payload = [
        'byType'  => null,
        'daily'   => null,
        'filters' => [
            'view'    => $req_view,
            'service' => null
        ]
    ];

    if ($req_view === 'summary') {
        $byType = [];

        if ($req_service !== '') {
            // specific service
            if (is_gym_code($req_service)) {
                // gym summary only
                try {
                    $sql = "
                        SELECT `$RS_STATUS` AS s, COUNT(*) AS c
                        FROM `$RS_TABLE`
                        WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                        GROUP BY `$RS_STATUS`
                    ";
                    $st = $pdo->prepare($sql);
                    $st->execute([':d1' => $rStartY, ':d2' => $rEndY]);
                    while ($row = $st->fetch()) {
                        $bucket = bucketize((string)$row['s']);
                        $cnt    = (int)$row['c'];

                        if (!isset($byType['Gym Reservation'])) {
                            $byType['Gym Reservation'] = [
                                'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                            ];
                        }
                        $byType['Gym Reservation'][$bucket] += $cnt;
                        $byType['Gym Reservation']['total'] += $cnt;
                    }
                } catch (Throwable $e) {}
            } else {
                // single non-gym service
                try {
                    $sql = "
                        SELECT `$REQ_STATUS` AS s, COUNT(*) AS c
                        FROM `$REQ_TABLE`
                        WHERE `$REQ_DATE` BETWEEN :s AND :e
                          AND `$REQ_TYPE` = :svc
                        GROUP BY `$REQ_STATUS`
                    ";
                    $st = $pdo->prepare($sql);
                    $st->execute([
                        ':s'   => $rStart,
                        ':e'   => $rEnd,
                        ':svc' => $req_service
                    ]);

                    $labelForSvc = canon_label($req_service);

                    while ($row = $st->fetch()) {
                        $bucket = bucketize((string)$row['s']);
                        $cnt    = (int)$row['c'];

                        if (!isset($byType[$labelForSvc])) {
                            $byType[$labelForSvc] = [
                                'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                            ];
                        }
                        $byType[$labelForSvc][$bucket] += $cnt;
                        $byType[$labelForSvc]['total'] += $cnt;
                    }
                } catch (Throwable $e) {}
            }
        } else {
            // ALL services summary
            try {
                $sql = "
                    SELECT `$REQ_TYPE` AS t, `$REQ_STATUS` AS s, COUNT(*) AS c
                    FROM `$REQ_TABLE`
                    WHERE `$REQ_DATE` BETWEEN :s AND :e
                    GROUP BY `$REQ_TYPE`, `$REQ_STATUS`
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':s' => $rStart, ':e' => $rEnd]);

                while ($row = $st->fetch()) {
                    $label  = canon_label((string)$row['t']);
                    if ($label === null) continue;
                    $bucket = bucketize((string)$row['s']);
                    $cnt    = (int)$row['c'];

                    if (!isset($byType[$label])) {
                        $byType[$label] = [
                            'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                        ];
                    }
                    $byType[$label][$bucket] += $cnt;
                    $byType[$label]['total'] += $cnt;
                }
            } catch (Throwable $e) {}

            // also include gym reservations
            try {
                $sql = "
                    SELECT `$RS_STATUS` AS s, COUNT(*) AS c
                    FROM `$RS_TABLE`
                    WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                    GROUP BY `$RS_STATUS`
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':d1' => $rStartY, ':d2' => $rEndY]);

                while ($row = $st->fetch()) {
                    $bucket = bucketize((string)$row['s']);
                    $cnt    = (int)$row['c'];

                    if (!isset($byType['Gym Reservation'])) {
                        $byType['Gym Reservation'] = [
                            'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                        ];
                    }
                    $byType['Gym Reservation'][$bucket] += $cnt;
                    $byType['Gym Reservation']['total'] += $cnt;
                }
            } catch (Throwable $e) {}
        }

        $requests_payload['byType'] = $byType;
        $requests_payload['filters']['service'] =
            $req_service ? canon_label($req_service) : null;

    } else {
        // DAILY VIEW
        $daily = [];

        if ($req_service !== '') {
            // single service
            if (is_gym_code($req_service)) {
                try {
                    $sql = "
                        SELECT DATE(`$RS_DATE`) AS d, `$RS_STATUS` AS s, COUNT(*) AS c
                        FROM `$RS_TABLE`
                        WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                        GROUP BY DATE(`$RS_DATE`), `$RS_STATUS`
                    ";
                    $st = $pdo->prepare($sql);
                    $st->execute([':d1' => $rStartY, ':d2' => $rEndY]);

                    while ($row = $st->fetch()) {
                        $d      = $row['d'];
                        $bucket = bucketize((string)$row['s']);
                        $cnt    = (int)$row['c'];

                        if (!isset($daily[$d])) $daily[$d] = [];
                        if (!isset($daily[$d]['Gym Reservation'])) {
                            $daily[$d]['Gym Reservation'] = [
                                'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                            ];
                        }
                        $daily[$d]['Gym Reservation'][$bucket] += $cnt;
                        $daily[$d]['Gym Reservation']['total'] += $cnt;
                    }
                } catch (Throwable $e) {}
            } else {
                try {
                    $sql = "
                        SELECT DATE(`$REQ_DATE`) AS d, `$REQ_STATUS` AS s, COUNT(*) AS c
                        FROM `$REQ_TABLE`
                        WHERE `$REQ_DATE` BETWEEN :s AND :e
                          AND `$REQ_TYPE` = :svc
                        GROUP BY DATE(`$REQ_DATE`), `$REQ_STATUS`
                    ";
                    $st = $pdo->prepare($sql);
                    $st->execute([
                        ':s'   => $rStart,
                        ':e'   => $rEnd,
                        ':svc' => $req_service
                    ]);

                    $labelForSvc = canon_label($req_service);

                    while ($row = $st->fetch()) {
                        $d      = $row['d'];
                        $bucket = bucketize((string)$row['s']);
                        $cnt    = (int)$row['c'];

                        if (!isset($daily[$d])) $daily[$d] = [];
                        if (!isset($daily[$d][$labelForSvc])) {
                            $daily[$d][$labelForSvc] = [
                                'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                            ];
                        }
                        $daily[$d][$labelForSvc][$bucket] += $cnt;
                        $daily[$d][$labelForSvc]['total'] += $cnt;
                    }
                } catch (Throwable $e) {}
            }
        } else {
            // ALL services
            try {
                $sql = "
                    SELECT DATE(`$REQ_DATE`) AS d,
                           `$REQ_TYPE` AS t,
                           `$REQ_STATUS` AS s,
                           COUNT(*) AS c
                    FROM `$REQ_TABLE`
                    WHERE `$REQ_DATE` BETWEEN :s AND :e
                    GROUP BY DATE(`$REQ_DATE`), `$REQ_TYPE`, `$REQ_STATUS`
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':s' => $rStart, ':e' => $rEnd]);

                while ($row = $st->fetch()) {
                    $d      = $row['d'];
                    $label  = canon_label((string)$row['t']);
                    if ($label === null) continue;
                    $bucket = bucketize((string)$row['s']);
                    $cnt    = (int)$row['c'];

                    if (!isset($daily[$d])) $daily[$d] = [];
                    if (!isset($daily[$d][$label])) {
                        $daily[$d][$label] = [
                            'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                        ];
                    }
                    $daily[$d][$label][$bucket] += $cnt;
                    $daily[$d][$label]['total'] += $cnt;
                }
            } catch (Throwable $e) {}

            // gym too
            try {
                $sql = "
                    SELECT DATE(`$RS_DATE`) AS d, `$RS_STATUS` AS s, COUNT(*) AS c
                    FROM `$RS_TABLE`
                    WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                    GROUP BY DATE(`$RS_DATE`), `$RS_STATUS`
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':d1' => $rStartY, ':d2' => $rEndY]);

                while ($row = $st->fetch()) {
                    $d      = $row['d'];
                    $bucket = bucketize((string)$row['s']);
                    $cnt    = (int)$row['c'];

                    if (!isset($daily[$d])) $daily[$d] = [];
                    if (!isset($daily[$d]['Gym Reservation'])) {
                        $daily[$d]['Gym Reservation'] = [
                            'approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0
                        ];
                    }
                    $daily[$d]['Gym Reservation'][$bucket] += $cnt;
                    $daily[$d]['Gym Reservation']['total'] += $cnt;
                }
            } catch (Throwable $e) {}
        }

        $requests_payload['daily'] = $daily;
        $requests_payload['filters']['service'] =
            $req_service ? canon_label($req_service) : null;
    }
}

/* ============================================================
   PER-SECTION: SALES (summary / daily)
   SAME CHANGE:
   If user did NOT pick a date range in Sales section,
   default to ALL TIME instead of "this month".
   ============================================================ */
$sales_view    = $_GET['sales_view'] ?? null;
$sales_service = $_GET['sales_service'] ?? '';
$sales_startI  = $_GET['sales_start_date'] ?? null;
$sales_endI    = $_GET['sales_end_date']   ?? null;

$sales_start   = parseDate($sales_startI);
$sales_end     = parseDate($sales_endI);

if ($sales_view) {
    // fallback range for Sales table:
    if ($sales_start === null && $sales_end === null) {
        $sStart = '1970-01-01 00:00:00';
        $sEnd   = endOfDay((new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'));
    } else {
        $sStart = $sales_start
            ? startOfDay($sales_start)
            : '1970-01-01 00:00:00';
        $sEnd   = $sales_end
            ? endOfDay($sales_end)
            : endOfDay((new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'));
    }

    $sales_payload = [
        'byType'  => null,
        'daily'   => null,
        'total'   => 0,
        'filters' => [
            'view'    => $sales_view,
            'service' => null
        ]
    ];

    $svcIsGym = is_gym_code($sales_service);

    if ($sales_view === 'summary') {
        $byType = [];
        $total  = 0.0;

        try {
            $sql = "
                SELECT pi.`$PI_TYPE` AS t,
                       COALESCE(SUM(pi.`$PI_AMOUNT`),0) AS s
                FROM `$PAY_TABLE` p
                INNER JOIN `$PI_TABLE` pi
                        ON pi.`$PI_PAYID` = p.`$PAY_ID`
                WHERE p.`$PAY_DATE` BETWEEN :s AND :e
            ";
            $params = [':s' => $sStart, ':e' => $sEnd];

            if ($sales_service !== '') {
                if ($svcIsGym) {
                    $sql .= " AND pi.`$PI_TYPE` IN ('gym','gym_reservation')";
                } else {
                    $sql .= " AND pi.`$PI_TYPE` = :svc";
                    $params[':svc'] = $sales_service;
                }
            }

            $sql .= " GROUP BY pi.`$PI_TYPE`";

            $st = $pdo->prepare($sql);
            $st->execute($params);

            while ($row = $st->fetch()) {
                $label = canon_label((string)$row['t']);
                if ($label === null) continue;

                $amt = (float)$row['s'];

                if (!isset($byType[$label])) {
                    $byType[$label] = 0.0;
                }
                $byType[$label] += $amt;
                $total          += $amt;
            }
        } catch (Throwable $e) {}

        $sales_payload['byType']  = $byType;
        $sales_payload['total']   = $total;
        $sales_payload['filters']['service'] =
            $sales_service ? canon_label($sales_service) : null;

    } else {
        // DAILY VIEW
        $daily = [];
        $total = 0.0;

        try {
            $sql = "
                SELECT DATE(p.`$PAY_DATE`) AS d,
                       pi.`$PI_TYPE` AS t,
                       COALESCE(SUM(pi.`$PI_AMOUNT`),0) AS s
                FROM `$PAY_TABLE` p
                INNER JOIN `$PI_TABLE` pi
                        ON pi.`$PI_PAYID` = p.`$PAY_ID`
                WHERE p.`$PAY_DATE` BETWEEN :s AND :e
            ";
            $params = [':s' => $sStart, ':e' => $sEnd];

            if ($sales_service !== '') {
                if ($svcIsGym) {
                    $sql .= " AND pi.`$PI_TYPE` IN ('gym','gym_reservation')";
                } else {
                    $sql .= " AND pi.`$PI_TYPE` = :svc";
                    $params[':svc'] = $sales_service;
                }
            }

            $sql .= " GROUP BY DATE(p.`$PAY_DATE`), pi.`$PI_TYPE`";

            $st = $pdo->prepare($sql);
            $st->execute($params);

            while ($row = $st->fetch()) {
                $d     = $row['d'];
                $label = canon_label((string)$row['t']);
                if ($label === null) continue;

                $amt = (float)$row['s'];

                if (!isset($daily[$d])) $daily[$d] = [];
                if (!isset($daily[$d][$label])) {
                    $daily[$d][$label] = 0.0;
                }
                $daily[$d][$label] += $amt;
                $total             += $amt;
            }
        } catch (Throwable $e) {}

        $sales_payload['daily']   = $daily;
        $sales_payload['total']   = $total;
        $sales_payload['filters']['service'] =
            $sales_service ? canon_label($sales_service) : null;
    }
}

/* ============================================================
   DEMOGRAPHIC FILTER / SEGMENT EXPORT
   ============================================================ */
$demo_filter  = $_GET['demo_filter'] ?? null;
$demo_payload = null;

if ($demo_filter !== null) {
    $age_min = isset($_GET['age_min']) && $_GET['age_min'] !== '' ? (int)$_GET['age_min'] : null;
    $age_max = isset($_GET['age_max']) && $_GET['age_max'] !== '' ? (int)$_GET['age_max'] : null;
    $gender  = isset($_GET['gender']) && $_GET['gender'] !== '' ? strtolower(trim((string)$_GET['gender'])) : null;
    $civil   = isset($_GET['civil_status']) && $_GET['civil_status'] !== '' ? strtolower(trim((string)$_GET['civil_status'])) : null;
    $purok   = isset($_GET['purok']) && $_GET['purok'] !== '' ? trim((string)$_GET['purok']) : null;

    $limit   = isset($_GET['limit']) && (int)$_GET['limit'] > 0 ? (int)$_GET['limit'] : 200;
    if ($limit > 10000) $limit = 10000;

    $wheres = ["u.`$USR_TYPE` = 'resident'"];
    $params = [];

    if ($gender !== null) {
        $wheres[] = "LOWER(u.`$USR_GENDER`) = :g";
        $params[':g'] = $gender;
    }
    if ($civil !== null) {
        $wheres[] = "LOWER(u.`civil_status`) = :civ";
        $params[':civ'] = $civil;
    }
    if ($purok !== null) {
        $wheres[] = "u.`purok` = :purok";
        $params[':purok'] = $purok;
    }
    if ($age_min !== null) {
        $wheres[] = "u.`$USR_BIRTH` IS NOT NULL
                     AND TIMESTAMPDIFF(YEAR, u.`$USR_BIRTH`, CURDATE()) >= :amin";
        $params[':amin'] = $age_min;
    }
    if ($age_max !== null) {
        $wheres[] = "u.`$USR_BIRTH` IS NOT NULL
                     AND TIMESTAMPDIFF(YEAR, u.`$USR_BIRTH`, CURDATE()) <= :amax";
        $params[':amax'] = $age_max;
    }

    $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

    $countSql = "
        SELECT COUNT(*) AS cnt
        FROM `$USR_TABLE` u
        $whereSql
    ";
    $countSt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) { $countSt->bindValue($k, $v); }
    $countSt->execute();
    $totalCount = (int)$countSt->fetchColumn();

    $listSql = "
        SELECT 
            u.id,
            TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS fullname,
            u.`$USR_GENDER` AS gender,
            u.civil_status AS civil_status,
            u.purok        AS purok,
            u.`$USR_BIRTH` AS birth_date,
            TIMESTAMPDIFF(YEAR, u.`$USR_BIRTH`, CURDATE()) AS age
        FROM `$USR_TABLE` u
        $whereSql
        ORDER BY fullname ASC
        LIMIT :lim
    ";
    $listSt = $pdo->prepare($listSql);
    foreach ($params as $k => $v) { $listSt->bindValue($k, $v); }
    $listSt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $listSt->execute();

    $rowsOut = [];
    while ($u = $listSt->fetch()) {
        $rowsOut[] = [
            'id'            => (int)$u['id'],
            'name'          => $u['fullname'] ?: null,
            'age'           => ($u['age'] !== null ? (int)$u['age'] : null),
            'gender'        => $u['gender'] ?? null,
            'civil_status'  => $u['civil_status'] ?? null,
            'purok'         => $u['purok'] ?? null,
        ];
    }

    $demo_payload = [
        'count' => $totalCount,
        'rows'  => $rowsOut,
        'limit' => $limit
    ];
}

/* ============================================================
   FINAL RESPONSE
   ============================================================ */
$out = [
    'ok'      => true,
    'period'  => [
        'start_date' => $startYmd,
        'end_date'   => $endYmd
    ],
    'summary' => [
        'totalRequests'       => (int)$totalRequests,
        'pendingApprovals'    => (int)$pendingApprovals,
        'totalRevenue'        => (float)$totalRevenue,
        'registeredResidents' => (int)$registeredResidents,
        'registeredOutsiders' => (int)$registeredOutsiders,
    ],

    // Requests section payload (with all-time fallback)
    'requests' => isset($requests_payload)
        ? $requests_payload
        : ['byType' => $requestsByType],

    // Sales section payload (with all-time fallback)
    'sales' => isset($sales_payload)
        ? $sales_payload
        : ['byType' => $revenueByType, 'total' => (float)$totalRevenue],

    'financial' => isset($sales_payload)
        ? $sales_payload
        : ['byType' => $revenueByType, 'total' => (float)$totalRevenue],

    'demographics' => [
        'age'    => $ageGroups,
        'gender' => $genderDist
    ],
];

// attach demographic segment table if requested
if ($demo_payload !== null) {
    $out['demo_filtered'] = $demo_payload;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
