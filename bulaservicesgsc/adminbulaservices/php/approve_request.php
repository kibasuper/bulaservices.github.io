<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const DEBUG = true;

/* -------------------------------------------------------
   Load mailer helper safely (no config conflict)
------------------------------------------------------- */
$mailerLoaded = false;
$mailerPaths = [
    __DIR__ . '/../../userbulaservices/php/mailer.php', // primary user mailer
    __DIR__ . '/../../userbulaservices/php/mailer_service.php' // optional alternate name
];
foreach ($mailerPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        $mailerLoaded = true;
        break;
    }
}

/* -------------------------------------------------------
   Access control
------------------------------------------------------- */
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDBConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) $input = $_POST;

    $ref    = trim((string)($input['ref'] ?? ''));
    $action = trim((string)($input['action'] ?? ''));
    $notes  = trim((string)($input['notes'] ?? ''));
    $reason = trim((string)($input['reason'] ?? ''));

    if ($ref === '' || !in_array($action, ['approve', 'reject'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // Fetch service request
    $stmt = $db->prepare("
        SELECT sr.*, u.email AS user_email, u.first_name AS user_first, u.last_name AS user_last
        FROM service_requests sr
        JOIN users u ON u.id = sr.user_id
        WHERE sr.reference_number = ? LIMIT 1
    ");
    $stmt->execute([$ref]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    $curStatus = strtolower((string)$req['status']);
    if (in_array($curStatus, ['completed', 'rejected'], true)) {
        echo json_encode(['success' => false, 'message' => "Request already {$curStatus}."]);
        exit;
    }

    $adminId = (int)$_SESSION['admin_id'];
    $newStatus = '';
    $successMsg = '';

    /* =====================================================
       APPROVE
    ===================================================== */
    if ($action === 'approve') {
        $sql = "
            UPDATE service_requests
               SET status = 'approved',
                   approved_date = NOW(),
                   processed_date = NOW(),
                   approved_by = :admin_id,
                   admin_notes = CASE WHEN :notesA <> '' 
                       THEN CONCAT(COALESCE(admin_notes,''), 
                           CASE WHEN admin_notes IS NULL OR admin_notes='' THEN '' ELSE '\n' END, :notesB)
                       ELSE admin_notes END
             WHERE reference_number = :ref
             LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':admin_id' => $adminId,
            ':notesA'   => $notes,
            ':notesB'   => $notes,
            ':ref'      => $ref
        ]);
        $newStatus = 'approved';
        $successMsg = 'Request approved successfully.';
    }

    /* =====================================================
       REJECT
    ===================================================== */
    if ($action === 'reject') {
        $sql = "
            UPDATE service_requests
               SET status = 'rejected',
                   rejected_reason = :reason,
                   admin_notes = CASE WHEN :notesA <> '' 
                       THEN CONCAT(COALESCE(admin_notes,''), 
                           CASE WHEN admin_notes IS NULL OR admin_notes='' THEN '' ELSE '\n' END, :notesB)
                       ELSE admin_notes END
             WHERE reference_number = :ref
             LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':reason' => $reason !== '' ? $reason : null,
            ':notesA' => $notes,
            ':notesB' => $notes,
            ':ref'    => $ref
        ]);
        $newStatus = 'rejected';
        $successMsg = 'Request rejected.';
    }

    /* -------------------------------------------------------
       Send Email Notification (optional)
    ------------------------------------------------------- */
    $toEmail = trim((string)($req['user_email'] ?? ''));
    $userName = trim(($req['user_first'] ?? '') . ' ' . ($req['user_last'] ?? ''));

    $a = $db->prepare("SELECT first_name, last_name FROM admins WHERE admin_id = ? LIMIT 1");
    $a->execute([$adminId]);
    $admin = $a->fetch(PDO::FETCH_ASSOC);
    $adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));

    if ($mailerLoaded && $toEmail !== '') {
        $subject = '';
        $body = '';
        $alt = '';

        if ($newStatus === 'approved') {
            $subject = "Your {$req['service_type']} request has been approved";
            $body = "
                <p>Dear {$userName},</p>
                <p>Your service request <strong>{$ref}</strong> for 
                <strong>" . ucwords(str_replace('_', ' ', $req['service_type'])) . "</strong> 
                has been <b>approved</b> by {$adminName}.</p>
                <p>You may now proceed to the barangay hall for payment and processing.</p>
                <p><i>Thank you for using Barangay Bula Online Services.</i></p>";
            $alt = strip_tags($body);
        } elseif ($newStatus === 'rejected') {
            $subject = "Your {$req['service_type']} request has been rejected";
            $reasonText = $reason !== '' ? "<p><b>Reason:</b> " . htmlspecialchars($reason) . "</p>" : '';
            $body = "
                <p>Dear {$userName},</p>
                <p>We regret to inform you that your request <strong>{$ref}</strong> for 
                <strong>" . ucwords(str_replace('_', ' ', $req['service_type'])) . "</strong> 
                has been <b>rejected</b>.</p>
                {$reasonText}
                <p><i>For further clarification, you may contact the barangay office.</i></p>";
            $alt = strip_tags($body);
        }

        try {
            if (function_exists('sendEmailGeneric')) {
                $res = sendEmailGeneric($toEmail, $userName, $subject, $body, $alt);
                if (!$res['ok']) error_log('approve_request mail failed: ' . $res['error']);
            } elseif (function_exists('sendVerificationLink')) {
                // fallback if only verification mailer is available
                $fakeToken = bin2hex(random_bytes(8));
                sendVerificationLink($toEmail, $userName, $fakeToken);
            }
        } catch (Throwable $e) {
            error_log('MAIL ERROR: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $successMsg,
        'reference' => $ref,
        'new_status' => $newStatus
    ]);
    exit;

} catch (Throwable $e) {
    error_log('approve_request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => DEBUG ? 'Server error: ' . $e->getMessage() : 'Server error'
    ]);
}
