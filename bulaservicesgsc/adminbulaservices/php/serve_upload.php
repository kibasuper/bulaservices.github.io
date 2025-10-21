<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';

// Only logged-in admins can access
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// ---- INPUT ----
$raw = $_GET['file'] ?? '';
if ($raw === '' || !is_string($raw)) {
    http_response_code(400);
    exit('Missing file');
}

/**
 * Normalize the incoming path:
 * - strip any leading /uploads/
 * - remove backslashes, .. segments
 * - collapse duplicate slashes
 * - trim leading slashes
 */
$rel = preg_replace('#^/+#', '', $raw);                 // drop leading slashes
$rel = preg_replace('#^uploads/+#', '', $rel);          // if client passed /uploads/...
$rel = str_replace('\\', '/', $rel);                    // windows-style -> unix
$rel = preg_replace('#\.\.+#', '', $rel);               // remove any .. runs
$rel = preg_replace('#/{2,}#', '/', $rel);              // collapse //

// optional: map historical folder names (singular/plural) to current
$rel = preg_replace('#^purok_clearances/#', 'purok_clearance/', $rel); // old -> new

// Safety: do not allow path to resolve to empty or root-ish
$rel = ltrim($rel, '/');
if ($rel === '' || $rel === '.' ) {
    http_response_code(400);
    exit('Invalid file path');
}

// ---- ABSOLUTE UPLOADS ROOT (user side) ----
// /var/www/bulaservices/.../bulaservicesgsc/userbulaservices/uploads
$uploadsRootAbsolute = '/var/www/bulaservices/data/www/bulaservicesgsc.com/bulaservicesgsc/userbulaservices/uploads';

$uploadsDir = realpath($uploadsRootAbsolute);
if ($uploadsDir === false || !is_dir($uploadsDir)) {
    error_log('serve_upload.php ERROR: Uploads directory not found at '.$uploadsRootAbsolute);
    http_response_code(500);
    exit('Uploads directory not found');
}

// Build candidate path
$candidate = $uploadsDir . '/' . $rel;

// Resolve the real path of the *file*, but realpath fails if file doesn’t exist.
// So check existence first, then realpath on its dirname for containment check.
if (!is_file($candidate)) {
    error_log("serve_upload.php DEBUG: File not found candidate=$candidate rel=$rel");
    http_response_code(404);
    exit('File not found');
}

$realDir = realpath(dirname($candidate));
if ($realDir === false) {
    http_response_code(404);
    exit('File not found');
}

// Containment check: ensure the directory is inside uploads root
// Note: add trailing slash to avoid /uploads-evil matching
$uploadsDirSlash = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$realDirSlash    = rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($realDirSlash, $uploadsDirSlash) !== 0) {
    http_response_code(403);
    exit('Invalid file path');
}

// At this point, $candidate is an existing file inside uploads dir
$fullPath = $candidate;

// ---- HEADERS / MIME ----
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = $finfo ? finfo_file($finfo, $fullPath) : 'application/octet-stream';
if ($finfo) finfo_close($finfo);

// Decide inline vs attachment: inline for images & pdf
$inlineMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'application/pdf'
];
$isInline = in_array($mime, $inlineMimes, true);

// Security headers (soft)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');

// Cache a bit (tune as you like)
$mtime = filemtime($fullPath) ?: time();
header('Cache-Control: private, max-age=86400'); // 1 day
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($fullPath));
$disposition = $isInline ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="' . basename($fullPath) . '"');

// Output
$fp = fopen($fullPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Failed to open file');
}
fpassthru($fp);
fclose($fp);
exit;
