<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';

// ---------------- Session & Access ----------------
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Auto-logout on timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Get admin info
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$admin_role     = $_SESSION['admin_role'] ?? 'kagawad';   // default = staff
$admin_position = $_SESSION['admin_position'] ?? '';
$is_superadmin  = ($admin_role === 'superadmin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Bula - Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./css/admin.css">
</head>
<body>
<header class="app-header">
  <div class="container header-content">
    <div class="logo-container">
      <img src="./images/bula_logo.png" alt="Barangay Bula Logo" class="logo-image">
      <h1>Bula Services</h1>
    </div>
    <div class="time-display"><div id="philippine-time">Loading Time...</div></div>
    <div class="user-menu">
      <button class="user-menu-btn" id="user-menu-btn" aria-expanded="false">
        <span><?= htmlspecialchars($admin_username) ?></span>
        <i class="fas fa-user-circle"></i>
      </button>
      <div class="dropdown-menu" id="dropdown-menu">
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Account Settings</a>
        <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
        <div class="dropdown-divider"></div>
        <button id="logout-btn"><i class="fas fa-sign-out-alt"></i> Log Out</button>
      </div>
    </div>
  </div>
</header>

<main class="dashboard-container container">
  <div class="quick-actions-container">

    <?php if ($is_superadmin): ?>
    <!-- People Management -->
    <div class="quick-actions-section">
      <h2 class="section-title">People Management</h2>
      <div class="actions-grid">
        <a href="officials.php" class="action-card"><i class="fas fa-users-cog"></i><span>Officials Management</span></a>
        <a href="resident.php" class="action-card"><i class="fas fa-users"></i><span>Resident Management</span></a>
        <a href="report.php" class="action-card"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
        <a href="Pricing.php" class="action-card"><i class="fas fa-file-alt"></i><span>Pricing</span></a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
      <h2 class="section-title">Quick Actions</h2>
      <div class="actions-grid">
        <a href="Transaction.php" class="action-card"><i class="fas fa-file-invoice"></i><span>Transactions History</span></a>
        <a href="request.php" class="action-card"><i class="fas fa-file-signature"></i><span>Service Requests</span></a>
        <a href="gymrequest.php" class="action-card"><i class="fas fa-warehouse"></i><span>Gym Requests</span></a>
        <a href="announcement.php" class="action-card"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
      </div>
    </div>

    <!-- Processing & Finance -->
    <div class="quick-actions-section">
      <h2 class="section-title">Processing &amp; Finance</h2>
      <div class="actions-grid">
        <a href="cashier.php" class="action-card"><i class="fas fa-cash-register"></i><span>Cashier</span></a>
        <a href="release.php" class="action-card"><i class="fa-solid fa-file-circle-check"></i><span>Certificate Releasing</span></a>
        <a href="gym_extension.php" class="action-card"><i class="fa-solid fa-business-time"></i><span>Extensions</span></a>
      </div>
    </div>
  </div>

  <?php if (!$is_superadmin): ?>
  <div style="margin-top:2rem;background:#fef3c7;padding:1rem;border-radius:8px;border:1px solid #fcd34d;">
    <i class="fas fa-lock"></i> You are logged in as <strong><?= htmlspecialchars($admin_role) ?></strong>. Some modules are restricted to the Barangay Captain.
  </div>
  <?php endif; ?>
</main>

<!-- Logout Modal -->
<div class="modal" id="logout-modal">
  <div class="modal-content">
    <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
    <h3>Logging Out</h3>
    <p>You are being securely logged out of the system...</p>
    <div class="spinner"><i class="fas fa-circle-notch fa-spin"></i></div>
  </div>
</div>

<script src="./script/admin.js"></script>
</body>
</html>
