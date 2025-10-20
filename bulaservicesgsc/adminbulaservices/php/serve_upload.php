<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';

// Only logged-in admins can access
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$file = $_GET['file'] ?? '';
if (!$file) {
    http_response_code(400);
    exit('Missing file');
}

// Sanitize to avoid traversal
$file = str_replace(['..', '\\'], '', $file);

// Base uploads dir
$uploadsDir = realpath(__DIR__ . '/../../uploads');
if ($uploadsDir === false) {
    http_response_code(500);
    exit('Uploads directory not found');
}

// Build full path (DB already has subfolder e.g. "purok_clearances/...")
$fullPath = realpath($uploadsDir . '/' . $file);

if ($fullPath === false || !is_file($fullPath)) {
    error_log("serve_upload.php DEBUG: Tried path $fullPath");
    http_response_code(404);
    exit('File not found');
}

// Security: ensure still inside uploads dir
if (strpos($fullPath, $uploadsDir) !== 0) {
    http_response_code(403);
    exit('Invalid file path');
}

// Send file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fullPath);
finfo_close($finfo);

header("Content-Type: $mime");
header("Content-Length: " . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');

readfile($fullPath);
exit;
