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

  <!-- modal polish (same as before) -->
  <style>
    .modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(2px); z-index:1000; align-items:center; justify-content:center; }
    .modal.show { display:flex; }
    .modal-content { width:min(900px,94vw); border-radius:16px; background:#fff; box-shadow:0 24px 48px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.08); overflow:hidden; }
    .modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 18px; border-bottom:1px solid #eef2f7; background:linear-gradient(180deg,#f7f9fc 0%,#ffffff 100%); }
    .modal-header h2 { font-size:1.1rem; margin:0; }
    .close-modal { border:0; background:#e5e7eb; width:36px; height:36px; border-radius:10px; display:grid; place-items:center; cursor:pointer; }
    .close-modal:hover { background:#dfe3e8; }
    .modal-body { padding:18px; }
    .meta-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 18px; }
    .meta-item { display:flex; align-items:center; gap:8px; }
    .meta-item .lbl { color:#6b7280; min-width:150px; font-weight:600; }
    .meta-item .val { color:#0f172a; }
    .badge { display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size:.78rem; border-radius:999px; padding:4px 10px; }
    .badge.completed{ background:#ecfdf5; color:#047857; }
    .badge.approved{ background:#eff6ff; color:#1d4ed8; }
    .badge.rejected{ background:#fef2f2; color:#b91c1c; }
    .badge.pending{ background:#fdf6e6; color:#b45309; }
    .section-title { margin:16px 0 8px; font-size:.95rem; color:#334155; font-weight:700; }
    .modal-table { width:100%; border-collapse:collapse; }
    .modal-table th, .modal-table td { border:1px solid #eef2f7; padding:8px 10px; font-size:.92rem; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; padding:14px 16px; border-top:1px solid #eef2f7; background:#fafbfc; }
    .btn { border:0; border-radius:10px; padding:10px 14px; font-weight:600; cursor:pointer; }
    .btn-outline { background:#e5e7eb; }
    .btn-outline:hover { background:#dfe3e8; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-primary:hover { background:#1d4ed8; }
    .tiny-muted { color:#6b7280; font-size:.8rem; }
    @media (max-width: 720px) {
      .meta-grid { grid-template-columns:1fr; }
      .meta-item .lbl { min-width:120px; }
    }
  </style>
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
