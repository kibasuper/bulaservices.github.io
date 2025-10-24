<?php
require_once __DIR__ . '/server/config.php';
if (empty($_SESSION['admin_id'])) {
  header('Location: index.php');
  exit;
}
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    header('Location: admin.php?denied=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Officials Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <!-- Page stylesheet -->
  <link rel="stylesheet" href="./css/officials.css">
</head>

<body>
  <!-- Top header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-users-cog"></i> Officials Management</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

  <main class="wrap">
    <p class="sub">Create and manage officials, view last login timelines, and control access.</p>

    <section class="card">
      <div class="card-head">
        <div></div>
        <button class="btn btn-solid" id="btnAdd">Create Official</button>
      </div>
      <div class="card-body">
        <table class="table" id="officialsTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Last login</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody><!-- rows injected by JS --></tbody>
        </table>
      </div>
    </section>
  </main>

  <!-- Create Modal -->
  <div class="modal" id="createModal" aria-hidden="true">
    <div class="panel" role="dialog" aria-modal="true">
      <div class="panel-head">
        <strong>Create Official</strong>
        <button class="x" id="closeCreate" aria-label="Close">×</button>
      </div>
      <div class="panel-body">
        <form id="formCreate" enctype="multipart/form-data" novalidate>
          <!-- Photo -->
          <div class="section">
            <h3>Photo</h3>
            <div class="row">
              <img id="createPhotoPreview" class="photo" alt="">
              <div class="grid">
                <input class="inp" type="file" name="photo" id="createPhoto" accept="image/*">
                <small class="muted">JPEG/PNG/GIF up to 2MB</small>
              </div>
            </div>
          </div>

          <div class="hr"></div>

          <!-- Basic info (email optional here) -->
          <div class="section">
            <h3>Basic information</h3>
            <div class="grid g-2">
              <div>
                <div class="lbl">First name</div>
                <input class="inp" name="first_name" required>
              </div>
              <div>
                <div class="lbl">Last name</div>
                <input class="inp" name="last_name" required>
              </div>

              <div>
                <div class="lbl">Birthdate</div>
                <input class="inp" type="date" name="birthdate" id="birthdate">
              </div>
              <div>
                <div class="lbl">Age</div>
                <input class="inp" type="number" name="age" id="age" inputmode="numeric" readonly placeholder="Auto">
              </div>

              <div>
                <div class="lbl">Sex</div>
                <select class="sel" name="sex">
                  <option value="">—</option>
                  <option>Male</option>
                  <option>Female</option>
                </select>
              </div>
              <div>
                <div class="lbl">Religion</div>
                <select class="sel" name="religion" id="religion">
                  <option value="">—</option>
                  <option>Roman Catholic</option>
                  <option>Islam</option>
                  <option>Iglesia ni Cristo</option>
                  <option>Evangelical/Protestant</option>
                  <option>Seventh-day Adventist</option>
                  <option>Buddhism</option>
                  <option>Hinduism</option>
                  <option>Indigenous/Tribal</option>
                  <option>None</option>
                  <option>Other</option>
                </select>
              </div>

              <div>
                <div class="lbl">Email <span class="muted">(optional)</span></div>
                <input class="inp" type="email" name="email" placeholder="name@example.com">
              </div>
              <div>
                <div class="lbl">Contact number</div>
                <input class="inp" name="contact_number" id="contact_number"
                       placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11">
                <small class="muted">Must be exactly 11 digits and start with 0.</small>
              </div>
            </div>
            <div class="mt10">
              <div class="lbl">Address</div>
              <textarea class="txt" name="address" rows="2"></textarea>
            </div>
            <div id="religionOtherWrap" class="mt10" style="display:none">
              <div class="lbl">If “Other”, specify</div>
              <input class="inp" name="religion_other" id="religion_other" placeholder="Religion">
            </div>
          </div>

          <div class="hr"></div>

          <!-- Account -->
          <div class="section">
            <h3>Account details</h3>
            <div class="grid g-2">
              <div>
                <div class="lbl">Username</div>
                <input class="inp" name="username" required>
              </div>
              <div>
                <div class="lbl">Role</div>
                <select class="sel" name="role">
                  <option value="admin">admin</option>
                  <option value="staff">staff</option>
                </select>
              </div>
            </div>
          </div>

          <div class="right mt16">
            <button type="button" class="btn" id="closeCreate2">Cancel</button>
            <button class="btn btn-solid" type="submit">Create Official</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Details Modal -->
  <div class="modal" id="detailsModal" aria-hidden="true">
    <div class="panel" role="dialog" aria-modal="true">
      <div class="panel-head">
        <strong>Official Details</strong>
        <button class="x" id="closeDetails" aria-label="Close">×</button>
      </div>
      <div class="panel-body" id="detailsBody">
        <p class="muted">Loading…</p>
      </div>
    </div>
  </div>

  <!-- Create Success Modal -->
  <div class="modal" id="createdModal" aria-hidden="true">
    <div class="panel" role="dialog" aria-modal="true">
      <div class="panel-head">
        <strong>Official Created</strong>
        <button class="x" id="closeCreated" aria-label="Close">×</button>
      </div>
      <div class="panel-body" id="createdBody"><!-- injected by JS --></div>
    </div>
  </div>

  <script src="./script/officials.js"></script>
</body>
</html>
