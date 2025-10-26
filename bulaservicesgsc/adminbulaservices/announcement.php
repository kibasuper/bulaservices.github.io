<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
$adminName = $_SESSION['admin_username'] ?? 'Admin';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

// Local path works if this PHP lives on admin subdomain
$ANNOUNCE_API = '/php/announce_api.php';

$csp = [
    "default-src 'self'",
    "img-src 'self' data: blob: https://bulaservicesgsc.com",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
    "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
    "connect-src 'self' https://admin.bulaservicesgsc.com https://bulaservicesgsc.com",
    "object-src 'none'",
    "base-uri 'self'",
    "frame-ancestors 'self'",
    "upgrade-insecure-requests"
];
header('Content-Security-Policy: ' . implode('; ', $csp));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Announcement Management</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/announce.css">
</head>
<body>
  <!-- Toast root -->
  <div id="toast-root" aria-live="polite" aria-atomic="true"></div>

  <!-- Alert / Confirm Modal -->
  <div id="bx-alert-overlay" class="bx-alert-overlay" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="bx-alert" role="document">
      <div class="bx-alert__header">
        <div class="bx-alert__icon" id="bx-alert-icon" aria-hidden="true">
          <i class="fas fa-info-circle"></i>
        </div>
        <h3 class="bx-alert__title" id="bx-alert-title">Notice</h3>
        <button class="bx-alert__close" id="bx-alert-close" aria-label="Close">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="bx-alert__body">
        <p id="bx-alert-message">This is an alert message.</p>
      </div>
      <div class="bx-alert__actions" id="bx-alert-actions">
        <!-- Buttons are injected via JS -->
      </div>
    </div>
  </div>

  <header class="app-header">
    <div class="container header-content">
      <h1>
        <i class="fas fa-city"></i>
        Barangay Bula Admin
      </h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
      </div>
    </div>
  </header>

  <main class="dashboard-container container">
    <div class="section-title">
      <h2>Current Announcements</h2>
      <button class="add-announcement-btn" id="addAnnouncementBtn">
        <i class="fas fa-plus"></i> Add Announcement
      </button>
    </div>

    <div class="announcements-grid" id="announcementsGrid"></div>
  </main>

  <!-- Add Announcement Modal -->
  <div class="modal-overlay" id="addModal">
    <div class="add-modal">
      <div class="modal-header">
        <h3>Add New Announcement</h3>
        <button class="modal-close" id="modalCloseBtn" aria-label="Close">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label for="announcementTitle">Title</label>
          <input type="text" id="announcementTitle" placeholder="Enter announcement title">
        </div>

        <div class="file-upload" id="fileUpload" role="button" tabindex="0" aria-label="Upload announcement image">
          <i class="fas fa-cloud-upload-alt"></i>
          <p>Click to upload announcement image</p>
          <p><small>Recommended: 1200×800 (JPG/PNG/WEBP, ≤ 2MB)</small></p>
          <input type="file" id="imageUpload" accept="image/*" style="display:none;">
        </div>

        <img id="imagePreview" alt="Preview">
      </div>

      <div class="modal-actions">
        <button class="modal-btn cancel-btn" id="cancelBtn">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button class="modal-btn upload-btn" id="uploadBtn" disabled>
          <i class="fas fa-upload"></i> Publish
        </button>
      </div>
    </div>
  </div>

  <script>
    window.__ANNOUNCE_API__ = "<?= htmlspecialchars($ANNOUNCE_API, ENT_QUOTES) ?>";
    window.__CSRF__ = "<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>";
  </script>
  <script src="./script/announce.js"></script>
</body>
</html>
