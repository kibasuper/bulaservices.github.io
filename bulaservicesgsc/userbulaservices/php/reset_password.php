<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/config.php';
header('X-Frame-Options: DENY');

function outJson($code, $data){ http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; }

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
  // Show a very small HTML form (no layout dependency)
  $token = $_GET['token'] ?? '';
  $csrf  = generateCsrfToken();
  ?>
  <!doctype html>
  <html><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb}
      .card{max-width:420px;margin:8vh auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 8px 32px rgba(0,0,0,.08)}
      h1{font-size:1.25rem;margin:.25rem 0 1rem}
      .form-group{margin:.75rem 0}
      .form-group label{display:block;margin-bottom:.35rem;color:#334155}
      .form-control{width:100%;padding:.6rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
      .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:8px;border:0;cursor:pointer}
      .btn-primary{background:#2563eb;color:#fff}
      .msg{margin:.75rem 0;color:#334155}
    </style>
  </head><body>
    <div class="card">
      <h1><i class="fa-solid fa-key"></i> Choose a new password</h1>
      <div class="msg">Enter your new password twice. The link expires in 30 minutes.</div>
      <form id="resetForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token,ENT_QUOTES) ?>">
        <div class="form-group">
          <label>New password</label>
          <input class="form-control" type="password" name="new_password" id="pw1" minlength="8" required>
        </div>
        <div class="form-group">
          <label>Confirm new password</label>
          <input class="form-control" type="password" name="new_password2" id="pw2" minlength="8" required>
        </div>
        <button class="btn btn-primary"><i class="fa-solid fa-save"></i> Update password</button>
      </form>
      <div id="status" class="msg"></div>
    </div>
    <script>
      document.getElementById('resetForm').addEventListener('submit', async (e)=>{
        e.preventDefault();
        const s = document.getElementById('status');
        s.textContent = '';
        const fd = new FormData(e.target);
        if (fd.get('new_password') !== fd.get('new_password2')) {
          s.textContent = 'Passwords do not match.'; return;
        }
        const res = await fetch(location.href, {method:'POST', body: fd, credentials:'include'});
        const j = await res.json().catch(()=>({ok:false,error:'Server error'}));
        if (j.ok) {
          s.textContent = 'Password changed. You may now log in.';
          setTimeout(()=> location.href = '../index.php', 1200);
        } else {
          s.textContent = j.error || 'Reset failed.';
        }
      });
    </script>
  </body></html>
  <?php
  exit;
}

if ($method === 'POST') {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!validateCsrfToken($csrf)) outJson(400, ['ok'=>false,'error'=>'Invalid CSRF token']);

  $token = (string)($_POST['token'] ?? '');
  $pw1   = (string)($_POST['new_password'] ?? '');
  $pw2   = (string)($_POST['new_password2'] ?? '');

  if ($token === '' || $pw1 === '' || $pw2 === '') outJson(422, ['ok'=>false,'error'=>'All fields are required']);
  if ($pw1 !== $pw2) outJson(422, ['ok'=>false,'error'=>'Passwords do not match']);
  if (strlen($pw1) < 8) outJson(422, ['ok'=>false,'error'=>'Password must be at least 8 characters']);
  // Optional complexity:
  // if (!preg_match('/[A-Za-z]/',$pw1) || !preg_match('/\d/',$pw1)) outJson(422, ['ok'=>false,'error'=>'Use letters and numbers']);

  try { $db = getDBConnection(); } catch(Throwable $e){ outJson(500, ['ok'=>false,'error'=>'DB error']); }

  $hash = hash('sha256', $token);

  // Find valid token (unused + not expired)
  $st = $db->prepare("SELECT id FROM users
                      WHERE reset_token_hash=:h AND reset_token_used=0
                        AND reset_token_expires IS NOT NULL
                        AND reset_token_expires >= CURRENT_TIMESTAMP
                      LIMIT 1");
  $st->execute([':h'=>$hash]);
  $uid = (int)($st->fetchColumn() ?: 0);
  if ($uid <= 0) outJson(400, ['ok'=>false,'error'=>'Invalid or expired reset link']);

  // Update password & invalidate token
  $newHash = password_hash($pw1, PASSWORD_DEFAULT);
  $upd = $db->prepare("UPDATE users
                       SET password=:p, updated_at=CURRENT_TIMESTAMP,
                           reset_token_used=1, reset_token_hash=NULL, reset_token_expires=NULL
                       WHERE id=:id LIMIT 1");
  $upd->execute([':p'=>$newHash, ':id'=>$uid]);

  if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);

  outJson(200, ['ok'=>true,'message'=>'Password updated']);
}

outJson(405, ['ok'=>false,'error'=>'Method not allowed']);
