<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php'; // must start session + define getDBConnection()

// Optional: gate by admin session (match your app's rule)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['admin_id'])) {
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

/* ----------------- helpers ----------------- */
function parseDate(string $s, ?string $fallback = null): ?string {
    $t = strtotime($s);
    if ($t === false) return $fallback;
    return date('Y-m-d', $t);
}
function startOfDay(string $ymd): string { return $ymd . ' 00:00:00'; }
function endOfDay(string $ymd): string { return $ymd . ' 23:59:59'; }

/** Cache SHOW COLUMNS for each table, and return real column name that matches any candidate (case-insensitive). */
function findCol(PDO $pdo, string $table, array $candidates): ?string {
    static $cache = [];
    $key = strtolower($table);
    if (!isset($cache[$key])) {
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) {
            $cols[strtolower($r['Field'])] = $r['Field']; // map lower->real
        }
        $cache[$key] = $cols;
    }
    $cols = $cache[$key];
    foreach ($candidates as $cand) {
        $lc = strtolower($cand);
        if (isset($cols[$lc])) return $cols[$lc];
    }
    return null;
}

/* ----------------- period ----------------- */
$today = new DateTime('today', new DateTimeZone('Asia/Manila'));
$period = $_GET['period'] ?? 'this_month';
$startParam = $_GET['start_date'] ?? null;
$endParam   = $_GET['end_date'] ?? null;

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
            $m = (int)$today->format('n');
            $q = intdiv($m - 1, 3);
            $firstMonth = $q * 3 + 1;
            $startYmd = sprintf('%s-%02d-01', $today->format('Y'), $firstMonth);
            $endYmd   = (new DateTime($startYmd))->modify('+2 months')->modify('last day of this month')->format('Y-m-d');
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

/* ----------------- constants ----------------- */
$SERVICE_TYPES = [
    'Barangay Clearance',
    'Business Permit',
    'Community Tax Cert.',
    'Cert. of Indigency',
    'Cert. of Residency',
    'Low Income Cert.',
    'Proof of Income',
    'Gym Reservation'
];

// status bucket helper (uniform for SR + Reservations)
$bucketize = function (string $raw): string {
    $s = strtolower(trim($raw));
    if (in_array($s, ['approved','completed','paid','ready','processed'], true)) return 'approved';
    if (in_array($s, ['rejected','cancelled','canceled','void'], true))         return 'rejected';
    return 'pending';
};

/* ----------------- table/column mapping (your schema) ----------------- */
// service_requests
$REQ_TABLE      = 'service_requests';
$REQ_TYPE_COL   = findCol($pdo, $REQ_TABLE, ['service_type', 'request_type', 'type', 'document_type']);
$REQ_STATUS_COL = findCol($pdo, $REQ_TABLE, ['status', 'request_status', 'state']);
$REQ_DATE_COL   = findCol($pdo, $REQ_TABLE, ['request_date','created_at','date','submitted_at']);
if (!$REQ_TYPE_COL || !$REQ_STATUS_COL) {
    echo json_encode(['ok'=>false,'error'=>'service_requests must have service_type and status']); exit;
}

// payments + payment_items (sales is per-line in items, date comes from payments)
$PAY_TABLE    = 'payments';
$PI_TABLE     = 'payment_items';
$PAY_DATE_COL = findCol($pdo, $PAY_TABLE, ['payment_date','paid_at','date','created_at']);
$PI_AMOUNT_COL= findCol($pdo, $PI_TABLE, ['amount','total_amount','fee','price','line_amount']);
$PI_TYPE_COL  = findCol($pdo, $PI_TABLE, ['request_type','service_type','type','document_type']);
$PI_PAYID_COL = findCol($pdo, $PI_TABLE, ['payment_id','pay_id']);
$PAY_ID_COL   = findCol($pdo, $PAY_TABLE, ['id']);
if (!$PI_AMOUNT_COL || !$PI_TYPE_COL || !$PI_PAYID_COL || !$PAY_ID_COL) {
    echo json_encode(['ok'=>false,'error'=>'payment_items must have amount, request_type, payment_id; payments must have id']); exit;
}

// residents (gender + birthdate) — optional
$RES_TABLE = 'residents';
$RES_GENDER_COL    = findCol($pdo, $RES_TABLE, ['gender', 'sex']);
$RES_BIRTHDATE_COL = findCol($pdo, $RES_TABLE, ['birthdate','birth_date','date_of_birth','dob']);

// reservations (for Gym Reservation counts + optional sales fallback)
$RS_TABLE       = 'reservations';
$RS_STATUS_COL  = findCol($pdo, $RS_TABLE, ['status']);
$RS_DATE_COL    = findCol($pdo, $RS_TABLE, ['reservation_date','created_at','date']);
$RS_TOTAL_COL   = findCol($pdo, $RS_TABLE, ['total_amount','amount']);
$RS_PAID_COL    = findCol($pdo, $RS_TABLE, ['paid_at']);

/* ----------------- 1) Summary (requests) ----------------- */
// service_requests summary
$reqWhere = '1'; $rParams = [];
if ($REQ_DATE_COL) {
    $reqWhere = "`$REQ_DATE_COL` BETWEEN :rs AND :re";
    $rParams = [':rs'=>$startAt, ':re'=>$endAt];
}
$reqSummarySql = "
    SELECT 
        COUNT(*) AS total_requests,
        SUM(CASE WHEN LOWER(TRIM(`$REQ_STATUS_COL`)) IN ('requested','pending','for_approval','processing') THEN 1 ELSE 0 END) AS pending_approvals
    FROM `$REQ_TABLE`
    WHERE $reqWhere
";
$reqStmt = $pdo->prepare($reqSummarySql);
$reqStmt->execute($rParams);
$srSummary = $reqStmt->fetch() ?: ['total_requests'=>0,'pending_approvals'=>0];

// reservations summary (for total + pending)
$rsTotal = 0; $rsPending = 0;
if ($RS_TABLE && $RS_DATE_COL && $RS_STATUS_COL) {
    $rsSumSql = "
        SELECT 
            COUNT(*) AS total_r,
            SUM(CASE WHEN LOWER(TRIM(`$RS_STATUS_COL`)) IN ('requested','pending','for_approval','processing') THEN 1 ELSE 0 END) AS pending_r
        FROM `$RS_TABLE`
        WHERE `$RS_DATE_COL` BETWEEN :s AND :e
    ";
    $st = $pdo->prepare($rsSumSql);
    $st->execute([':s'=>$startYmd, ':e'=>$endYmd]);
    $row = $st->fetch();
    if ($row) { $rsTotal = (int)$row['total_r']; $rsPending = (int)$row['pending_r']; }
}

$totalRequests    = (int)$srSummary['total_requests'] + $rsTotal;
$pendingApprovals = (int)$srSummary['pending_approvals'] + $rsPending;

/* ----------------- 2) Requests by service type ----------------- */
// Start with all known service types
$requestsByType = [];
foreach ($SERVICE_TYPES as $t) $requestsByType[$t] = ['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];

// service_requests grouped
$reqByTypeSql = "
    SELECT `$REQ_TYPE_COL` AS service_type, `$REQ_STATUS_COL` AS status, COUNT(*) AS c
    FROM `$REQ_TABLE`
    WHERE $reqWhere
    GROUP BY `$REQ_TYPE_COL`, `$REQ_STATUS_COL`
";
$reqBy = $pdo->prepare($reqByTypeSql);
$reqBy->execute($rParams);
foreach ($reqBy->fetchAll() as $r) {
    $tRaw = (string)$r['service_type'];
    $bucket = $bucketize((string)$r['status']);
    $cnt = (int)$r['c'];
    $t = $tRaw; // display name as-is; your frontend normalizes pretty labels
    if (!isset($requestsByType[$t])) $requestsByType[$t] = ['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
    $requestsByType[$t][$bucket] += $cnt;
    $requestsByType[$t]['total'] += $cnt;
}

// reservations grouped (as virtual type = Gym Reservation)
if ($RS_TABLE && $RS_DATE_COL && $RS_STATUS_COL) {
    $rsSql = "
        SELECT `$RS_STATUS_COL` AS status, COUNT(*) AS c
        FROM `$RS_TABLE`
        WHERE `$RS_DATE_COL` BETWEEN :s AND :e
        GROUP BY `$RS_STATUS_COL`
    ";
    $st = $pdo->prepare($rsSql);
    $st->execute([':s'=>$startYmd, ':e'=>$endYmd]);
    $label = 'Gym Reservation';
    if (!isset($requestsByType[$label])) $requestsByType[$label] = ['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
    foreach ($st->fetchAll() as $r) {
        $bucket = $bucketize((string)$r['status']);
        $cnt = (int)$r['c'];
        $requestsByType[$label][$bucket] += $cnt;
        $requestsByType[$label]['total'] += $cnt;
    }
}

/* ----------------- 3) Sales (payments + items) ----------------- */
$payWhere = '1'; $pParams = [];
if ($PAY_DATE_COL) { $payWhere = "p.`$PAY_DATE_COL` BETWEEN :ps AND :pe"; $pParams = [':ps'=>$startAt, ':pe'=>$endAt]; }

$finSql = "
    SELECT pi.`$PI_TYPE_COL` AS service_type, COALESCE(SUM(pi.`$PI_AMOUNT_COL`),0) AS total_amount
    FROM `$PAY_TABLE` p
    INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID_COL` = p.`$PAY_ID_COL`
    WHERE $payWhere
    GROUP BY pi.`$PI_TYPE_COL`
";
$finStmt = $pdo->prepare($finSql);
$finStmt->execute($pParams);
$finRows = $finStmt->fetchAll();

$revenueByType = []; $totalRevenue = 0.0;
foreach ($SERVICE_TYPES as $t) $revenueByType[$t] = 0.0;
foreach ($finRows as $r) {
    $t = (string)$r['service_type'];
    $amt = (float)$r['total_amount'];
    $totalRevenue += $amt;
    if (!isset($revenueByType[$t])) $revenueByType[$t] = 0.0;
    $revenueByType[$t] += $amt;
}

// (Optional) Fallback: if you also want to ensure gym sales included even if no payment_items rows exist,
// uncomment below to add reservations.paid_at totals.
/*
if ($RS_TABLE && $RS_PAID_COL && $RS_TOTAL_COL) {
    $sqlGym = "
        SELECT COALESCE(SUM(`$RS_TOTAL_COL`),0) AS s
        FROM `$RS_TABLE`
        WHERE `$RS_PAID_COL` IS NOT NULL
          AND DATE(`$RS_PAID_COL`) BETWEEN :s AND :e
    ";
    $st = $pdo->prepare($sqlGym);
    $st->execute([':s'=>$startYmd, ':e'=>$endYmd]);
    $extra = (float)($st->fetchColumn() ?: 0);
    if ($extra > 0) {
        $revenueByType['Gym Reservation'] = ($revenueByType['Gym Reservation'] ?? 0) + $extra;
        $totalRevenue += $extra;
    }
}
*/

/* ----------------- 4) Residents (gender + age buckets) ----------------- */
$genderDist = ['male'=>0,'female'=>0,'other'=>0];
$totalResidents = 0;

if ($RES_TABLE && $RES_GENDER_COL) {
    $gSql = "SELECT `$RES_GENDER_COL` AS g, COUNT(*) AS cnt FROM `$RES_TABLE` GROUP BY `$RES_GENDER_COL`";
    foreach ($pdo->query($gSql) as $r) {
        $g = strtolower((string)$r['g']);
        $c = (int)$r['cnt'];
        if (isset($genderDist[$g])) $genderDist[$g] = $c;
        $totalResidents += $c;
    }
}

$ageGroups = ['0-17'=>0,'18-35'=>0,'36-55'=>0,'56+'=>0];
if ($RES_TABLE && $RES_BIRTHDATE_COL) {
    $sql = "
        SELECT 
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, `$RES_BIRTHDATE_COL`, CURDATE()) BETWEEN 0  AND 17 THEN 1 ELSE 0 END) AS a0_17,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, `$RES_BIRTHDATE_COL`, CURDATE()) BETWEEN 18 AND 35 THEN 1 ELSE 0 END) AS a18_35,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, `$RES_BIRTHDATE_COL`, CURDATE()) BETWEEN 36 AND 55 THEN 1 ELSE 0 END) AS a36_55,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, `$RES_BIRTHDATE_COL`, CURDATE()) >= 56                         THEN 1 ELSE 0 END) AS a56p
        FROM `$RES_TABLE`
    ";
    $a = $pdo->query($sql)->fetch();
    if ($a) {
        $ageGroups = [
            '0-17'  => (int)$a['a0_17'],
            '18-35' => (int)$a['a18_35'],
            '36-55' => (int)$a['a36_55'],
            '56+'   => (int)$a['a56p'],
        ];
    }
}
if ($totalResidents === 0) $totalResidents = array_sum($ageGroups);

/* ----------------- response ----------------- */
echo json_encode([
    'ok' => true,
    'period' => ['start_date'=>$startYmd, 'end_date'=>$endYmd],
    'summary' => [
        'totalRequests'       => (int)$totalRequests,
        'pendingApprovals'    => (int)$pendingApprovals,
        'totalRevenue'        => (float)$totalRevenue,
        'registeredResidents' => (int)$totalResidents
    ],
    'requests'   => ['byType'=>$requestsByType],
    // expose as "sales" for the frontend rename, keep "financial" for backward-compat
    'sales'      => ['byType'=>$revenueByType, 'total'=>(float)$totalRevenue],
    'financial'  => ['byType'=>$revenueByType, 'total'=>(float)$totalRevenue],
    'demographics' => ['age'=>$ageGroups, 'gender'=>$genderDist]
], JSON_UNESCAPED_UNICODE);
