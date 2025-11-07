<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json');

$DEBUG_MODE = false;
if ($DEBUG_MODE) { ini_set('display_errors', '1'); error_reporting(E_ALL); }

/* ===== AUTH: allow session OR header key ===== */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isAdminSession = isset($_SESSION['admin_id']);
$providedKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
$expectedKey = 'change-this-admin-key-123';
$isAdminKey = function_exists('hash_equals') ? hash_equals($expectedKey, $providedKey) : ($expectedKey === $providedKey);

if (!$isAdminSession && !$isAdminKey) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

/* ===== Load mailer (reuse your PHPMailer config) ===== */
$mailerLoaded = false;
$mailerPaths = [
    __DIR__ . '/../../userbulaservices/php/mailer.php',
    __DIR__ . '/../../userbulaservices/php/mailer_service.php',
];
foreach ($mailerPaths as $p) {
    if (is_file($p)) { require_once $p; $mailerLoaded = true; break; }
}

/* ===== helpers ===== */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}
function jinput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function ok($data = null, array $extra = []): void {
    // don’t let extras overwrite reserved keys
    unset($extra['status'], $extra['data']);

    // build response
    $resp = ['status' => 'success', 'data' => $data] + $extra;

    echo json_encode($resp);
    exit;
}
function err(string $message, $detail = null, int $code = 400): void {
    http_response_code($code);
    $res = ['status' => 'error', 'message' => $message];
    if ($detail !== null) $res['detail'] = $detail;
    echo json_encode($res);
    exit;
}
function hasCol(string $table, string $col): bool {
    $q = db()->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $q->execute([$table,$col]);
    return (bool)$q->fetchColumn();
}
function adminIdentity(): array {
    $id = $_SESSION['admin_id'] ?? null;
    $name = '';
    foreach (['admin_name','full_name','name','user_name'] as $k) {
        if (!empty($_SESSION[$k])) { $name = trim((string)$_SESSION[$k]); break; }
    }
    if (!$name && isset($_SESSION['admin_first_name'], $_SESSION['admin_last_name'])) {
        $name = trim($_SESSION['admin_first_name'].' '.$_SESSION['admin_last_name']);
    }
    if (!$name) $name = 'Barangay Staff';
    return ['id' => $id ? (int)$id : null, 'name' => $name];
}

/** stamp audit into columns if present, else append to notes */
function stampAction(int $id, string $kind, array $admin): void {
    $k = strtolower($kind);
    $map = [
        'approved'  => ['id'=>'approved_by_admin_id',  'name'=>'approved_by_name',  'at'=>'approved_at'],
        'processed' => ['id'=>'processed_by_admin_id', 'name'=>'processed_by_name', 'at'=>'processed_at'],
        'released'  => ['id'=>'released_by_admin_id',  'name'=>'released_by_name',  'at'=>'released_at'],
        'rejected'  => ['id'=>'rejected_by_admin_id',  'name'=>'rejected_by_name',  'at'=>'rejected_at'],
        'completed' => ['id'=>'processed_by_admin_id', 'name'=>'processed_by_name', 'at'=>'processed_at'],
    ];
    $cols = $map[$k] ?? null;

    $assigns = [];
    $params  = [];
    if ($cols) {
        if ($cols['id']   && hasCol('reservations', $cols['id']))   { $assigns[] = "`{$cols['id']}`=?";   $params[] = $admin['id']; }
        if ($cols['name'] && hasCol('reservations', $cols['name'])) { $assigns[] = "`{$cols['name']}`=?"; $params[] = $admin['name']; }
        if ($cols['at']   && hasCol('reservations', $cols['at']))   { $assigns[] = "`{$cols['at']}`=?";   $params[] = date('Y-m-d H:i:s'); }
    }

    if ($assigns) {
        $sql = "UPDATE reservations SET ".implode(',', $assigns)." WHERE id = ?";
        $params[] = $id;
        $st = db()->prepare($sql);
        $st->execute($params);
    } else {
        $entry = '['.ucfirst($k).' @ '.date('Y-m-d H:i:s').'] By: '.$admin['name']."\n";
        $sql = "UPDATE reservations SET notes = CONCAT(COALESCE(notes,''), :entry) WHERE id=:id";
        $st = db()->prepare($sql);
        $st->execute([':entry' => $entry, ':id' => $id]);
    }
}

function readTransactionRow(array $r): array {
    $tx = [
        'approved_by_name'  => null, 'approved_at'  => null,
        'processed_by_name' => null, 'processed_at' => null,
        'released_by_name'  => null, 'released_at'  => null,
    ];
    foreach ([['approved_by_name','approved_at'],['processed_by_name','processed_at'],['released_by_name','released_at']] as [$n,$t]) {
        if (isset($r[$n]) && $r[$n]) $tx[$n] = (string)$r[$n];
        if (isset($r[$t]) && $r[$t]) $tx[$t] = (string)$r[$t];
    }
    if ((!$tx['approved_by_name'] || !$tx['approved_at'] || !$tx['processed_by_name'] || !$tx['processed_at'] || !$tx['released_by_name'] || !$tx['released_at']) && !empty($r['notes'])) {
        $lines = preg_split('/\r?\n/', (string)$r['notes']);
        foreach ($lines as $line) {
            if (preg_match('/^\[(Approved|Processed|Released)\s*@\s*([0-9:\- ]+)\]\s*By:\s*(.+)$/i', trim($line), $m)) {
                $kind = strtolower($m[1]); $at = trim($m[2]); $who = trim($m[3]);
                if ($kind === 'approved')  { $tx['approved_by_name']  = $tx['approved_by_name']  ?: $who; $tx['approved_at']  = $tx['approved_at']  ?: $at; }
                if ($kind === 'processed') { $tx['processed_by_name'] = $tx['processed_by_name'] ?: $who; $tx['processed_at'] = $tx['processed_at'] ?: $at; }
                if ($kind === 'released')  { $tx['released_by_name']  = $tx['released_by_name']  ?: $who; $tx['released_at']  = $tx['released_at']  ?: $at; }
            }
        }
    }
    return $tx;
}

/* ---------- email helpers ---------- */
function findUserEmailForReservation(array $row): array {
    // Best bet: exact contact_number
    $email = '';
    $name  = trim((string)($row['resident_name'] ?? ''));
    $phone = trim((string)($row['contact_number'] ?? ''));
    if ($phone !== '') {
        $q = db()->prepare("SELECT email, first_name, last_name FROM users WHERE contact_number = ? LIMIT 1");
        $q->execute([$phone]);
        if ($u = $q->fetch(PDO::FETCH_ASSOC)) {
            $email = (string)$u['email'];
            $name  = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: $name;
            return [$email, $name];
        }
    }
    // Fallback: try to split resident_name and match
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name);
        $first = $parts[0] ?? '';
        $last  = $parts[count($parts)-1] ?? '';
        if ($first && $last) {
            $q = db()->prepare("SELECT email, first_name, last_name FROM users WHERE LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?) LIMIT 1");
            $q->execute([$first, $last]);
            if ($u = $q->fetch(PDO::FETCH_ASSOC)) {
                $email = (string)$u['email'];
                $name  = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: $name;
            }
        }
    }
    return [$email, $name];
}

function slotLabelFromJson($timeSlots): string {
    $slots = is_array($timeSlots) ? $timeSlots : [];
    $labels = [];
    foreach ($slots as $s) {
        if (is_array($s)) {
            if (!empty($s['time'])) { $labels[] = trim((string)$s['time']); continue; }
            if (isset($s['hour']) && is_numeric($s['hour'])) {
                $h = (int)$s['hour'];
                $labels[] = slotFromHour($h);
            }
        } elseif (is_string($s) && trim($s) !== '') {
            $labels[] = trim($s);
        }
    }
    return implode(' | ', $labels);
}
function slotFromHour(int $h): string {
    $fmt = function($x){ $p = $x>=12?'PM':'AM'; $dh = $x>12? $x-12 : ($x===0?12:$x); return $dh.':00 '.$p; };
    return $fmt($h).' - '.$fmt($h+1);
}

function sendReservationEmail(array $row, string $newStatus, ?string $reason, string $adminName, bool $mailerLoaded): void {
    if (!$mailerLoaded) return;

    [$toEmail, $userName] = findUserEmailForReservation($row);
    if (trim($toEmail) === '') return; // nothing to send to

    $ref = (string)($row['reference_number'] ?? '');
    $date = isset($row['reservation_date']) ? date('F j, Y', strtotime((string)$row['reservation_date'])) : '—';
    $slotText = slotLabelFromJson(json_decode((string)($row['time_slots'] ?? '[]'), true) ?: []);
    $venue = 'Barangay Bula Gymnasium';
    $amount = isset($row['total_amount']) ? number_format((float)$row['total_amount'], 2) : null;

    if ($newStatus === 'approved') {
        $subject = "Your Gym Reservation {$ref} is Approved";
        $moneyLine = $amount ? "<p><b>Amount Due:</b> ₱{$amount}</p>" : "";
        $html = "
          <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.55'>
            <p>Dear {$userName},</p>
            <p>Your <b>Gym Reservation</b> with reference <b>{$ref}</b> has been <b>approved</b> by {$adminName}.</p>
            <p><b>Schedule:</b><br>Date: {$date}<br>Time: {$slotText}<br>Venue: {$venue}</p>
            {$moneyLine}
            <p>Please proceed to the barangay hall for payment and final confirmation (bring valid ID and your reference number).</p>
            <p style='color:#6b7280'>Thank you for using Barangay Bula Online Services.</p>
          </div>";
        $alt =
          "Dear {$userName},\n\n".
          "Your Gym Reservation {$ref} has been approved.\n\n".
          "Schedule:\nDate: {$date}\nTime: {$slotText}\nVenue: {$venue}\n".
          ($amount ? "Amount Due: ₱{$amount}\n" : "").
          "\nPlease proceed to the barangay hall for payment and confirmation.";
    } elseif ($newStatus === 'rejected') {
        $subject = "Your Gym Reservation {$ref} is Rejected";
        $reasonLine = ($reason && trim($reason) !== '') ? "<p><b>Reason:</b> ".htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')."</p>" : "";
        $html = "
          <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.55'>
            <p>Dear {$userName},</p>
            <p>We regret to inform you that your <b>Gym Reservation</b> with reference <b>{$ref}</b> has been <b>rejected</b>.</p>
            {$reasonLine}
            <p>If you believe this is an error or need clarification, please contact the barangay office.</p>
            <p style='color:#6b7280'>Thank you for your understanding.</p>
          </div>";
        $alt =
          "Dear {$userName},\n\n".
          "Your Gym Reservation {$ref} has been rejected.\n".
          ($reason && trim($reason) !== '' ? "Reason: {$reason}\n" : "").
          "\nFor questions, please contact the barangay office.";
    } else {
        return; // only mail on approve/reject
    }

    try {
        if (function_exists('sendEmailGeneric')) {
            $r = sendEmailGeneric($toEmail, $userName, $subject, $html, $alt);
            if (!($r['ok'] ?? false)) error_log('gym mail failed: '.$r['error']);
        } elseif (function_exists('sendServiceStatusEmail')) {
            $r = sendServiceStatusEmail($toEmail, $userName, $subject, $html, $alt);
            if (!($r['ok'] ?? false)) error_log('gym mail failed: '.$r['error']);
        }
    } catch (Throwable $e) {
        error_log('MAIL ERROR: '.$e->getMessage());
    }
}

/* ===== router ===== */
$in = jinput();
$action = $in['action'] ?? '';

try {
    if ($action === 'ping') {
        ok(['pong' => true, 'auth' => ($isAdminSession || $isAdminKey)]);
    }

    if ($action === 'list_reservations') {
        $perPage = isset($in['per_page']) ? max(1, (int)$in['per_page']) : 100;
        $page    = isset($in['page']) ? max(1, (int)$in['page']) : 1;
        $offset  = ($page - 1) * $perPage;

        // allowlist per your enum
        $allowedStatuses = ['pending','approved','paid','rejected','completed'];
        $filterStatus = strtolower(trim((string)($in['status'] ?? 'pending')));
        if (!in_array($filterStatus, $allowedStatuses, true)) {
            $filterStatus = 'pending';
        }

        $sel = [
            "id",
            "COALESCE(reference_number,'') AS reference_number",
            "COALESCE(activity,'') AS activity",
            "COALESCE(resident_name,'') AS resident_name",
            "COALESCE(contact_number,'') AS contact_number",
            "reservation_date",
            "COALESCE(time_slots, '[]') AS time_slots",
            "COALESCE(total_amount, 0) AS total_amount",
            "COALESCE(status,'') AS status",
            "COALESCE(notes,'') AS notes"
        ];
        foreach (['approved_by_name','approved_at','processed_by_name','processed_at','released_by_name','released_at'] as $c) {
            if (hasCol('reservations', $c)) $sel[] = $c;
        }

        // all positional placeholders (no mixing)
        $sql = "
            SELECT ".implode(',', $sel)."
            FROM reservations
            WHERE LOWER(COALESCE(status,'')) = ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = db()->prepare($sql);
        $stmt->bindValue(1, $filterStatus, PDO::PARAM_STR);
        $stmt->bindValue(2, (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$offset,  PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $decoded = json_decode($r['time_slots'], true);
            $r['time_slots']  = is_array($decoded) ? $decoded : [];
            $r['transaction'] = readTransactionRow($r);
        }
        ok($rows, ['page' => $page, 'per_page' => $perPage, 'status' => $filterStatus]);
    }

    if ($action === 'get_by_reference') {
        $ref = trim((string)($in['reference'] ?? ''));
        if ($ref === '') err('Missing reference number');

        $sel = [
            "id",
            "COALESCE(reference_number,'') AS reference_number",
            "COALESCE(activity,'') AS activity",
            "COALESCE(resident_name,'') AS resident_name",
            "COALESCE(contact_number,'') AS contact_number",
            "reservation_date",
            "COALESCE(time_slots, '[]') AS time_slots",
            "COALESCE(total_amount, 0) AS total_amount",
            "COALESCE(status,'') AS status",
            "COALESCE(notes,'') AS notes"
        ];
        foreach (['approved_by_name','approved_at','processed_by_name','processed_at','released_by_name','released_at'] as $c) {
            if (hasCol('reservations', $c)) $sel[] = $c;
        }

        $sql = "SELECT ".implode(',', $sel)." FROM reservations WHERE reference_number = ? LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) err('Reservation not found', null, 404);

        $decoded = json_decode($row['time_slots'], true);
        $row['time_slots'] = is_array($decoded) ? $decoded : [];
        $row['transaction'] = readTransactionRow($row);

        ok($row);
    }

    if ($action === 'update_status') {
        $id = (int)($in['id'] ?? 0);
        $status = strtolower(trim((string)($in['status'] ?? '')));
        $reason = isset($in['reason']) ? trim((string)$in['reason']) : null;

        if ($id <= 0) err('Invalid id');
        if (!in_array($status, ['approved', 'rejected', 'completed'], true)) {
            err('Invalid status value');
        }

        // get current row for email context
        $rowStmt = db()->prepare("SELECT * FROM reservations WHERE id = ? LIMIT 1");
        $rowStmt->execute([$id]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) err('Reservation not found', null, 404);

        $current = strtolower(trim((string)($row['status'] ?? 'requested')));
        if (in_array($current, ['completed', 'cancelled', 'canceled'], true)) {
            err('Reservation already finalized');
        }

        $admin = adminIdentity();

        if ($status === 'rejected') {
            $sql = "
                UPDATE reservations
                   SET status = :status,
                       notes  = CONCAT(COALESCE(notes,''), :entry)
                 WHERE id = :id
            ";
            $entry = '[Rejected @ ' . date('Y-m-d H:i:s') . '] By: ' . $admin['name'] .
                     ($reason ? (' | Reason: ' . $reason) : '') . "\n";
            $st = db()->prepare($sql);
            $st->execute([':status'=>$status, ':entry'=>$entry, ':id'=>$id]);
            stampAction($id, 'rejected', $admin);

            // send email (reject)
            sendReservationEmail(array_merge($row, ['status'=>$status]), 'rejected', $reason, $admin['name'], $mailerLoaded);

        } elseif ($status === 'approved') {
            $st = db()->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $st->execute([$status, $id]);
            stampAction($id, 'approved', $admin);

            // send email (approve)
            sendReservationEmail(array_merge($row, ['status'=>$status]), 'approved', null, $admin['name'], $mailerLoaded);

        } elseif ($status === 'completed') {
            $st = db()->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $st->execute([$status, $id]);
            stampAction($id, 'processed', $admin);
            // no email on completed by default
        }

        ok(['id' => $id, 'status' => $status]);
    }

    err('Unknown action');
} catch (Throwable $e) {
    if ($DEBUG_MODE) error_log('gymadback error: ' . $e->getMessage());
    err('Server error', $e->getMessage(), 500);
}
