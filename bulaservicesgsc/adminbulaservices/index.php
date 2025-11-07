<?php
require_once __DIR__ . '/server/config.php';
if (isset($_SESSION['admin_id'])) {
  header('Location: admin.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Bula - Admin Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="./css/login.css">

<style>
/* Basic minimal modal styles */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  justify-content: center;
  align-items: center;
  z-index: 999;
}
.modal.show { display: flex; }

.modal-content {
  background: #fff;
  border-radius: 8px;
  padding: 1.5rem;
  width: 90%;
  max-width: 400px;
}
.modal-content h2 { margin-top: 0; font-size: 1.2rem; }
.error-text { color: red; font-size: 0.85rem; margin-top: 0.5rem; text-align:center; }
.form-group { margin-bottom: 1rem; }
.form-group label { display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.9rem; }
.form-group input {
  width:100%; padding:0.6rem; border:1px solid #ddd; border-radius:5px; font-size:0.95rem;
}
button {
  display:inline-block; width:100%; padding:0.6rem; border:none;
  border-radius:5px; font-weight:600; font-size:0.95rem;
  cursor:pointer;
}
.btn-primary { background:#2563eb; color:white; }
.btn-primary:disabled { opacity:0.7; cursor:not-allowed; }
</style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-logo">
        <img src="./images/bula_logo.png" alt="Barangay Bula Logo" class="logo-image">
        <h1>Bula Services</h1>
      </div>
      <h2 class="login-title">Admin Login</h2>
      <form id="login-form">
        <div class="form-group">
          <label>Username</label>
          <input type="text" class="form-control" name="username" placeholder="Enter your username" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-primary">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
        <div id="login-error" style="color:red;margin-top:1rem;text-align:center;"></div>
      </form>
      <div class="login-footer">
        <a href="#" id="forgotLink">Forgot password?</a>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
<div id="forgotModal" class="modal" aria-hidden="true">
  <div class="modal-content">
    <h2>Reset Admin Password</h2>
    <p class="muted">Enter your admin email and we'll send you a reset link.</p>

    <form id="forgotForm" method="POST" novalidate>
      <div class="form-group">
        <label for="forgotEmail">Admin Email</label>
        <input type="email" id="forgotEmail" name="email" class="form-control" required>
      </div>

      <div class="forgot-actions" style="display: flex; gap: 0.5rem; margin-top: 1rem;">
        <button type="button" class="btn btn-outline" id="forgotCancel" style="background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;">Cancel</button>
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
      </div>

      <div id="forgotStatus" class="error-text" style="margin-top: 0.5rem; text-align: center;"></div>
    </form>
  </div>
</div>

  <script src="./script/login.js"></script>
</body>
</html>
