<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

/* ============================================================
   Force JSON-safe error handling (override admin config handlers)
   ============================================================ */
ini_set('display_errors', '0'); // never echo raw errors for JSON endpoints
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Server error',
        // Uncomment to debug temporarily:
        // 'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    // Convert all errors to exceptions so the handler above runs
    throw new ErrorException($message, 0, $severity, $file, $line);
});

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

/* ---- helpers ---- */
function parseDate(?string $s, ?string $fallback=null): ?string { if(!$s) return $fallback; $t=strtotime($s); return $t===false?$fallback:date('Y-m-d',$t); }
function startOfDay(string $ymd): string { return $ymd.' 00:00:00'; }
function endOfDay(string $ymd): string { return $ymd.' 23:59:59'; }
function is_gym_code(?string $raw): bool {
  if ($raw === null) return false;
  $k = strtolower(trim($raw));
  return in_array($k, ['gym', 'gym_reservation', 'gym reservation'], true);
}

function canon_label(string $raw): ?string {
  $k = strtolower(trim($raw));
  if ($k === '' || $k === 'other' || $k === 'others') return null;
  static $map = [
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
    // text fallbacks
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
  return $map[$k] ?? null;
}
function bucketize(string $raw): string {
  $s = strtolower(trim($raw));
  if (in_array($s, ['approved','paid','ready','released','completed','processed','done'], true)) return 'approved';
  if (in_array($s, ['rejected','cancelled','canceled','void','denied'], true)) return 'rejected';
  return 'pending';
}

/* ---- table names ---- */
$REQ_TABLE='service_requests'; $REQ_TYPE='service_type'; $REQ_STATUS='status'; $REQ_DATE='request_date';
$PAY_TABLE='payments'; $PAY_ID='id'; $PAY_DATE='payment_date'; $PAY_USER='user_id'; $PAY_CASHIER='cashier_id'; $PAY_RECEIPT='receipt_number'; $PAY_TOTAL='total_amount';
$PI_TABLE='payment_items'; $PI_PAYID='payment_id'; $PI_TYPE='request_type'; $PI_AMOUNT='amount'; $PI_REQID='request_id';
$RS_TABLE='reservations'; $RS_DATE='reservation_date'; $RS_STATUS='status'; $RS_TOTAL='total_amount'; $RS_PAID='paid_at';
$USR_TABLE='users'; $USR_TYPE='user_type'; $USR_GENDER='gender'; $USR_BIRTH='birth_date';
$ADM_TABLE='admins'; $ADM_ID='admin_id'; $ADM_NAME=['first_name','last_name'];

/* ---- global period for summary ---- */
$today = new DateTime('today', new DateTimeZone('Asia/Manila'));
$period     = $_GET['period']     ?? 'this_month';
$startParam = $_GET['start_date'] ?? null;
$endParam   = $_GET['end_date']   ?? null;
if ($period === 'custom' && $startParam && $endParam) {
  $startYmd = parseDate($startParam); $endYmd = parseDate($endParam);
} else {
  switch ($period) {
    case 'last_month':
      $startYmd=(clone $today)->modify('first day of last month')->format('Y-m-d');
      $endYmd  =(clone $today)->modify('last day of last month')->format('Y-m-d'); break;
    case 'this_quarter':
      $m=(int)$today->format('n'); $q=intdiv($m-1,3); $first=$q*3+1;
      $startYmd=sprintf('%s-%02d-01',$today->format('Y'),$first);
      $endYmd  =(new DateTime($startYmd))->modify('+2 months')->modify('last day of this month')->format('Y-m-d'); break;
    case 'this_year':
      $startYmd=$today->format('Y').'-01-01'; $endYmd=$today->format('Y').'-12-31'; break;
    case 'this_month':
    default:
      $startYmd=(clone $today)->modify('first day of this month')->format('Y-m-d');
      $endYmd  =(clone $today)->modify('last day of this month')->format('Y-m-d');
  }
}
$startAt = startOfDay($startYmd); $endAt = endOfDay($endYmd);

/* ---- SUMMARY ---- */
$SERVICE_TYPES = [
  'Barangay Clearance','Business Permit','Community Tax Cert.','Cert. of Indigency','Cert. of Residency','Low Income Cert.','Proof of Income','Individual Voluntary Statement','Gym Reservation'
];
$requestsByType = []; foreach ($SERVICE_TYPES as $t) $requestsByType[$t]=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
$totalRequests=0; $pendingApprovals=0;

try {
  $sr = $pdo->prepare("SELECT `$REQ_TYPE` AS t, `$REQ_STATUS` AS s, COUNT(*) AS c
                       FROM `$REQ_TABLE`
                       WHERE `$REQ_DATE` BETWEEN :s AND :e
                       GROUP BY `$REQ_TYPE`, `$REQ_STATUS`");
  $sr->execute([':s'=>$startAt, ':e'=>$endAt]);
  while ($r=$sr->fetch()) {
    $label = canon_label((string)$r['t']); if ($label===null) continue;
    $bucket=bucketize((string)$r['s']); $cnt=(int)$r['c'];
    $requestsByType[$label][$bucket]+=$cnt;
    $requestsByType[$label]['total'] +=$cnt;
    $totalRequests += $cnt;
    if ($bucket==='pending') $pendingApprovals += $cnt;
  }
} catch(Throwable $e){}

try {
  $rs = $pdo->prepare("SELECT `$RS_STATUS` AS s, COUNT(*) AS c
                       FROM `$RS_TABLE`
                       WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                       GROUP BY `$RS_STATUS`");
  $rs->execute([':d1'=>$startYmd, ':d2'=>$endYmd]);
  while ($r=$rs->fetch()) {
    $bucket=bucketize((string)$r['s']); $cnt=(int)$r['c'];
    $requestsByType['Gym Reservation'][$bucket]+=$cnt;
    $requestsByType['Gym Reservation']['total'] +=$cnt;
    $totalRequests += $cnt;
    if ($bucket==='pending') $pendingApprovals += $cnt;
  }
} catch(Throwable $e){}

$revenueByType = []; foreach($SERVICE_TYPES as $t) $revenueByType[$t]=0.0; $totalRevenue=0.0;
try {
  $fin = $pdo->prepare("SELECT pi.`$PI_TYPE` AS t, COALESCE(SUM(pi.`$PI_AMOUNT`),0) AS s
                        FROM `$PAY_TABLE` p
                        INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID` = p.`$PAY_ID`
                        WHERE p.`$PAY_DATE` BETWEEN :ps AND :pe
                        GROUP BY pi.`$PI_TYPE`");
  $fin->execute([':ps'=>$startAt, ':pe'=>$endAt]);
  while ($r=$fin->fetch()) {
    $label = canon_label((string)$r['t']); if ($label===null) continue;
    $amt=(float)$r['s']; $revenueByType[$label]+=$amt; $totalRevenue+=$amt;
  }
} catch(Throwable $e){}

/* residents / outsiders counts */
$registeredResidents=0; $registeredOutsiders=0;
try { $registeredResidents = (int)$pdo->query("SELECT COUNT(*) FROM `$USR_TABLE` WHERE `$USR_TYPE`='resident'")->fetchColumn(); } catch(Throwable $e){}
try { $registeredOutsiders = (int)$pdo->query("SELECT COUNT(*) FROM `$USR_TABLE` WHERE `$USR_TYPE`='outsider'")->fetchColumn(); } catch(Throwable $e){}

/* demographics (residents only) */
$genderDist=['male'=>0,'female'=>0];
try {
  $g = $pdo->query("SELECT LOWER(`$USR_GENDER`) AS g, COUNT(*) AS c
                    FROM `$USR_TABLE` WHERE `$USR_TYPE`='resident' GROUP BY `$USR_GENDER`");
  foreach($g as $row){ $gg=$row['g']; if(isset($genderDist[$gg])) $genderDist[$gg]=(int)$row['c']; }
} catch(Throwable $e){}
$ageGroups=['0-17'=>0,'18-35'=>0,'36-55'=>0,'56+'=>0];
try {
  $aq = $pdo->query("SELECT 
    SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) BETWEEN 0 AND 17 THEN 1 ELSE 0 END) a0,
    SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) BETWEEN 18 AND 35 THEN 1 ELSE 0 END) a1,
    SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) BETWEEN 36 AND 55 THEN 1 ELSE 0 END) a2,
    SUM(CASE WHEN `$USR_BIRTH` IS NOT NULL AND TIMESTAMPDIFF(YEAR, `$USR_BIRTH`, CURDATE()) >= 56 THEN 1 ELSE 0 END) a3
  FROM `$USR_TABLE` WHERE `$USR_TYPE`='resident'")->fetch();
  if ($aq) $ageGroups=['0-17'=>(int)$aq['a0'],'18-35'=>(int)$aq['a1'],'36-55'=>(int)$aq['a2'],'56+'=> (int)$aq['a3']];
} catch(Throwable $e){}

/* ---- PER-SECTION FILTERS ---- */

/* REQUESTS */
$req_view = $_GET['req_view'] ?? null;                       // summary|daily
$req_service = $_GET['req_service'] ?? '';                   // service code
$req_start = parseDate($_GET['req_start_date'] ?? null);     // optional
$req_end   = parseDate($_GET['req_end_date'] ?? null);
if ($req_view) {
  $rStart = $req_start ? startOfDay($req_start) : $startAt;
  $rEnd   = $req_end   ? endOfDay($req_end)     : $endAt;
  $rStartY = substr($rStart, 0, 10);
  $rEndY   = substr($rEnd,   0, 10);

  $requests_payload = ['byType'=>null,'daily'=>null,'filters'=>['view'=>$req_view,'service'=>null]];

  if ($req_view === 'summary') {
    $byType = [];

    if ($req_service !== '') {
      if (is_gym_code($req_service)) {
        try {
          $sql = "SELECT `$RS_STATUS` AS s, COUNT(*) AS c
                  FROM `$RS_TABLE`
                  WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                  GROUP BY `$RS_STATUS`";
          $st = $pdo->prepare($sql);
          $st->execute([':d1'=>$rStartY, ':d2'=>$rEndY]);
          while ($row=$st->fetch()) {
            $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
            if (!isset($byType['Gym Reservation'])) $byType['Gym Reservation']=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
            $byType['Gym Reservation'][$bucket]+=$cnt;
            $byType['Gym Reservation']['total'] +=$cnt;
          }
        } catch(Throwable $e){}
      } else {
        try {
          $sql = "SELECT `$REQ_STATUS` AS s, COUNT(*) AS c
                  FROM `$REQ_TABLE`
                  WHERE `$REQ_DATE` BETWEEN :s AND :e AND `$REQ_TYPE` = :svc
                  GROUP BY `$REQ_STATUS`";
          $st = $pdo->prepare($sql);
          $st->execute([':s'=>$rStart, ':e'=>$rEnd, ':svc'=>$req_service]);
          while ($row=$st->fetch()) {
            $label = canon_label($req_service); if ($label===null) continue;
            if (!isset($byType[$label])) $byType[$label]=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
            $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
            $byType[$label][$bucket]+=$cnt; $byType[$label]['total']+=$cnt;
          }
        } catch(Throwable $e){}
      }
    } else {
      try {
        $sql = "SELECT `$REQ_TYPE` AS t, `$REQ_STATUS` AS s, COUNT(*) AS c
                FROM `$REQ_TABLE`
                WHERE `$REQ_DATE` BETWEEN :s AND :e
                GROUP BY `$REQ_TYPE`, `$REQ_STATUS`";
        $st = $pdo->prepare($sql); $st->execute([':s'=>$rStart, ':e'=>$rEnd]);
        while ($row=$st->fetch()) {
          $label=canon_label((string)$row['t']); if ($label===null) continue;
          if (!isset($byType[$label])) $byType[$label]=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
          $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
          $byType[$label][$bucket]+=$cnt; $byType[$label]['total']+=$cnt;
        }
      } catch(Throwable $e){}

      try {
        $sql = "SELECT `$RS_STATUS` AS s, COUNT(*) AS c
                FROM `$RS_TABLE`
                WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                GROUP BY `$RS_STATUS`";
        $st = $pdo->prepare($sql); $st->execute([':d1'=>$rStartY, ':d2'=>$rEndY]);
        while ($row=$st->fetch()) {
          if (!isset($byType['Gym Reservation'])) $byType['Gym Reservation']=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
          $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
          $byType['Gym Reservation'][$bucket]+=$cnt; $byType['Gym Reservation']['total']+=$cnt;
        }
      } catch(Throwable $e){}
    }

    $requests_payload['byType']=$byType;
    $requests_payload['filters']['service'] = $req_service ? canon_label($req_service) : null;

  } else { // daily
    $daily=[];

    if ($req_service !== '') {
      if (is_gym_code($req_service)) {
        try {
          $sql = "SELECT DATE(`$RS_DATE`) AS d, `$RS_STATUS` AS s, COUNT(*) AS c
                  FROM `$RS_TABLE`
                  WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                  GROUP BY DATE(`$RS_DATE`), `$RS_STATUS`";
          $st=$pdo->prepare($sql); $st->execute([':d1'=>$rStartY, ':d2'=>$rEndY]);
          while($row=$st->fetch()){
            $d=$row['d'];
            if (!isset($daily[$d])) $daily[$d]=[];
            if (!isset($daily[$d]['Gym Reservation'])) $daily[$d]['Gym Reservation']=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
            $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
            $daily[$d]['Gym Reservation'][$bucket]+=$cnt; $daily[$d]['Gym Reservation']['total']+=$cnt;
          }
        } catch(Throwable $e){}
      } else {
        try {
          $sql = "SELECT DATE(`$REQ_DATE`) AS d, `$REQ_STATUS` AS s, COUNT(*) AS c
                  FROM `$REQ_TABLE`
                  WHERE `$REQ_DATE` BETWEEN :s AND :e AND `$REQ_TYPE` = :svc
                  GROUP BY DATE(`$REQ_DATE`), `$REQ_STATUS`";
          $st=$pdo->prepare($sql); $st->execute([':s'=>$rStart, ':e'=>$rEnd, ':svc'=>$req_service]);
          $label = canon_label($req_service);
          while($row=$st->fetch()){
            $d=$row['d']; if(!isset($daily[$d])) $daily[$d]=[];
            if (!isset($daily[$d][$label])) $daily[$d][$label]=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
            $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
            $daily[$d][$label][$bucket]+=$cnt; $daily[$d][$label]['total']+=$cnt;
          }
        } catch(Throwable $e){}
      }
    } else {
      try {
        $sql = "SELECT DATE(`$REQ_DATE`) AS d, `$REQ_TYPE` AS t, `$REQ_STATUS` AS s, COUNT(*) AS c
                FROM `$REQ_TABLE`
                WHERE `$REQ_DATE` BETWEEN :s AND :e
                GROUP BY DATE(`$REQ_DATE`), `$REQ_TYPE`, `$REQ_STATUS`";
        $st=$pdo->prepare($sql); $st->execute([':s'=>$rStart, ':e'=>$rEnd]);
        while($row=$st->fetch()){
          $d=$row['d']; $label=canon_label((string)$row['t']); if($label===null) continue;
          if (!isset($daily[$d])) $daily[$d]=[];
          if (!isset($daily[$d][$label])) $daily[$d][$label]=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
          $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
          $daily[$d][$label][$bucket]+=$cnt; $daily[$d][$label]['total']+=$cnt;
        }
      } catch(Throwable $e){}

      try {
        $sql = "SELECT DATE(`$RS_DATE`) AS d, `$RS_STATUS` AS s, COUNT(*) AS c
                FROM `$RS_TABLE`
                WHERE `$RS_DATE` BETWEEN :d1 AND :d2
                GROUP BY DATE(`$RS_DATE`), `$RS_STATUS`";
        $st=$pdo->prepare($sql); $st->execute([':d1'=>$rStartY, ':d2'=>$rEndY]);
        while($row=$st->fetch()){
          $d=$row['d']; if(!isset($daily[$d])) $daily[$d]=[];
          if (!isset($daily[$d]['Gym Reservation'])) $daily[$d]['Gym Reservation']=['approved'=>0,'rejected'=>0,'pending'=>0,'total'=>0];
          $bucket=bucketize((string)$row['s']); $cnt=(int)$row['c'];
          $daily[$d]['Gym Reservation'][$bucket]+=$cnt; $daily[$d]['Gym Reservation']['total']+=$cnt;
        }
      } catch(Throwable $e){}
    }

    $requests_payload['daily']=$daily;
    $requests_payload['filters']['service'] = $req_service ? canon_label($req_service) : null;
  }
}

/* SALES */
$sales_view = $_GET['sales_view'] ?? null;                      // summary|daily
$sales_service = $_GET['sales_service'] ?? '';
$sales_start = parseDate($_GET['sales_start_date'] ?? null);
$sales_end   = parseDate($_GET['sales_end_date'] ?? null);
if ($sales_view) {
  $sStart = $sales_start ? startOfDay($sales_start) : $startAt;
  $sEnd   = $sales_end   ? endOfDay($sales_end)     : $endAt;
  $sales_payload=['byType'=>null,'daily'=>null,'total'=>0,'filters'=>['view'=>$sales_view,'service'=>null]];

  $svcIsGym = is_gym_code($sales_service);

  if ($sales_view === 'summary') {
    $byType=[]; $total=0.0;
    try {
      $sql = "SELECT pi.`$PI_TYPE` AS t, COALESCE(SUM(pi.`$PI_AMOUNT`),0) AS s
              FROM `$PAY_TABLE` p
              INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID`=p.`$PAY_ID`
              WHERE p.`$PAY_DATE` BETWEEN :s AND :e";
      $params=[':s'=>$sStart, ':e'=>$sEnd];

      if ($sales_service !== '') {
        if ($svcIsGym) {
          $sql .= " AND pi.`$PI_TYPE` IN ('gym','gym_reservation')";
        } else {
          $sql .= " AND pi.`$PI_TYPE` = :svc";
          $params[':svc'] = $sales_service;
        }
      }
      $sql.=" GROUP BY pi.`$PI_TYPE`";
      $st=$pdo->prepare($sql); $st->execute($params);
      while($row=$st->fetch()){
        $label=canon_label((string)$row['t']); if($label===null) continue;
        $amt=(float)$row['s']; $byType[$label]=($byType[$label]??0)+$amt; $total+=$amt;
      }
    } catch(Throwable $e){}
    $sales_payload['byType']=$byType; $sales_payload['total']=$total;
    $sales_payload['filters']['service']=$sales_service ? canon_label($sales_service):null;
  } else {
    $daily=[]; $total=0.0;
    try {
      $sql="SELECT DATE(p.`$PAY_DATE`) AS d, pi.`$PI_TYPE` AS t, COALESCE(SUM(pi.`$PI_AMOUNT`),0) AS s
            FROM `$PAY_TABLE` p
            INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID`=p.`$PAY_ID`
            WHERE p.`$PAY_DATE` BETWEEN :s AND :e";
      $params=[':s'=>$sStart, ':e'=>$sEnd];
      if ($sales_service !== '') {
        if ($svcIsGym) {
          $sql .= " AND pi.`$PI_TYPE` IN ('gym','gym_reservation')";
        } else {
          $sql .= " AND pi.`$PI_TYPE` = :svc";
          $params[':svc'] = $sales_service;
        }
      }
      $sql.=" GROUP BY DATE(p.`$PAY_DATE`), pi.`$PI_TYPE`";
      $st=$pdo->prepare($sql); $st->execute($params);
      while($row=$st->fetch()){
        $d=$row['d']; $label=canon_label((string)$row['t']); if($label===null) continue;
        $amt=(float)$row['s']; if(!isset($daily[$d])) $daily[$d]=[]; $daily[$d][$label]=($daily[$d][$label]??0)+$amt; $total+=$amt;
      }
    } catch(Throwable $e){}
    $sales_payload['daily']=$daily; $sales_payload['total']=$total;
    $sales_payload['filters']['service']=$sales_service ? canon_label($sales_service):null;
  }
}

/* TRANSACTION HISTORY */
$tx_service = $_GET['tx_service'] ?? '';                     // payment_items.request_type
$tx_cashier = trim((string)($_GET['tx_cashier'] ?? ''));     // id or name like
$tx_search  = trim((string)($_GET['tx_search']  ?? ''));     // receipt/email
$tx_start   = parseDate($_GET['tx_start_date'] ?? null);
$tx_end     = parseDate($_GET['tx_end_date'] ?? null);
$tx_page    = max(1, (int)($_GET['tx_page'] ?? 1));

// DEFAULT: 30 rows if not specified; still respects explicit sizes (e.g., 10000 for export)
$requested   = array_key_exists('tx_page_size', $_GET) ? (int)$_GET['tx_page_size'] : 30;
$tx_page_sz  = min(10000, max(1, $requested));

$tx_payload = null;
if ($tx_service || $tx_cashier !== '' || $tx_search !== '' || $tx_start || $tx_end || isset($_GET['tx_page'])) {
  $tStart = $tx_start ? startOfDay($tx_start) : $startAt;
  $tEnd   = $tx_end   ? endOfDay($tx_end)     : $endAt;

  $where = ["p.`$PAY_DATE` BETWEEN :ts AND :te"];
  $params = [':ts'=>$tStart, ':te'=>$tEnd];

  $needsPiJoin = false;

  if ($tx_service) {
    $needsPiJoin = true;
    if (is_gym_code($tx_service)) {
      $where[] = "pi.`$PI_TYPE` IN ('gym','gym_reservation')";
    } else {
      $where[] = "pi.`$PI_TYPE` = :txsvc";
      $params[':txsvc'] = $tx_service;
    }
  }
  if ($tx_search !== '') {
    $where[] = "(p.`$PAY_RECEIPT` LIKE :srch OR EXISTS(SELECT 1 FROM `$USR_TABLE` u WHERE u.`id`=p.`$PAY_USER` AND (u.`email` LIKE :srch OR u.`username` LIKE :srch)))";
    $params[':srch'] = '%'.$tx_search.'%';
  }
  $joinCashier = '';
  if ($tx_cashier !== '') {
    if (ctype_digit($tx_cashier)) {
      $where[] = "p.`$PAY_CASHIER` = :cid";
      $params[':cid'] = (int)$tx_cashier;
    } else {
      $joinCashier = " LEFT JOIN `$ADM_TABLE` a ON a.`$ADM_ID` = p.`$PAY_CASHIER` ";
      $where[] = "(CONCAT_WS(' ', a.`first_name`, a.`last_name`) LIKE :cname)";
      $params[':cname'] = '%'.$tx_cashier.'%';
    }
  }

  $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

  // COUNT (items rows) — keep join to pi so service filter works
  $countSql = "SELECT COUNT(*) FROM `$PAY_TABLE` p 
               INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID`=p.`$PAY_ID`
               $joinCashier
               $whereSql";
  $cst = $pdo->prepare($countSql); $cst->execute($params);
  $totalRows = (int)$cst->fetchColumn();

  // MAIN PAGE QUERY (payments page). Join to pi only if required by WHERE.
  $joinPi = $needsPiJoin ? " INNER JOIN `$PI_TABLE` pi ON pi.`$PI_PAYID`=p.`$PAY_ID` " : "";
  $offset = ($tx_page - 1) * $tx_page_sz;
  $sql = "SELECT DISTINCT
                 p.`$PAY_ID` AS pid, p.`$PAY_DATE` AS pdate, p.`$PAY_RECEIPT` AS receipt,
                 p.`$PAY_USER` AS uid, p.`$PAY_CASHIER` AS cashier_id, p.`$PAY_TOTAL` AS total
          FROM `$PAY_TABLE` p
          $joinPi
          $joinCashier
          $whereSql
          ORDER BY p.`$PAY_DATE` DESC, p.`$PAY_ID` DESC
          LIMIT :lim OFFSET :off";
  $st = $pdo->prepare($sql);
  foreach($params as $k=>$v){ $st->bindValue($k, $v); }
  $st->bindValue(':lim', $tx_page_sz, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();

  $payments = $st->fetchAll();
  $rows = [];

  if ($payments) {
    $ids = array_column($payments, 'pid');

    // items for those payments
    $in = implode(',', array_fill(0, count($ids), '?'));
    if ($in !== '') {
      $it = $pdo->prepare("SELECT `$PI_PAYID` AS pid, `$PI_TYPE` AS t, `$PI_REQID` AS rid, `$PI_AMOUNT` AS amt
                           FROM `$PI_TABLE` WHERE `$PI_PAYID` IN ($in)");
      $it->execute($ids);
      $byPay = [];
      while($r=$it->fetch()){
        $label = canon_label((string)$r['t']); if ($label===null) continue;
        $byPay[$r['pid']][] = ['service'=>$label, 'request_id'=>(int)$r['rid'], 'amount'=>(float)$r['amt']];
      }
    } else {
      $byPay = [];
    }

    // preload user names
    $userIds = array_values(array_unique(array_column($payments, 'uid')));
    $uMap = [];
    if ($userIds) {
      $uin = implode(',', array_fill(0, count($userIds), '?'));
      if ($uin !== '') {
        $us = $pdo->prepare("SELECT id, COALESCE(NULLIF(CONCAT_WS(' ', first_name, last_name),' '), username, email) AS name, email FROM `$USR_TABLE` WHERE id IN ($uin)");
        $us->execute($userIds);
        while($u=$us->fetch()){ $uMap[$u['id']] = ['id'=>(int)$u['id'],'name'=>$u['name'] ?? null,'email'=>$u['email'] ?? null]; }
      }
    }

    // preload cashier names
    $cashIds = array_values(array_unique(array_column($payments, 'cashier_id')));
    $cMap = [];
    if ($cashIds) {
      $cin = implode(',', array_fill(0, count($cashIds), '?'));
      if ($cin !== '') {
        $cs = $pdo->prepare("SELECT `$ADM_ID` AS id, COALESCE(NULLIF(CONCAT_WS(' ', first_name, last_name),' '), username) AS name FROM `$ADM_TABLE` WHERE `$ADM_ID` IN ($cin)");
        $cs->execute($cashIds);
        while($c=$cs->fetch()){ $cMap[$c['id']] = ['id'=>(int)$c['id'],'name'=>$c['name'] ?? null]; }
      }
    }

    foreach ($payments as $p) {
      $pid=(int)$p['pid'];
      $rows[] = [
        'payment_id'    => $pid,
        'payment_date'  => $p['pdate'],
        'receipt_number'=> $p['receipt'],
        'user'          => $uMap[$p['uid']] ?? ['id'=>(int)$p['uid']],
        'cashier'       => $cMap[$p['cashier_id']] ?? ['id'=>(int)$p['cashier_id']],
        'items'         => $byPay[$pid] ?? [],
        'total_amount'  => (float)$p['total']
      ];
    }
  }

  $tx_payload = [
    'rows' => $rows,
    'total_rows' => $totalRows,
    'page' => $tx_page,
    'page_size' => $tx_page_sz,
    'filters' => [
      'service' => $tx_service ? canon_label($tx_service) : null,
      'cashier' => $tx_cashier !== '' ? $tx_cashier : null,
      'search'  => $tx_search !== '' ? $tx_search  : null,
      'start'   => $tx_start, 'end'=>$tx_end
    ]
  ];
}

/* ---- response ---- */
$out = [
  'ok'      => true,
  'period'  => ['start_date'=>$startYmd, 'end_date'=>$endYmd],
  'summary' => [
    'totalRequests'        => (int)$totalRequests,
    'pendingApprovals'     => (int)$pendingApprovals,
    'totalRevenue'         => (float)$totalRevenue,
    'registeredResidents'  => (int)$registeredResidents,
    'registeredOutsiders'  => (int)$registeredOutsiders,
  ],
  'requests'  => isset($requests_payload) ? $requests_payload : ['byType'=>$requestsByType],
  'sales'     => isset($sales_payload)    ? $sales_payload    : ['byType'=>$revenueByType, 'total'=>(float)$totalRevenue],
  'financial' => isset($sales_payload)    ? $sales_payload    : ['byType'=>$revenueByType, 'total'=>(float)$totalRevenue], // back-compat
  'demographics' => ['age'=>$ageGroups,'gender'=>$genderDist],
];
if ($tx_payload !== null) $out['transactions'] = $tx_payload;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
