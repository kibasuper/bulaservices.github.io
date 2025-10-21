<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Cashier</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/cashier.css">
</head>
<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-desktop"></i> Cashier</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>
  </header>

  <main class="dashboard-container container">
    <!-- POS + Requests layout -->
    <div class="pos-layout">
      <!-- POS Section -->
      <section class="pos-section">
        <div class="pos-header">
          <h3>Current Transaction</h3>
          <button class="clear-cart-btn" id="clearCartBtn"><i class="fas fa-trash"></i> Clear</button>
        </div>
        <div class="cart-items" id="cartItems">
          <div class="empty-cart-message">No items Added</div>
        </div>
        <div class="pos-totals">
          <div class="total-row"><span>Total:</span><span class="total-amount" id="cartTotal">₱0.00</span></div>
          <div class="total-row"><span>Cash:</span><input type="number" id="cashGiven" placeholder="₱0.00" class="cash-input"></div>
          <div class="total-row"><span>Change:</span><span class="total-amount" id="cartChange">₱0.00</span></div>
        </div>
        <button class="payment-btn" id="processPaymentBtn" disabled>
          <i class="fas fa-cash-register"></i> Process Payment
        </button>
      </section>

      <!-- Pending Requests Section -->
      <section class="requests-section">
        <div class="requests-header">
          <h2 class="section-title">Pending Payment Requests <span class="status-pill">Processing</span></h2>

          <!-- Search moved here -->
          <div class="search-bar inline" style="margin-left:auto">
            <input type="text" class="search-input" id="searchInput" placeholder="Search by Transaction No. or Customer Name">
            <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i> Search</button>
          </div>
        </div>

        <table class="requests-table">
          <thead>
            <tr>
              <th>Transaction No</th>
              <th>Type</th>
              <th>Details</th>
              <th>Date/Time</th>
              <th>Status</th>
              <th>Amount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="requestsTableBody">
            <tr><td colspan="7" style="text-align:center;color:#666;">Search to find processing requests…</td></tr>
          </tbody>
        </table>
      </section>
    </div>
  </main>

  <!-- Receipt Modal -->
  <div class="modal-overlay" id="receiptModal">
    <div class="receipt-modal">
      <div class="receipt-header">
        <h3>BARANGAY BULA</h3>
        <p>Official Receipt</p>
      </div>
      <div class="receipt-body">
        <div class="receipt-row"><span>OR #:</span><span id="receipt-number">BULA-XXXX</span></div>
        <div class="receipt-row"><span>Date:</span><span id="receipt-date"></span></div>
        <div class="receipt-row" style="margin:10px 0;padding-top:10px;border-top:1px dashed #ccc;">
          <strong>Service</strong><strong>Amount</strong>
        </div>
        <div id="receipt-items"></div>
        <div class="receipt-row receipt-total-row"><span>TOTAL:</span><span id="receipt-total">₱0.00</span></div>
        <div class="receipt-row"><span>Cash:</span><span id="receipt-cash">₱0.00</span></div>
        <div class="receipt-row"><span>Change:</span><span id="receipt-change">₱0.00</span></div>
      </div>
      <div class="receipt-footer">
        <p>Thank you for your payment!</p>
        <div class="receipt-actions">
          <button class="modal-btn print-btn" id="printReceiptBtn"><i class="fas fa-print"></i> Print</button>
          <button class="modal-btn done-btn" id="closeReceiptBtn"><i class="fas fa-check"></i> Done</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="./script/cashier.js"></script>
</body>
</html>
