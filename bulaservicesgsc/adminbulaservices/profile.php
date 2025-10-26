<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

$adminId = (int)$_SESSION['admin_id'];

try {
  $db = getDBConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt = $db->prepare("
    SELECT admin_id, username, email, first_name, last_name, role, contact_number, is_active, last_login, password_changed_at
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
  ");
  $stmt->execute([$adminId]);
  $me = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$me) { throw new RuntimeException('Admin not found'); }
} catch (Throwable $e) {
  http_response_code(500);
  echo "Server error.";
  exit;
}

// name for avatar
$avatarName = urlencode(trim(($me['first_name'] ?: 'Admin') . ' ' . ($me['last_name'] ?: 'User')));
$roleLabel = ucfirst(str_replace('_',' ', (string)$me['role']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Profile</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="./css/profile.css"/>
</head>
<body>
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-city"></i> Barangay Bula Admin</h1>
      <div class="user-menu">
        <button class="user-menu-btn" id="user-menu-btn" aria-expanded="false" aria-haspopup="true">
          <span><?=htmlspecialchars($me['first_name'].' '.$me['last_name'], ENT_QUOTES)?></span>
          <i class="fas fa-user-circle"></i>
        </button>
        <div class="dropdown-menu" id="dropdown-menu">
          <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
          <div class="dropdown-divider"></div>
          <form method="post" action="logout.php"><button type="submit" class="linklike"><i class="fas fa-sign-out-alt"></i> Log Out</button></form>
        </div>
      </div>
    </div>
  </header>

  <main class="dashboard-container container">
    <div class="page-header">
      <h1 class="page-title">
        <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        My Profile
      </h1>
    </div>

    <div class="card">
      <h2 class="card-title">Personal Information</h2>
      <div class="profile-top">
        <img
          src="https://ui-avatars.com/api/?name=<?=$avatarName?>&background=2563eb&color=fff&size=120"
          alt="Profile Picture"
          class="avatar"
        />
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">First Name</label>
            <input type="text" id="first_name" value="<?=htmlspecialchars((string)$me['first_name'], ENT_QUOTES)?>">
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input type="text" id="last_name" value="<?=htmlspecialchars((string)$me['last_name'], ENT_QUOTES)?>">
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" id="username" value="<?=htmlspecialchars((string)$me['username'], ENT_QUOTES)?>">
            <small class="muted">Must be unique</small>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" id="email" value="<?=htmlspecialchars((string)$me['email'], ENT_QUOTES)?>">
            <small class="muted">Must be unique</small>
          </div>
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input type="text" id="contact_number" value="<?=htmlspecialchars((string)$me['contact_number'], ENT_QUOTES)?>">
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <input type="text" value="<?=htmlspecialchars($roleLabel, ENT_QUOTES)?>" disabled>
          </div>
        </div>
      </div>
      <div class="row-actions">
        <button id="saveProfileBtn" class="btn btn-primary">
          <i class="fa fa-save"></i> Save Changes
        </button>
        <span id="profileStatus" class="status"></span>
      </div>
    </div>

    <div class="card">
      <h2 class="card-title">Account Security</h2>
      <div class="form-grid small">
        <div class="form-group">
          <label class="form-label">Password last changed</label>
          <input type="text" value="<?=htmlspecialchars($me['password_changed_at'] ?: '—', ENT_QUOTES)?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Last login</label>
          <input type="text" value="<?=htmlspecialchars($me['last_login'] ?: '—', ENT_QUOTES)?>" disabled>
        </div>
      </div>
      <button id="openChangePassword" class="btn btn-outline">
        <i class="fas fa-key"></i> Change Password
      </button>
      <span id="passwordStatus" class="status"></span>
    </div>
  </main>

  <!-- Modal: confirm current password for profile updates -->
  <div class="modal" id="confirmModal" aria-hidden="true">
    <div class="modal-content">
      <button class="modal-close" data-close>&times;</button>
      <div class="modal-icon"><i class="fas fa-lock"></i></div>
      <h3>Confirm Changes</h3>
      <p>Please enter your current password to save profile changes.</p>
      <form id="confirmForm">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input type="password" id="confirm_current_password" required autocomplete="current-password">
        </div>
        <div class="row-actions">
          <button type="button" class="btn" data-close>Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm & Save</button>
        </div>
      </form>
      <div class="modal-note" id="confirmError"></div>
    </div>
  </div>

  <!-- Modal: change password (requires current + new + confirm) -->
  <div class="modal" id="passwordModal" aria-hidden="true">
    <div class="modal-content">
      <button class="modal-close" data-close>&times;</button>
      <div class="modal-icon"><i class="fas fa-key"></i></div>
      <h3>Change Password</h3>
      <form id="passwordForm">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input type="password" id="pw_current" required autocomplete="current-password">
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" id="pw_new" required autocomplete="new-password">
          <small class="muted">Minimum 8 characters, mixed case and number recommended.</small>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" id="pw_new_confirm" required autocomplete="new-password">
        </div>
        <div class="row-actions">
          <button type="button" class="btn" data-close>Cancel</button>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
      </form>
      <div class="modal-note" id="passwordError"></div>
    </div>
  </div>

  <footer class="app-footer">
    <div class="container"><p>Barangay Bula Management System © 2025</p></div>
  </footer>

  <script>
    window.__ADMIN__ = {
      id: <?=$adminId?>,
      endpoints: {
        updateProfile: './php/admin_profile_update.php',
        changePassword: './php/admin_change_password.php'
      }
    };
  </script>
  <script src="./script/profile.js"></script>
</body>
</html>
