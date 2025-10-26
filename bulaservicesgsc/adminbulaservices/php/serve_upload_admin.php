<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';

// Require ADMIN login (keeps files private to the admin area)
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// 1) read & sanitize the relative path from query
$rel = $_GET['file'] ?? '';
if (!$rel) {
    http_response_code(400);
    exit('Missing file');
}
$rel = ltrim($rel, '/');
$rel = str_replace(['..', '\\'], '', $rel);

// allow callers to pass "/uploads/..." or "uploads/..."
if (strpos($rel, 'uploads/') === 0) {
    $rel = substr($rel, strlen('uploads/')); // -> "profile_pictures/xxx.jpg"
}

// 2) base uploads dir from config constant (same as user side)
$uploadsDir = realpath(UPLOADS_DIR);
if ($uploadsDir === false) {
    error_log('serve_upload_admin: UPLOADS_DIR not found: ' . (defined('UPLOADS_DIR') ? UPLOADS_DIR : '(undefined)'));
    http_response_code(500);
    exit('Uploads directory not found');
}

// 3) build & validate full path
$fullPath = realpath($uploadsDir . DIRECTORY_SEPARATOR . $rel);
if ($fullPath === false || !is_file($fullPath)) {
    error_log("serve_upload_admin: 404 rel='$rel' base='$uploadsDir' full='".($fullPath ?: 'false')."'");
    http_response_code(404);
    exit('Not Found');
}
if (strpos($fullPath, $uploadsDir) !== 0) {
    http_response_code(403);
    exit('Invalid path');
}

// 4) stream the file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Cache-Control: private, max-age=604800');
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;
