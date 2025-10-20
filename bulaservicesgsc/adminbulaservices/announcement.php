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
$ANNOUNCE_API = '/php/announce_api.php';

$csp = [
    "default-src 'self'",
    "img-src 'self' data: blob: https://bulaservicesgsc.com",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
    "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
    "connect-src 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    "frame-ancestors 'self'",
    "upgrade-insecure-requests"
];
header('Content-Security-Policy: ' . implode('; ', $csp));

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Announcement Management</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/announce.css">
</head>
<body>
 
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

    <div class="announcements-grid" id="announcementsGrid">

    </div>
  </main>

  <div class="modal-overlay" id="addModal">
    <div class="add-modal">
      <div class="modal-header">
        <h3>Add New Announcement</h3>
        <button class="modal-close" id="modalCloseBtn">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="announcementTitle">Title</label>
          <input type="text" id="announcementTitle" placeholder="Enter announcement title">
        </div>
        <div class="form-group">
          <label for="announcementContent">Content</label>
          <textarea id="announcementContent" placeholder="Enter announcement details"></textarea>
        </div>
        <div class="file-upload" id="fileUpload">
          <i class="fas fa-cloud-upload-alt"></i>
          <p>Click to upload announcement image</p>
          <p><small>Recommended size: 1200x800px (JPG/PNG/WEBP, ≤ 2MB)</small></p>
          <input type="file" id="imageUpload" accept="image/*" style="display: none;">
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


  <div class="toast" id="toast"></div>


  <script>
    window.__ANNOUNCE_API__ = "<?= htmlspecialchars($ANNOUNCE_API, ENT_QUOTES) ?>";
    window.__CSRF__ = "<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>";
  </script>


  <script src="./script/announce.js"></script>
</body>
</html>
