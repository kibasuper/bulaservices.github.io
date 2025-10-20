<?php
header('Content-Type: application/json');

// ---------- CONFIG ----------
$db_host = 'localhost';
$db_user = 'bulaservices';
$db_pass = '84kjXKf8Tjf9WG1f';
$db_name = 'bulaservicesfiles';

// CHANGE THIS! keep it private (move to env if you can)
$ADMIN_API_KEY = 'change-this-admin-key-123';

// ---------- AUTH ----------
$recv_key = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
if ($recv_key !== $ADMIN_API_KEY) {
  http_response_code(401);
  echo json_encode(['status'=>'error','message'=>'Unauthorized']);
  exit;
}

// ---------- DB ----------
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB connection failed']);
  exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

function set_csv_headers($filename){
  header_remove('Content-Type');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
}

function clamp_int($v,$min,$max,$def){ if(!is_numeric($v))return $def; $v=(int)$v; return max($min,min($max,$v)); }

// -------- list_reservations --------
if ($action === 'list_reservations') {
  $q       = trim($input['q'] ?? '');
  $status  = $input['status'] ?? ''; // pending/approved/cancelled/completed or ''
  $dateMin = $input['date_from'] ?? '';
  $dateMax = $input['date_to'] ?? '';
  $page    = clamp_int($input['page'] ?? 1, 1, 100000, 1);
  $perPage = clamp_int($input['per_page'] ?? 10, 1, 100, 10);
  $offset  = ($page - 1) * $perPage;

  $where=[]; $params=[]; $types='';
  if ($status !== '') { $where[]="status=?"; $params[]=$status; $types.='s'; }
  if ($dateMin !== '') { $where[]="reservation_date>=?"; $params[]=$dateMin; $types.='s'; }
  if ($dateMax !== '') { $where[]="reservation_date<=?"; $params[]=$dateMax; $types.='s'; }
  if ($q !== '') { $where[]="(resident_name LIKE CONCAT('%', ?, '%') OR contact_number LIKE CONCAT('%', ?, '%') OR reference_number LIKE CONCAT('%', ?, '%') OR activity LIKE CONCAT('%', ?, '%'))"; array_push($params,$q,$q,$q,$q); $types.='ssss'; }
  $whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

  // count
  $stmt=$mysqli->prepare("SELECT COUNT(*) c FROM reservations $whereSql");
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total=(int)$stmt->get_result()->fetch_assoc()['c'];
  $stmt->close();

  // data
  $sql="SELECT id,reservation_date,time_slots,resident_name,contact_number,activity,notes,reference_number,total_amount,status,created_at
        FROM reservations $whereSql
        ORDER BY reservation_date DESC, created_at DESC
        LIMIT ? OFFSET ?";
  $types2=$types.'ii'; $params2=$params; $params2[]=$perPage; $params2[]=$offset;
  $stmt=$mysqli->prepare($sql);
  $stmt->bind_param($types2, ...$params2);
  $stmt->execute(); $res=$stmt->get_result();
  $rows=[];
  while($row=$res->fetch_assoc()){ $row['time_slots']=json_decode($row['time_slots'],true); $rows[]=$row; }
  $stmt->close();

  echo json_encode(['status'=>'success','page'=>$page,'per_page'=>$perPage,'total'=>$total,'data'=>$rows]);
  exit;
}

// -------- get_by_reference --------
if ($action === 'get_by_reference') {
  $ref = trim($input['reference'] ?? '');
  if ($ref === '') { echo json_encode(['status'=>'error','message'=>'Missing reference']); exit; }
  $stmt = $mysqli->prepare("SELECT * FROM reservations WHERE reference_number = ? LIMIT 1");
  $stmt->bind_param('s', $ref);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$r) { echo json_encode(['status'=>'error','message'=>'Not found']); exit; }
  $r['time_slots']=json_decode($r['time_slots'],true);
  echo json_encode(['status'=>'success','data'=>$r]); exit;
}

// -------- update_status --------
if ($action === 'update_status') {
  $id = (int)($input['id'] ?? 0);
  $newStatus = $input['status'] ?? '';
  if (!$id || !in_array($newStatus, ['pending','approved','cancelled','completed'], true)) {
    echo json_encode(['status'=>'error','message'=>'Invalid parameters']); exit;
  }
  $stmt=$mysqli->prepare("UPDATE reservations SET status=? WHERE id=?");
  $stmt->bind_param('si',$newStatus,$id);
  if ($stmt->execute()) echo json_encode(['status'=>'success']);
  else echo json_encode(['status'=>'error','message'=>$stmt->error]);
  $stmt->close(); exit;
}

// -------- delete_reservation --------
if ($action === 'delete_reservation') {
  $id = (int)($input['id'] ?? 0);
  if (!$id) { echo json_encode(['status'=>'error','message'=>'Missing id']); exit; }
  $stmt=$mysqli->prepare("DELETE FROM reservations WHERE id=?");
  $stmt->bind_param('i',$id);
  if ($stmt->execute()) echo json_encode(['status'=>'success']);
  else echo json_encode(['status'=>'error','message'=>$stmt->error]);
  $stmt->close(); exit;
}

// -------- get_stats --------
if ($action === 'get_stats') {
  $today = date('Y-m-d'); $first = date('Y-m-01'); $endMonth = date('Y-m-t');
  $stats=['today'=>['count'=>0,'revenue'=>0.0],'month'=>['count'=>0,'revenue'=>0.0],'by_status'=>['pending'=>0,'approved'=>0,'cancelled'=>0,'completed'=>0]];
  $stmt=$mysqli->prepare("SELECT COUNT(*) c, IFNULL(SUM(total_amount),0) s FROM reservations WHERE reservation_date=?");
  $stmt->bind_param('s',$today); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); $stmt->close();
  $stats['today']=['count'=>(int)$r['c'],'revenue'=>(float)$r['s']];
  $stmt=$mysqli->prepare("SELECT COUNT(*) c, IFNULL(SUM(total_amount),0) s FROM reservations WHERE reservation_date>=? AND reservation_date<=?");
  $stmt->bind_param('ss',$first,$endMonth); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); $stmt->close();
  $stats['month']=['count'=>(int)$r['c'],'revenue'=>(float)$r['s']];
  $res=$mysqli->query("SELECT status, COUNT(*) c FROM reservations GROUP BY status"); while($row=$res->fetch_assoc()){ $stats['by_status'][$row['status']]=(int)$row['c']; }
  echo json_encode(['status'=>'success','stats'=>$stats]); exit;
}

// -------- export_csv --------
if ($action === 'export_csv') {
  $q=trim($_GET['q']??''); $status=$_GET['status']??''; $dateMin=$_GET['date_from']??''; $dateMax=$_GET['date_to']??'';
  $where=[];$params=[];$types='';
  if ($status!==''){ $where[]="status=?"; $params[]=$status; $types.='s'; }
  if ($dateMin!==''){ $where[]="reservation_date>=?"; $params[]=$dateMin; $types.='s'; }
  if ($dateMax!==''){ $where[]="reservation_date<=?"; $params[]=$dateMax; $types.='s'; }
  if ($q!==''){ $where[]="(resident_name LIKE CONCAT('%', ?, '%') OR contact_number LIKE CONCAT('%', ?, '%') OR reference_number LIKE CONCAT('%', ?, '%') OR activity LIKE CONCAT('%', ?, '%'))"; array_push($params,$q,$q,$q,$q); $types.='ssss'; }
  $whereSql=$where?('WHERE '.implode(' AND ',$where)):'';
  $stmt=$mysqli->prepare("SELECT reservation_date,resident_name,contact_number,activity,notes,reference_number,total_amount,status,time_slots,created_at FROM reservations $whereSql ORDER BY reservation_date DESC, created_at DESC");
  if($types)$stmt->bind_param($types,...$params);
  $stmt->execute(); $res=$stmt->get_result();
  set_csv_headers('reservations.csv');
  $out=fopen('php://output','w'); fputcsv($out,['Date','Resident','Contact','Activity','Notes','Reference','Total','Status','Slots','Created']);
  while($row=$res->fetch_assoc()){
    $slots=json_decode($row['time_slots'],true); $slotText='';
    if(is_array($slots)){ $slotText=implode(' | ',array_map(fn($s)=>(($s['time']??'').' ('.($s['rateType']??'').')'),$slots)); }
    fputcsv($out,[$row['reservation_date'],$row['resident_name'],$row['contact_number'],$row['activity'],$row['notes'],$row['reference_number'],number_format((float)$row['total_amount'],2,'.',''),$row['status'],$slotText,$row['created_at']]);
  }
  fclose($out); exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid action']);
