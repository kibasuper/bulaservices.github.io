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
  <title>Release — Barangay Bula</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/cashier.css">
  <style>
    .pill {background:#eef2ff;color:#4338ca;font-weight:600;border-radius:999px;padding:4px 10px;font-size:12px;}
    .claim-btn {background:#10b981;color:#fff;border:none;border-radius:8px;padding:6px 10px;cursor:pointer}
    .claim-btn:hover{opacity:.9}
    .type-badge{font-size:12px;padding:2px 8px;border-radius:999px;background:#f3f4f6}
  </style>
</head>
<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-file"></i> Certificate Release</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

<main class="dashboard-container container">
  <section class="requests-section">
    <div class="requests-header">
      <h2 class="section-title">Ready to Release <span class="pill">Paid</span></h2>
      <div class="search-bar inline" style="margin-left:auto">
        <input type="text" class="search-input" id="relSearch" placeholder="Search by Transaction No. or Customer Name">
        <button class="search-btn" id="relSearchBtn"><i class="fas fa-search"></i> Search</button>
      </div>
    </div>

    <table class="requests-table">
      <thead>
        <tr>
          <th>Transaction No</th>
          <th>Type</th>
          <th>Customer</th>
          <th>Paid Date</th>
          <th>Amount</th>
          <th>Claim</th>
        </tr>
      </thead>
      <tbody id="relTbody">
        <tr><td colspan="6" style="text-align:center;color:#666;">Search to list paid items…</td></tr>
      </tbody>
    </table>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="./script/release.js"></script>
</body>
</html>
