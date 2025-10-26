<?php
declare(strict_types=1);

// Normalize any DB value ("/uploads/..", "uploads/..", "profile_pictures/..") into a URL served by admin
function admin_upload_url(?string $path): ?string {
    if (!$path) return null;
    $rel = ltrim($path, '/');

    // Strip a leading "uploads/" if present – the serve script expects paths relative to /uploads
    if (strpos($rel, 'uploads/') === 0) {
        $rel = substr($rel, strlen('uploads/'));
    }
    return '/php/serve_upload_admin.php?file=' . rawurlencode($rel);
}
