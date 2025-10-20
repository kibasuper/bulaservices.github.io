<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const DEBUG = true;

// Diagnostics
if (isset($_GET['diag']) && $_GET['diag'] === '1') {
    $out = [
        'session_has_admin' => !empty($_SESSION['admin_id']),
        'session_admin_id'  => $_SESSION['admin_id'] ?? null,
        'have_getDBConnection' => function_exists('getDBConnection'),
        'cwd' => __DIR__,
        'time' => date('c'),
    ];
    try {
        if (!function_exists('getDBConnection')) {
            throw new RuntimeException('getDBConnection() not found in server/config.php');
        }
        $db = getDBConnection();
        if (!$db instanceof PDO) throw new RuntimeException('DB is not PDO instance');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $cnt = (int)$db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();
        $out['service_requests_count'] = $cnt;

        $cols = $db->query("SHOW COLUMNS FROM service_requests")->fetchAll(PDO::FETCH_COLUMN, 0);
        $out['service_requests_columns'] = $cols;

        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['ok'] = false;
        $out['error'] = $e->getMessage();
    }
    echo json_encode($out);
    exit;
}

// Admin only
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if (!function_exists('getDBConnection')) {
        throw new RuntimeException('getDBConnection() not found (check server/config.php include path).');
    }
    $db = getDBConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('DB connection failed or not PDO.');
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read payload: JSON first, fall back to form
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true);
    if (!is_array($in)) { $in = $_POST; }

    $ref    = isset($in['ref'])    ? trim((string)$in['ref'])    : '';
    $action = isset($in['action']) ? trim((string)$in['action']) : '';
    $notes  = isset($in['notes'])  ? trim((string)$in['notes'])  : '';
    $reason = isset($in['reason']) ? trim((string)$in['reason']) : '';

    if ($ref === '' || !in_array($action, ['approve','reject'], true)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid request: ref/action required']);
        exit;
    }

    // Fetch row
    $stmt = $db->prepare("SELECT id, service_type, status, reference_number FROM service_requests WHERE reference_number = ? LIMIT 1");
    $stmt->execute([$ref]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) { echo json_encode(['success'=>false,'message'=>'Request not found']); exit; }

    $cur = strtolower((string)($req['status'] ?? ''));
    $adminId = (int)$_SESSION['admin_id'];

    $isWaitingApproval = in_array($cur, ['waiting_approval','pending','new','submitted','for_review','approved'], true);
    $isFinal           = in_array($cur, ['completed','rejected'], true);

    if ($isFinal) {
        echo json_encode(['success'=>false,'message'=>"Request already {$cur}."]);
        exit;
    }

    // Discover columns
    $cols = $db->query("SHOW COLUMNS FROM service_requests")->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasApprovedBy = in_array('approved_by', $cols, true);

    if ($action === 'approve') {
        if (!$isWaitingApproval) {
            echo json_encode(['success'=>false,'message'=>"Cannot approve from status '{$cur}'"]);
            exit;
        }

        $sql  = "UPDATE service_requests
                    SET status = 'processing',
                        approved_date = COALESCE(approved_date, NOW()),";
        $args = [];

        if ($hasApprovedBy) {
            $sql .= " approved_by = :admin_id,";
            $args[':admin_id'] = $adminId;
        }

        $sql .= " admin_notes = CASE WHEN :notesA <> '' 
                          THEN CONCAT(COALESCE(admin_notes,''), 
                               CASE WHEN admin_notes IS NULL OR admin_notes='' THEN '' ELSE '\n' END, :notesB)
                          ELSE admin_notes END
                  WHERE reference_number = :ref
                  LIMIT 1";

        $args[':notesA'] = $notes; // may be empty (no change)
        $args[':notesB'] = $notes;
        $args[':ref']    = $ref;

        $stmt = $db->prepare($sql);
        $stmt->execute($args);

        echo json_encode([
            'success'     => true,
            'message'     => 'Request approved. Status is now Processing. Please instruct the resident to pay over-the-counter at the barangay.',
            'reference'   => $ref,
            'new_status'  => 'processing',
            'payment_tip' => 'Over-the-counter only. Bring valid ID and this reference number.'
        ]);
        exit;
    }

    if ($action === 'reject') {
        // Allow rejection with NO reason / NO notes
        // Set rejected_reason to NULL (or keep previous) if not provided
        $sql = "
            UPDATE service_requests
               SET status = 'rejected',
                   rejected_reason = :reason,
                   admin_notes     = CASE WHEN :notesA <> '' 
                                     THEN CONCAT(COALESCE(admin_notes,''), 
                                          CASE WHEN admin_notes IS NULL OR admin_notes='' THEN '' ELSE '\n' END, :notesB)
                                     ELSE admin_notes END
             WHERE reference_number = :ref
             LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        // If you prefer empty string instead of NULL for no reason, change PDO::PARAM_NULL usage
        $reasonParam = ($reason === '') ? null : $reason;
        $stmt->bindValue(':reason', $reasonParam, $reason === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':notesA', $notes);
        $stmt->bindValue(':notesB', $notes);
        $stmt->bindValue(':ref', $ref);
        $stmt->execute();

        echo json_encode([
            'success'    => true,
            'message'    => 'Request rejected.',
            'reference'  => $ref,
            'new_status' => 'rejected'
        ]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);

} catch (Throwable $e) {
    error_log('approve_request error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'message'=> DEBUG ? ('Server error: '.$e->getMessage()) : 'Server error'
    ]);
}
