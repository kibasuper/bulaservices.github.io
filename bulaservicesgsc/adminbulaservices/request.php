<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php'; // loads $db and session

// Session check: only admins can access
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT sr.reference_number, sr.service_type, sr.purpose, sr.purpose_details,
            sr.status, sr.request_date, sr.document_method, sr.document_path,
            u.first_name, u.last_name, u.email, u.contact_number
        FROM service_requests sr
         JOIN users u ON sr.user_id = u.id
         WHERE sr.status = 'pending'
        ORDER BY sr.request_date DESC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error fetching requests: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Bula - Service Requests</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="./css/request.css">
</head>
<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-file"></i> Certificate Request Approval</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

  <main class="dashboard-container container">
    <section class="requests-list-section">
      <div class="section-header">
        <h2 class="section-title">All Requests</h2>
        <div class="section-actions">
          <button class="action-btn refresh-btn" onclick="refreshPage()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
      </div>
      <table class="requests-table">
        <thead>
          <tr>
            <th>Transaction No</th>
            <th>Requested By</th>
            <th>Certificate Type</th>
            <th>Purpose</th>
            <th>Date Requested</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="requestsTableBody">
          <?php foreach ($requests as $r): ?>
          <tr>
            <td class="transaction-code"><?= htmlspecialchars($r['reference_number']) ?></td>
            <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td><?= ucfirst(str_replace('_', ' ', $r['service_type'])) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= date('M d, Y h:i A', strtotime($r['request_date'])) ?></td>
            <td><span class="status-badge status-<?= strtolower($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <button class="action-btn review-btn" onclick="reviewRequest('<?= htmlspecialchars($r['reference_number']) ?>')">
                <i class="fas fa-eye"></i> Review
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Review Modal -->
  <div id="reviewModal" class="modal"></div>

  <!-- Success Modal -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <h3 id="successTitle"></h3>
      <p id="successMessage"></p>
      <button onclick="document.getElementById('successModal').classList.remove('active')">Close</button>
    </div>
  </div>

  <!-- Image Lightbox -->
  <div id="imageLightbox" class="img-lightbox" aria-hidden="true">
    <button type="button" class="img-close" aria-label="Close preview">&times;</button>
    <img id="lightboxImg" alt="Attachment preview">
  </div>

  <script src="./script/request.js"></script>
</body>
</html>
