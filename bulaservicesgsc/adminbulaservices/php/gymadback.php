<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php'; // $db (PDO) + session_start()
header('Content-Type: application/json');

/* ===== DEBUG (turn off in prod) ===== */
$DEBUG_MODE = false; // set true only while diagnosing
if ($DEBUG_MODE) { ini_set('display_errors', '1'); error_reporting(E_ALL); }

/* ===== AUTH: allow session OR header key ===== */
$isAdminSession = isset($_SESSION['admin_id']);
$providedKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
$expectedKey = 'change-this-admin-key-123'; // must match window.ADMIN_API_KEY
$isAdminKey = function_exists('hash_equals') ? hash_equals($expectedKey, $providedKey) : ($expectedKey === $providedKey);

if (!$isAdminSession && !$isAdminKey) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

/* ===== helpers ===== */
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

/* ===== router ===== */
$in = jinput();
$action = $in['action'] ?? '';

try {
    /* quick health check (optional) */
    if ($action === 'ping') {
        ok(['pong' => true, 'auth' => ($isAdminSession || $isAdminKey)]);
    }

    /* ===== LIST: only items that still need action =====
       Case-insensitive, trim-aware filter. Excludes statuses: approved, cancelled, completed (any casing / trailing spaces).
       Sort by id DESC (doesn't require a created_at column).
    */
    if ($action === 'list_reservations') {
        $perPage = isset($in['per_page']) ? max(1, (int)$in['per_page']) : 100;
        $page = isset($in['page']) ? max(1, (int)$in['page']) : 1;
        $offset = ($page - 1) * $perPage;
        $perPage = (int)$perPage; $offset = (int)$offset;

        $sql = "
            SELECT
                id,
                COALESCE(reference_number,'') AS reference_number,
                COALESCE(activity,'') AS activity,
                COALESCE(resident_name,'') AS resident_name,
                COALESCE(contact_number,'') AS contact_number,
                reservation_date,
                COALESCE(time_slots, '[]') AS time_slots,
                COALESCE(total_amount, 0) AS total_amount,
                COALESCE(status,'') AS status,
                COALESCE(notes,'') AS notes
            FROM reservations
            WHERE
                -- Show ONLY items that still need action (pre-approval)
                TRIM(LOWER(COALESCE(status,''))) IN ('requested','pending','for_approval')
                -- And make sure nothing already paid slips back into this list
                AND (paid_at IS NULL)
            ORDER BY id DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $decoded = json_decode($r['time_slots'], true);
            $r['time_slots'] = is_array($decoded) ? $decoded : [];
        }

        ok($rows, ['page' => $page, 'per_page' => $perPage]);
    }

    /* ===== GET BY REFERENCE ===== */
    if ($action === 'get_by_reference') {
        $ref = trim((string)($in['reference'] ?? ''));
        if ($ref === '') err('Missing reference number');

        $stmt = $db->prepare("
            SELECT
                id,
                COALESCE(reference_number,'') AS reference_number,
                COALESCE(activity,'') AS activity,
                COALESCE(resident_name,'') AS resident_name,
                COALESCE(contact_number,'') AS contact_number,
                reservation_date,
                COALESCE(time_slots, '[]') AS time_slots,
                COALESCE(total_amount, 0) AS total_amount,
                COALESCE(status,'') AS status,
                COALESCE(notes,'') AS notes
            FROM reservations
            WHERE reference_number = ?
            LIMIT 1
        ");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) err('Reservation not found', null, 404);

        $decoded = json_decode($row['time_slots'], true);
        $row['time_slots'] = is_array($decoded) ? $decoded : [];
        ok($row);
    }

    /* ===== UPDATE STATUS: approved | cancelled | completed ===== */
    if ($action === 'update_status') {
        $id = (int)($in['id'] ?? 0);
        $status = strtolower(trim((string)($in['status'] ?? '')));
        $reason = isset($in['reason']) ? trim((string)$in['reason']) : null;

        if ($id <= 0) err('Invalid id');
       if (!in_array($status, ['approved', 'rejected', 'completed'], true)) {
    err('Invalid status value');
}


        // read current status
        $stmt = $db->prepare("SELECT status FROM reservations WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) err('Reservation not found', null, 404);

        $currentLower = strtolower(trim((string)$current));
        if (in_array($currentLower, ['completed', 'cancelled'], true)) {
            err('Reservation already finalized');
        }

        if ($status === 'rejected' && $reason) {
            // use all-named placeholders (avoid mixing styles)
            $sql = "
                UPDATE reservations
                   SET status = :status,
                       notes = CONCAT(COALESCE(notes,''), :sep, :entry)
                 WHERE id = :id
            ";
            $sep = ($currentLower !== '' ? "\n" : '');
            $entry = '[Cancelled @ ' . date('Y-m-d H:i:s') . '] Reason: ' . $reason . "\n";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':sep' => $sep,
                ':entry' => $entry,
                ':id' => $id,
            ]);
        } else {
            $stmt = $db->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }

        ok(['id' => $id, 'status' => $status]);
    }

    /* ===== unknown action ===== */
    err('Unknown action');
} catch (Throwable $e) {
    error_log('gymadback error: ' . $e->getMessage());
    err('Server error', $e->getMessage(), 500);
}
