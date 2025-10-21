<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}

try {
  $pdo = getDBConnection();
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB connection failed']); exit;
}

/* ---------------- time helpers ---------------- */
function parseDate(string $s, ?string $fallback=null): ?string {
  $t = strtotime($s);
  if ($t === false) return $fallback;
  return date('Y-m-d', $t);
}
function startOfDay(string $ymd): string { return $ymd . ' 00:00:00'; }
function endOfDay(string $ymd): string   { return $ymd . ' 23:59:59'; }

/* ---------------- canonical services (UI order) ---------------- */
$SERVICE_TYPES = [
  'Barangay Clearance',
  'Business Permit',
  'Community Tax Cert.',
  'Cert. of Indigency',
  'Cert. of Residency',
  'Low Income Cert.',
  'Proof of Income',
  'Individual Voluntary Statement',
  'Gym Reservation',
];

/**
 * Map raw enum/aliases → canonical label.
 * Return null to ignore invalid buckets like "other/others/empty".
 */
function canon_label(string $raw): ?string {
  $k = strtolower(trim($raw));
  if ($k === '' || $k === 'other' || $k === 'others') return null;

  static $map = [
    // service_requests enums in your dump
    'barangay_clearance' => 'Barangay Clearance',
    'business_permit'    => 'Business Permit',
    'cedula'             => 'Community Tax Cert.',
    'indigency'          => 'Cert. of Indigency',
    'residency'          => 'Cert. of Residency',
    'low_income'         => 'Low Income Cert.',
    'proof_income'       => 'Proof of Income',
    'ivs'                => 'Individual Voluntary Statement',
    'gym'                => 'Gym Reservation',

    // payment_items enums
    'gym_reservation'    => 'Gym Reservation',

    // defensive aliases
    'community_tax_certificate' => 'Community Tax Cert.',
    'community_tax_cert'        => 'Community Tax Cert.',
    'certificate_of_indigency'  => 'Cert. of Indigency',
    'certificate_of_residency'  => 'Cert. of Residency',
    'low_income_cert'           => 'Low Income Cert.',
    'proof_of_income'           => 'Proof of Income',
  ];
  if (isset($map[$k])) return $map[$k];

  static $canon_text = [
    'barangay clearance'              => 'Barangay Clearance',
    'business permit'                 => 'Business Permit',
    'community tax cert.'             => 'Community Tax Cert.',
    'cert. of indigency'              => 'Cert. of Indigency',
    'cert. of residency'              => 'Cert. of Residency',
    'low income cert.'                => 'Low Income Cert.',
    'proof of income'                 => 'Proof of Income',
    'individual voluntary statement'  => 'Individual Voluntary Statement',
    'gym reservation'                 => 'Gym Reservation',
  ];
  return $canon_text[$k] ?? null;
}

/* ---------------- status buckets ---------------- */
$bucketize = function (string $raw): string {
  $s = strtolower(trim($raw));
  if (in_array($s, ['approved','paid','ready','released','completed','processed','done'], true)) return 'approved';
  if (in_array($s, ['rejected','cancelled','canceled','void','denied'], true))                 return 'rejected';
  return 'pending';
};

/* ---------------- period ---------------- */
$today = new DateTime('today', new DateTimeZone('Asia/Manila'));
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
      $m = (int) $today->format('n');
      $q = intdiv($m - 1, 3);
      $first = $q * 3 + 1;
      $startYmd = sprintf('%s-%02d-01', $today->format('Y'), $first);
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

/* ---------------- table/column names ---------------- */
$REQ_TABLE      = 'service_requests';
$REQ_TYPE_COL   = 'service_type';
$REQ_STATUS_COL = 'status';
$REQ_DATE_COL   = 'request_date';

$PAY_TABLE      = 'payments';
$PAY_ID_COL     = 'id';
$PAY_DATE_COL   = 'payment_date';

$PI_TABLE       = 'payment_items';
$PI_PAYID_COL   = 'payment_id';
$PI_TYPE_COL    = 'request_type';
$PI_AMOUNT_COL  = 'amount';

$RS_TABLE       = 'reservations';
$RS_DATE_COL    = 'reservation_date';
$RS_STATUS_COL  = 'status';
$RS_TOTAL_COL   = 'total_amount';
$RS_PAID_COL    = 'paid_at';

/* ---- DEMOGRAPHICS NOW FROM users (only user_type='resident') ---- */
$USR_TABLE       = 'users';
$USR_TYPE_COL    = 'user_type';   // enum('resident','outsider')
$USR_GENDER_COL  = 'gender';      // enum('male','female')
$USR_BDATE_COL   = 'birth_date';  // date

/* ---------------- init aggregates ---------------- */
$requestsByType = [];
foreach ($SERVICE_TYPES as $t) {
  $requestsByType[$t] = ['approved'=>0, 'rejected'=>0, 'pending'=>0, 'total'=>0];
}
$totalRequests = 0;
$pendingApprovals = 0;

/* ---------------- 1) service_requests ---------------- */
try {
  $srSql = "
    SELECT `$REQ_TYPE_COL` AS t, `$REQ_STATUS_COL` AS s, COUNT(*) AS c
    FROM `$REQ_TABLE`
    WHERE `$REQ_DATE_COL` BETWEEN :s AND :e
    GROUP BY `$REQ_TYPE_COL`, `$REQ_STATUS_COL`
  ";
  $sr = $pdo->prepare($srSql);
  $sr->execute([':s'=>$startAt, ':e'=>$endAt]);

  while ($r = $sr->fetch()) {
    $label = canon_label((string) $r['t']);
    if ($label === null) continue;

    $bucket = $bucketize((string) $r['s']);
    $cnt    = (int) $r['c'];

    $requestsByType[$label][$bucket] += $cnt;
    $requestsByType[$label]['total']  += $cnt;

    $totalRequests += $cnt;
    if ($bucket === 'pending') $pendingApprovals += $cnt;
  }
} catch (Throwable $e) {}

/* ---------------- 2) reservations → "Gym Reservation" ---------------- */
try {
  $rsSql = "
    SELECT `$RS_STATUS_COL` AS s, COUNT(*) AS c
    FROM `$RS_TABLE`
    WHERE `$RS_DATE_COL` BETWEEN :d1 AND :d2
    GROUP BY `$RS_STATUS_COL`
  ";
  $rs = $pdo->prepare($rsSql);
  $rs->execute([':d1'=>$startYmd, ':d2'=>$endYmd]);

  while ($r = $rs->fetch()) {
    $bucket = $bucketize((string) $r['s']);
    $cnt    = (int) $r['c'];

    $requestsByType['Gym Reservation'][$bucket] += $cnt;
    $requestsByType['Gym Reservation']['total'] += $cnt;

    $totalRequests += $cnt;
    if ($bucket === 'pending') $pendingApprovals += $cnt;
  }
} catch (Throwable $e) {}

/* ---------------- 3) sales (payments + payment_items) ---------------- */
$revenueByType = [];
foreach ($SERVICE_TYPES as $t) $revenueByType[$t] = 0.0;
$totalRevenue = 0.0;

try {
  $finSql = "
    SELECT pi.`$PI_TYPE_COL` AS t, COALESCE(SUM(pi.`$PI_AMOUNT_COL`),0) AS s
    FROM `$PAY_TABLE` p
    INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID_COL` = p.`$PAY_ID_COL`
    WHERE p.`$PAY_DATE_COL` BETWEEN :ps AND :pe
    GROUP BY pi.`$PI_TYPE_COL`
  ";
  $fin = $pdo->prepare($finSql);
  $fin->execute([':ps'=>$startAt, ':pe'=>$endAt]);

  while ($r = $fin->fetch()) {
    $label = canon_label((string) $r['t']);
    if ($label === null) continue;

    $amt = (float) $r['s'];
    $revenueByType[$label] += $amt;
    $totalRevenue += $amt;
  }

  // Optional: include reservations with paid_at even if no payment_items exist
  /*
  $gymPaidSql = "
    SELECT COALESCE(SUM(`$RS_TOTAL_COL`),0) AS s
    FROM `$RS_TABLE`
    WHERE `$RS_PAID_COL` IS NOT NULL
      AND DATE(`$RS_PAID_COL`) BETWEEN :d1 AND :d2
  ";
  $gp = $pdo->prepare($gymPaidSql);
  $gp->execute([':d1'=>$startYmd, ':d2'=>$endYmd]);
  $extra = (float) ($gp->fetchColumn() ?: 0);
  if ($extra > 0) {
    $revenueByType['Gym Reservation'] += $extra;
    $totalRevenue += $extra;
  }
  */
} catch (Throwable $e) {}

/* ---------------- 4) demographics from users (residents only) ---------------- */

/* Total residents (fast, authoritative) */
$totalResidents = 0;
try {
  $totalResidents = (int)$pdo->query("
    SELECT COUNT(*) AS c
    FROM `$USR_TABLE`
    WHERE `$USR_TYPE_COL` = 'resident'
  ")->fetchColumn();
} catch (Throwable $e) { $totalResidents = 0; }

/* Gender distribution: only male/female in table */
$genderDist = ['male'=>0, 'female'=>0];
try {
  $gstmt = $pdo->query("
    SELECT LOWER(`$USR_GENDER_COL`) AS g, COUNT(*) AS c
    FROM `$USR_TABLE`
    WHERE `$USR_TYPE_COL` = 'resident'
    GROUP BY `$USR_GENDER_COL`
  ");
  foreach ($gstmt as $r) {
    $g = $r['g'];
    if (isset($genderDist[$g])) $genderDist[$g] = (int)$r['c'];
  }
} catch (Throwable $e) {}

/* Age buckets from birth_date, residents only */
$ageGroups = ['0-17'=>0,'18-35'=>0,'36-55'=>0,'56+'=>0];
try {
  $aq = $pdo->query("
    SELECT 
      SUM(CASE WHEN `$USR_BDATE_COL` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BDATE_COL`, CURDATE()) BETWEEN 0  AND 17 THEN 1 ELSE 0 END) AS a0_17,
      SUM(CASE WHEN `$USR_BDATE_COL` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BDATE_COL`, CURDATE()) BETWEEN 18 AND 35 THEN 1 ELSE 0 END) AS a18_35,
      SUM(CASE WHEN `$USR_BDATE_COL` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BDATE_COL`, CURDATE()) BETWEEN 36 AND 55 THEN 1 ELSE 0 END) AS a36_55,
      SUM(CASE WHEN `$USR_BDATE_COL` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BDATE_COL`, CURDATE()) >= 56                         THEN 1 ELSE 0 END) AS a56p
    FROM `$USR_TABLE`
    WHERE `$USR_TYPE_COL` = 'resident'
  ")->fetch();
  if ($aq) {
    $ageGroups = [
      '0-17'  => (int)$aq['a0_17'],
      '18-35' => (int)$aq['a18_35'],
      '36-55' => (int)$aq['a36_55'],
      '56+'   => (int)$aq['a56p'],
    ];
  }
} catch (Throwable $e) {}

 /* If totalResidents somehow 0 (edge case), derive from age buckets */
if ($totalResidents === 0) {
  $totalResidents = array_sum($ageGroups);
}

/* ---------------- response ---------------- */
echo json_encode([
  'ok'      => true,
  'period'  => ['start_date'=>$startYmd, 'end_date'=>$endYmd],
  'summary' => [
    'totalRequests'       => (int)$totalRequests,
    'pendingApprovals'    => (int)$pendingApprovals,
    'totalRevenue'        => (float)$totalRevenue,
    'registeredResidents' => (int)$totalResidents, // residents only
  ],
  'requests'   => ['byType' => $requestsByType],
  'sales'      => ['byType' => $revenueByType, 'total' => (float)$totalRevenue],
  'financial'  => ['byType' => $revenueByType, 'total' => (float)$totalRevenue], // backward-compat
  'demographics' => [
    'age'    => $ageGroups,
    'gender' => $genderDist, // only male/female
  ],
], JSON_UNESCAPED_UNICODE);
