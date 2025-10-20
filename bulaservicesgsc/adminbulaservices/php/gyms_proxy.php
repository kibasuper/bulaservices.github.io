<?php
declare(strict_types=1);

/**
 * Same-origin proxy from admin site -> main site's gymsback.php.
 * Works with CSP default-src 'self' because the browser only talks to this file.
 * If you later REQUIRE main-site cookies, switch to CORS (Option A).
 */

header('Content-Type: application/json');

$TARGET = 'https://bulaservicesgsc.com/bulaservicesgsc/userbulaservices/php/gymsback.php';

// Raw JSON from fetch()
$body = file_get_contents('php://input') ?: '';

$ch = curl_init($TARGET);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/json',
    'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
    'X-Forwarded-Proto: https',
    'X-Admin-Origin: admin.bulaservicesgsc.com'
  ],
  CURLOPT_POSTFIELDS     => $body,
  CURLOPT_HEADER         => false,
  CURLOPT_TIMEOUT        => 15,
  CURLOPT_CONNECTTIMEOUT => 5,
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
$status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 200;
curl_close($ch);

if ($response === false) {
  http_response_code(502);
  echo json_encode(['status'=>'error','message'=>'Proxy error: '.$err]);
  exit;
}

http_response_code($status);
echo $response;
