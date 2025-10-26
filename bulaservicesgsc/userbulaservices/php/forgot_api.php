<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
require_once __DIR__ . '/mailer.php';           // sendPasswordResetLink()
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function($e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']); exit; });
set_error_handler(function(){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']); exit; });

function out($code, $data){ http_response_code($code); echo json_encode($data); exit; }

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') out(405, ['ok'=>false,'error'=>'Method not allowed']);

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf)) out(400, ['ok'=>false,'error'=>'Invalid CSRF token']);

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  // generic response
  out(200, ['ok'=>true,'message'=>'If your email exists, a reset link has been sent.']);
}

try { $db = getDBConnection(); } catch(Throwable $e){ out(200, ['ok'=>true,'message'=>'If your email exists, a reset link has been sent.']); }

// Look up user (case-insensitive)
$st = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE LOWER(email)=LOWER(:e) LIMIT 1");
$st->execute([':e' => $email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

// Always respond generically
if (!$user) out(200, ['ok'=>true,'message'=>'If your email exists, a reset link has been sent.']);

// Optional: throttle (1 request per 5 minutes per user)
$st2 = $db->prepare("SELECT reset_token_expires, reset_token_used FROM users WHERE id=:id");
$st2->execute([':id' => $user['id']]);
$u2 = $st2->fetch(PDO::FETCH_ASSOC);
if ($u2 && !empty($u2['reset_token_expires'])) {
    $exp = strtotime((string)$u2['reset_token_expires']);
    if ($exp && ($exp - time()) > (30*60 - 5*60)) { // if requested within last 5 mins of a 30-min window
        out(200, ['ok'=>true,'message'=>'If your email exists, a reset link has been sent.']);
    }
}

// Generate token (random), store only hash (SHA-256)
$rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); // url-safe
$hash     = hash('sha256', $rawToken);
$expires  = (new DateTime('+30 minutes', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');

$upd = $db->prepare("UPDATE users SET reset_token_hash=:h, reset_token_expires=:x, reset_token_used=0 WHERE id=:id");
$upd->execute([':h'=>$hash, ':x'=>$expires, ':id'=>$user['id']]);

$display = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
sendPasswordResetLink($user['email'], $display, $rawToken);

// Generic success to avoid enumeration
out(200, ['ok'=>true,'message'=>'If your email exists, a reset link has been sent.']);