<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';

// Require login
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Transaction History</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="./css/transaction.css">
</head>
<body>
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-receipt"></i> Transaction History</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
   
    </div>
  </header>

  <main class="main-content container">
    <div class="page-header">
      <div>
        <h1 class="page-title">Transaction History</h1>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Financial Management</a></li>
          <li class="breadcrumb-item active">Transaction History</li>
        </ul>
      </div>
    </div>

    <div class="filter-section">
      <div class="filter-group">
        <label for="time-period" class="filter-label">Time Period</label>
        <select id="time-period" class="filter-select">
          <option selected>All Time</option>
          <option>Last 7 Days</option>
          <option>Last 30 Days</option>
          <option>Last 90 Days</option>
          <option>This Month</option>
          <option>Last Month</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="transaction-status" class="filter-label">Transaction Status</label>
        <select id="transaction-status" class="filter-select">
          <option selected>All Transactions</option>
          <option>Completed</option>
          <option>Approved</option>
          <option>Rejected</option>
          <option>Pending</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="service-type" class="filter-label">Service Type</label>
        <select id="service-type" class="filter-select">
          <option selected>All Services</option>
          <option>Gym Services</option>
          <option>Certificates</option>
        </select>
      </div>

      <div class="filter-group" style="align-self:flex-end;">
        <button class="btn btn-primary" id="apply-filters">
          <i class="fas fa-filter"></i> Apply Filters
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Transaction History</h2>
        <div class="table-actions">
          <button class="btn btn-outline" id="export-btn">
            <i class="fas fa-file-export"></i> Export to Excel
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="transactions-table" id="transactions-table">
            <thead>
              <tr>
                <th>Reference #</th> <!-- NEW first column -->
                <th>Receipt #</th>   <!-- NEW column -->
                <th>Date & Time</th>
                <th>Customer</th>
                <th>Service Items</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="transactions-body"><!-- filled by JS --></tbody>
          </table>
        </div>
        <p class="tiny-muted" style="margin-top:6px;">
          Note: If a payment includes multiple requests, the first reference is shown here; open details to see all.
        </p>
      </div>
    </div>
  </main>

  <!-- Transaction Details Modal -->
  <div class="modal" id="transaction-details-modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="tx-title">
      <div class="modal-header">
        <h2 id="tx-title"><i class="fas fa-receipt"></i> Transaction Details</h2>
        <button class="close-modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="meta-grid">
          <div class="meta-item"><span class="lbl">Reference #:</span>     <span class="val" id="detail-ref"></span></div>
          <div class="meta-item"><span class="lbl">Receipt #:</span>       <span class="val" id="detail-id"></span></div>
          <div class="meta-item"><span class="lbl">Date:</span>            <span class="val" id="detail-date"></span></div>
          <div class="meta-item"><span class="lbl">Customer:</span>        <span class="val" id="detail-customer"></span></div>
          <div class="meta-item"><span class="lbl">Contact:</span>         <span class="val" id="detail-contact"></span></div>
          <div class="meta-item"><span class="lbl">Amount:</span>          <span class="val" id="detail-amount"></span></div>
          <div class="meta-item"><span class="lbl">Payment Method:</span>  <span class="val" id="detail-payment"></span></div>
          <div class="meta-item"><span class="lbl">Processed By:</span>    <span class="val" id="detail-processor"></span></div>
          <div class="meta-item"><span class="lbl">Released By:</span>     <span class="val" id="detail-releasedby"></span></div>
          <div class="meta-item"><span class="lbl">Approved By:</span>          <span class="val" id="detail-status"></span></div>
        </div>

        <h3 class="section-title">Service Requests</h3>
        <table class="modal-table">
          <thead>
            <tr>
              <th>Reference #</th>
              <th>Service</th>
              <th>Description</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="detail-service"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" id="close-details-modal">Close</button>
        <button class="btn btn-primary" id="print-receipt"><i class="fas fa-receipt"></i> Print Receipt</button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-message">Operation completed successfully</span>
  </div>

  <script src="./script/transaction.js" defer></script>
</body>
</html>
