<?php
declare(strict_types=1);
/**
 * Same-origin proxy for public announcements (list only).
 */

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$action = $_GET['action'] ?? 'list';
if ($action !== 'list') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$limit = (int)($_GET['limit'] ?? 6);
if ($limit < 1)  $limit = 1;
if ($limit > 100) $limit = 100;

$upstream = 'https://admin.bulaservicesgsc.com/php/announce_api.php?action=list&limit=' . $limit;

$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
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

if ($code < 200 || $code >= 300) {
    http_response_code($code ?: 502);
}
echo $body;
