<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barangay Bula - Gym Requests</title>

  <!-- Icons + Your CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="./css/gymrequest.css">
</head>
<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-warehouse"></i> Gymasium Request Approval</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

<main class="dashboard-container container">
  <section class="requests-list-section">
    <div class="section-title">
      <h2>All Gym Requests</h2>
      <div class="action-buttons">
        <button class="action-button refresh" onclick="refreshData()">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <button class="action-button new" onclick="newTransaction()">
          <i class="fas fa-plus"></i> New Transaction
        </button>
      </div>
    </div>
    
    <table class="requests-table">
      <thead>
        <tr>
          <th>Reference No</th>
          <th>Requested By</th>
          <th>Activity</th>
          <th>Reservation Date</th>
          <th>Time Slots</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>

      <!-- JS will render all rows here -->
      <tbody id="requestsTableBody"></tbody>
    </table>
  </section>
</main>

<!-- Review Modal -->
<div id="reviewModal" class="modal-overlay"></div>

<!-- Confirm Rejection Modal -->
<div id="confirmRejectModal" class="modal">
  <div class="confirm-modal-content">
    <div class="confirm-icon">
      <i class="fas fa-triangle-exclamation"></i>
    </div>
    <h3 class="confirm-title">Reject this reservation?</h3>
    <p class="confirm-message">This will cancel the reservation and free up its time slots.</p>
    <div class="confirm-actions">
      <button class="modal-btn close-btn" onclick="closeRejectConfirm()">
        <i class="fas fa-times"></i> Cancel
      </button>
      <button class="modal-btn reject-btn" onclick="confirmReject()">
        <i class="fas fa-ban"></i> Confirm Rejection
      </button>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal">
  <div class="success-modal-content">
    <div class="success-icon">
      <i class="fas fa-check-circle"></i>
    </div>
    <h3 class="success-title" id="successTitle"></h3>
    <p class="success-message" id="successMessage"></p>
    <button onclick="closeSuccessModal()">Close</button>
  </div>
</div>

<!-- Admin JS (talks to gymadback.php in same folder) -->
<script>
  // Set your admin API key here (must match gymadback.php)
  window.ADMIN_API_KEY = 'change-this-admin-key-123';
</script>
<script src="./script/gymrequest.js"></script>
</body>
</html>
