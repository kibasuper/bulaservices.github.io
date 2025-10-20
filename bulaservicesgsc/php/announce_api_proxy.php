<?php
declare(strict_types=1);
/**
 * Public Announcements Proxy (same-origin) — 2025-10-14
 * Purpose: let user site call same-origin URL, which fetches public list from admin API.
 * Security: only allows action=list (public). No admin actions. No cookies forwarded.
 */

header('Content-Type: application/json');

// Only allow GET
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Whitelist only the public "list" action
$action = $_GET['action'] ?? 'list';
if ($action !== 'list') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Sanitize/limit "limit" param
$limit = (int)($_GET['limit'] ?? 6);
if ($limit < 1)  $limit = 1;
if ($limit > 100) $limit = 100;

// Build upstream URL (admin API)
$upstream = 'https://admin.bulaservicesgsc.com/php/announce_api.php?action=list&limit=' . $limit;

// Fetch via cURL without cookies (public endpoint)
$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        // Don’t send credentials or cookies; public list doesn’t need them
    ],
]);

$body = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Upstream fetch failed: ' . $err]);
    exit;
}

// Pass through upstream status code if reasonable
if ($code < 200 || $code >= 300) {
    http_response_code($code ?: 502);
}

echo $body;
