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

// Helper map for nicer labels
function requirement_label(string $key): string {
    $map = [
        'purok_clearance' => 'Purok Clearance',
        'valid_id'        => 'Valid ID (Government-issued)',
        'cedula'          => 'Community Tax Certificate (Cedula)',
        // future-proof: add more when you enable other services
    ];
    return $map[$key] ?? ucwords(str_replace('_', ' ', $key));
}

try {
    $stmt = $db->prepare("
        SELECT sr.*,
               u.first_name, u.last_name, u.email, u.contact_number, u.address
        FROM service_requests sr
        JOIN users u ON sr.user_id = u.id
        WHERE sr.reference_number = ?
        LIMIT 1
    ");
    $stmt->execute([$ref]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Legacy single-file URL
    if (!empty($request['document_path'])) {
        $request['document_url'] = "/php/serve_upload.php?file=" . urlencode($request['document_path']);
    } else {
        $request['document_url'] = null;
    }

    // NEW: parse multi-requirements stored in extra_data->requirements
    $request['requirements'] = [];
    if (!empty($request['extra_data'])) {
        $extra = json_decode($request['extra_data'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($extra)) {
            $bundle = $extra['requirements'] ?? [];
            if (is_array($bundle)) {
                foreach ($bundle as $key => $meta) {
                    $method = strtolower((string)($meta['method'] ?? ''));
                    $path   = isset($meta['path']) ? (string)$meta['path'] : null;
                    $mime   = isset($meta['mime']) ? (string)$meta['mime'] : null;
                    $size   = isset($meta['size']) ? (int)$meta['size'] : null;

                    $url = null;
                    if ($method === 'upload' && $path) {
                        $url = "/php/serve_upload.php?file=" . urlencode($path);
                    }

                    $request['requirements'][] = [
                        'key'    => $key,
                        'label'  => requirement_label($key),
                        'method' => in_array($method, ['upload','hall'], true) ? $method : 'unknown',
                        'url'    => $url,
                        'mime'   => $mime,
                        'size'   => $size,
                        'path'   => $path, // keep raw for troubleshooting
                    ];
                }
            }
        }
    }

    echo json_encode(['success' => true, 'request' => $request]);
} catch (Exception $e) {
    error_log("Get request details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
