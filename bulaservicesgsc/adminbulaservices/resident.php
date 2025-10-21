<?php
require_once __DIR__ . '/server/config.php';
if (empty($_SESSION['admin_id'])) {
  header('Location: index.php');
  exit;
}

if ($_SESSION['admin_role'] !== 'superadmin') {
    header('Location: admin.php?denied=1');
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Resident Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./css/resident.css">
</head>
<body>

  <header class="page-header">
    <h1><i class="fa-solid fa-users"></i> Resident Management</h1>
    <div class="actions">
      <button id="btnAddResident" class="btn primary">
        <i class="fa-solid fa-user-plus"></i> Add Resident
      </button>
      <button id="btnRefresh" class="btn">
        <i class="fa-solid fa-rotate-right"></i> Refresh
      </button>
    </div>
  </header>

  <section class="toolbar">
    <div class="filters">
      <label>
        Gender
        <select id="filterGender">
          <option value="all">All</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>
      </label>

      <label>
        Residency
        <select id="filterResidency">
          <option value="all">All</option>
          <option value="resident">Resident</option>
          <option value="outsider">Outsider</option>
        </select>
      </label>

      <label>
        Status
        <select id="filterStatus">
          <option value="all">All</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      </label>

      <label class="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="searchBox" type="text" placeholder="Search name, email, address…" />
      </label>
    </div>

    <div class="meta">
      <span id="totalCount">0</span> result(s)
    </div>
  </section>

  <main class="table-wrap">
    <table class="grid">
      <thead>
        <tr>
          <!-- removed # -->
          <th>Profile</th>
          <th>Name</th>
          <th>Gender</th>
          <th>Birthdate</th>
          <th>Residency</th>
          <th>Status</th>
          <th>Contact</th>
          <th>Address</th>
          <!-- removed Actions -->
        </tr>
      </thead>
      <tbody id="residentsTbody">
        <tr><td colspan="8" class="center muted">Loading…</td></tr>
      </tbody>
    </table>

    <div class="pager">
      <button id="prevPage" class="btn" disabled><i class="fa-solid fa-angle-left"></i> Prev</button>
      <span id="pageInfo">Page 1</span>
      <button id="nextPage" class="btn" disabled>Next <i class="fa-solid fa-angle-right"></i></button>
    </div>
  </main>

  <!-- View Modal -->
  <div id="viewModal" class="modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" tabindex="-1">
      <div class="modal-header">
        <h3><i class="fa-solid fa-id-card"></i> Resident Details</h3>
        <button class="icon-btn close close-view"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="profile">
          <img id="vProfile" src="" alt="Profile">
          <div class="names">
            <div id="vName" class="big"></div>
            <div id="vEmail" class="muted"></div>
          </div>
        </div>

        <div class="grid2">
          <div><span class="label">Gender</span><span id="vGender">—</span></div>
          <div><span class="label">Birthdate</span><span id="vDob">—</span></div>
          <div><span class="label">Residency</span><span id="vResidency">—</span></div>
          <div><span class="label">Contact</span><span id="vContact">—</span></div>
          <div class="span2"><span class="label">Address</span><span id="vAddress">—</span></div>
          <div><span class="label">Status</span><span id="vStatus">—</span></div>
          <div><span class="label">Created</span><span id="vCreated">—</span></div>
        </div>
      </div>
      <div class="modal-actions">
        <button id="btnToggleStatus" class="btn"></button>
        <button class="btn close-view">Close</button>
      </div>
    </div>
  </div>

  <script src="./script/resident.js"></script>
</body>
</html>