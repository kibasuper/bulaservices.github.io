<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('Content-Type: application/json');

$DEBUG_MODE = false;
if ($DEBUG_MODE) { ini_set('display_errors', '1'); error_reporting(E_ALL); }

/* ===== AUTH: allow session OR header key ===== */
$isAdminSession = isset($_SESSION['admin_id']);
$providedKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
$expectedKey = 'change-this-admin-key-123';
$isAdminKey = function_exists('hash_equals') ? hash_equals($expectedKey, $providedKey) : ($expectedKey === $providedKey);

if (!$isAdminSession && !$isAdminKey) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
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
    echo json_encode(array_merge(['status' => 'success', 'data' => $data], $extra));
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
    // Try session-based admin identity
    $id = $_SESSION['admin_id'] ?? null;
    $name = '';
    // Common session keys used in many setups
    foreach (['admin_name','full_name','name','user_name'] as $k) {
        if (!empty($_SESSION[$k])) { $name = trim((string)$_SESSION[$k]); break; }
    }
    // Fallback: if you keep admins in users table
    if ((!$name || !$id) && isset($_SESSION['user_id'])) {
        $id = $id ?: (int)$_SESSION['user_id'];
        try {
            $st = db()->prepare("SELECT CONCAT(first_name,' ',last_name) AS n FROM users WHERE id=? LIMIT 1");
            $st->execute([$id]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) $name = trim((string)($row['n'] ?? ''));
        } catch (Throwable $e) {}
    }
    if (!$name) $name = 'Barangay Staff';
    return ['id' => $id ? (int)$id : null, 'name' => $name];
}

/**
 * Store an audit mark either into dedicated columns (if present)
 * or append a readable entry into notes.
 */
function stampAction(int $id, string $kind, array $admin): void {
    $k = strtolower($kind); // 'approved' | 'processed' | 'released' | 'rejected' | 'completed'

    // Column naming that we will try to set if they exist
    $map = [
        'approved'  => ['id'=>'approved_by_admin_id',  'name'=>'approved_by_name',  'at'=>'approved_at'],
        'processed' => ['id'=>'processed_by_admin_id', 'name'=>'processed_by_name', 'at'=>'processed_at'],
        'released'  => ['id'=>'released_by_admin_id',  'name'=>'released_by_name',  'at'=>'released_at'],
        'rejected'  => ['id'=>'rejected_by_admin_id',  'name'=>'rejected_by_name',  'at'=>'rejected_at'],
        'completed' => ['id'=>'processed_by_admin_id', 'name'=>'processed_by_name', 'at'=>'processed_at'], // treat complete as processed
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
        // Fallback: add a readable line into notes
        $entry = '['.ucfirst($k).' @ '.date('Y-m-d H:i:s').'] By: '.$admin['name']."\n";
        $sql = "UPDATE reservations SET notes = CONCAT(COALESCE(notes,''), :sep, :entry) WHERE id=:id";
        $st = db()->prepare($sql);
        $st->execute([
            ':sep' => '',
            ':entry' => $entry,
            ':id' => $id
        ]);
    }
}

/**
 * Read transaction details from columns if available; otherwise try to parse notes.
 */
function readTransactionRow(array $r): array {
    $tx = [
        'approved_by_name'  => null, 'approved_at'  => null,
        'processed_by_name' => null, 'processed_at' => null,
        'released_by_name'  => null, 'released_at'  => null,
    ];

    // Prefer columns if present
    $pairs = [
        ['approved_by_name','approved_at'],
        ['processed_by_name','processed_at'],
        ['released_by_name','released_at'],
    ];
    foreach ($pairs as [$nameCol,$atCol]) {
        if (isset($r[$nameCol]) && $r[$nameCol]) $tx[$nameCol] = (string)$r[$nameCol];
        if (isset($r[$atCol])   && $r[$atCol])   $tx[$atCol]   = (string)$r[$atCol];
    }

    // If still empty and we have notes, try coarse parse
    if ((!$tx['approved_by_name'] || !$tx['approved_at']
        || !$tx['processed_by_name'] || !$tx['processed_at']
        || !$tx['released_by_name'] || !$tx['released_at'])
        && !empty($r['notes'])) {

        $lines = preg_split('/\r?\n/', (string)$r['notes']);
        foreach ($lines as $line) {
            if (preg_match('/^\[(Approved|Processed|Released)\s*@\s*([0-9:\- ]+)\]\s*By:\s*(.+)$/i', trim($line), $m)) {
                $kind = strtolower($m[1]);
                $at   = trim($m[2]);
                $who  = trim($m[3]);
                if ($kind === 'approved') { $tx['approved_by_name'] = $tx['approved_by_name'] ?: $who; $tx['approved_at'] = $tx['approved_at'] ?: $at; }
                if ($kind === 'processed'){ $tx['processed_by_name']= $tx['processed_by_name']?: $who; $tx['processed_at']= $tx['processed_at'] ?: $at; }
                if ($kind === 'released') { $tx['released_by_name'] = $tx['released_by_name'] ?: $who; $tx['released_at']  = $tx['released_at']  ?: $at; }
            }
        }
    }

    return $tx;
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
        $page = isset($in['page']) ? max(1, (int)$in['page']) : 1;
        $offset = ($page - 1) * $perPage;

        // Select base columns and optionally transaction columns
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

        // Optional transaction columns
        foreach ([
            'approved_by_name','approved_at',
            'processed_by_name','processed_at',
            'released_by_name','released_at'
        ] as $c) {
            if (hasCol('reservations', $c)) $sel[] = $c;
        }

        $sql = "
            SELECT ".implode(',', $sel)."
              FROM reservations
             WHERE TRIM(LOWER(COALESCE(status,''))) IN ('requested','pending','for_approval')
               AND (paid_at IS NULL OR paid_at = paid_at) -- tolerate missing column
             ORDER BY id DESC
             LIMIT :lim OFFSET :off
        ";
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', (int)$offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $decoded = json_decode($r['time_slots'], true);
            $r['time_slots'] = is_array($decoded) ? $decoded : [];
            $r['transaction'] = readTransactionRow($r);
        }

        ok($rows, ['page' => $page, 'per_page' => $perPage]);
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
        foreach ([
            'approved_by_name','approved_at',
            'processed_by_name','processed_at',
            'released_by_name','released_at'
        ] as $c) {
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

        $stmt = db()->prepare("SELECT COALESCE(status,'') FROM reservations WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $current = strtolower(trim((string)$stmt->fetchColumn()));
        if ($current === '') $current = 'requested';

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
        } elseif ($status === 'approved') {
            $st = db()->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $st->execute([$status, $id]);
            stampAction($id, 'approved', $admin);
        } elseif ($status === 'completed') {
            $st = db()->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $st->execute([$status, $id]);
            // treat "completed" as processed by (there is no release step for gym)
            stampAction($id, 'processed', $admin);
        }

        ok(['id' => $id, 'status' => $status]);
    }

    err('Unknown action');
} catch (Throwable $e) {
    if ($DEBUG_MODE) error_log('gymadback error: ' . $e->getMessage());
    err('Server error', $e->getMessage(), 500);
}
