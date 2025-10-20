<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';

if ($_SESSION['admin_role'] !== 'superadmin') {
    header('Location: admin.php?denied=1');
    exit;
}


// Enforce admin login
if (empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$adminName = $_SESSION['admin_username'] ?? 'Admin';

// Optional: local time for footer (not required)
date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Barangay Bula • Pricing</title>

  <!-- Vendor CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    rel="stylesheet"
  />


 <link rel="stylesheet" href="./css/pricing.css">
</head>

<body>
  <!-- App Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-tags"></i> Pricing Management</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

  <main class="page-wrap">
    <div class="container">

      <!-- Page heading -->
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="section-title">
            <i class="fa-solid fa-sliders"></i>
            <span>Pricing Settings</span>
          </h2>
          
        </div>
      </div>

      <!-- Alerts mount -->
      <div id="alertWrap" class="mb-3"></div>

      <!-- Certificates -->
      <div class="card mb-4">
        <div class="card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div>Certificate Prices</div>
        </div>
        <div class="card-body">
          <!-- Table -->
          <div class="table-responsive">
            <table class="table align-middle mb-2" id="certTable">
              <thead class="table-light">
                <tr>
                  <th style="width:140px;">Code</th>
                  <th>Name</th>
                  <th style="width:160px;">Price (PHP)</th>
                  <th style="width:120px;" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="certBody">
                <!-- filled by pricing.js -->
              </tbody>
            </table>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" id="addCertRow">
              <i class="fa fa-plus me-1"></i> Add Certificate
            </button>
            <button class="btn btn-primary" id="saveCerts">
              <i class="fa fa-floppy-disk me-1"></i> Save Certificates
            </button>
          </div>


        </div>
      </div>

      <!-- Divider -->
      <div class="divider"></div>

      <!-- Gym Rates -->
      <div class="card mb-4">
        <div class="card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div>Gym Rates</div>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-sm-4 col-md-3 col-lg-2">
              <label for="morningRate" class="form-label">Morning Rate (7AM–5PM)</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" step="0.01" min="0" class="form-control" id="morningRate" placeholder="0.00">
              </div>
            </div>
            <div class="col-sm-4 col-md-3 col-lg-2">
              <label for="eveningRate" class="form-label">Evening Rate (5PM–10PM)</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" step="0.01" min="0" class="form-control" id="eveningRate" placeholder="0.00">
              </div>
            </div>
            <div class="col-auto">
              <button class="btn btn-success" id="saveGym">
                <i class="fa fa-floppy-disk me-1"></i> Save Gym Rates
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Expose CSRF to pricing.js -->
  <script>
    window.CSRF_TOKEN = <?= json_encode($csrf) ?>;
  </script>

  <!-- Vendor JS -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
  ></script>

  <!-- Page JS (your existing logic) -->
  <script src="./script/pricing.js"></script>
</body>
</html>
