<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Gym Extension — Barangay Bula</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/cashier.css">
  <style>
    .tabbar {display:flex; gap:8px; margin:12px 0 20px;}
    .tabbar a {padding:8px 12px; border-radius:10px; background:#f3f4f6; text-decoration:none; color:#111827;}
    .tabbar a.active {background:#e0e7ff; color:#3730a3; font-weight:600;}
    .grid {display:grid; grid-template-columns: 1.2fr 1fr; gap:16px;}
    .card {border:1px solid #e5e7eb; border-radius:12px; padding:12px;}
    .slot {display:inline-block; margin:4px; padding:6px 8px; border-radius:8px; border:1px solid #e5e7eb;}
    .slot.busy {background:#fee2e2; border-color:#fecaca;}
    .slot.free {background:#ecfeff; border-color:#a7f3d0;}
    .btn {background:#111827;color:#fff;border:none;border-radius:8px;padding:8px 12px;cursor:pointer}
    .btn:hover{opacity:.9}
  </style>
</head>
<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-warehouse"></i>Gym Extension</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

<main class="dashboard-container container">
  <section class="requests-section">
    <div class="requests-header">
      <h2 class="section-title">Paid Gym Reservations (Today & Upcoming)</h2>
      <div class="search-bar inline" style="margin-left:auto">
        <input type="date" id="extDate">
        <input type="text" class="search-input" id="extSearch" placeholder="Search by Code or Name">
        <button class="search-btn" id="extSearchBtn"><i class="fas fa-search"></i> Search</button>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <table class="requests-table">
          <thead>
            <tr><th>Transaction No.</th><th>Customer</th><th>Time</th><th>Status</th><th>Timer</th><th>Date</th> </tr>
          </thead>
          <tbody id="extList"><tr><td colspan="5" style="text-align:center;color:#666;">Search or pick a date…</td></tr></tbody>
        </table>
      </div>

      <div class="card" id="extDetail">
        <h3>Reservation Detail</h3>
        <div id="extMeta" style="margin-bottom:10px; color:#374151;">Select a reservation…</div>
        <div id="extSlots"></div>
        <div style="margin-top:12px;display:flex;gap:8px;">
          <button class="btn" id="btnPlus1h">+1 hour</button>
          <button class="btn" id="btnPlus2h">+2 hours</button>
        </div>
        <div id="extQuote" style="margin-top:10px;font-weight:600;"></div>
        <div style="margin-top:10px;">
          <button class="btn" id="btnCreateExt">Create Extension (send to Cashier)</button>
        </div>
      </div>
    </div>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="./script/gym_extension.js"></script>
</body>
</html>
