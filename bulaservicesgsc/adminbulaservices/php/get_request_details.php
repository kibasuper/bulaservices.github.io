<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php'; // loads $db and session
header('Content-Type: application/json');

// Only allow logged-in admins
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get reference number
$ref = $_GET['ref'] ?? $_POST['ref'] ?? '';
if (!$ref) {
    echo json_encode(['success' => false, 'message' => 'Reference number is required']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT sr.*, 
            u.first_name, 
            u.last_name, 
            u.email, 
            u.contact_number, 
            u.address
        FROM service_requests sr
        JOIN users u ON sr.user_id = u.id
        WHERE sr.reference_number = ?
    ");
    $stmt->execute([$ref]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Fix document path -> public URL (with ref + file)
    if (!empty($request['document_path'])) {
        $request['document_url'] = "/php/serve_upload.php?file=" . urlencode($request['document_path']);
    } else {
        $request['document_url'] = null;
    }


    echo json_encode(['success' => true, 'request' => $request]);

} catch (Exception $e) {
    error_log("Get request details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
