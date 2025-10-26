<?php
require_once __DIR__ . '/server/config.php';
if (empty($_SESSION['admin_id'])) {
  header('Location: index.php'); exit;
}
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
  header('Location: admin.php?denied=1'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Staff Account Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="./css/officials.css">

  <style>
    /* Lightweight additions for filter bar + confirm dialog */

    .filters {
      display:flex; gap:12px; align-items:center; flex-wrap:wrap;
      padding:12px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; margin-bottom:14px;
    }
    .filters .f-group { display:flex; align-items:center; gap:8px; }
    .filters .inp, .filters .sel {
      height:36px; padding:0 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px;
    }
    .filters .toggle {
      display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none;
    }
    .filters .toggle input { width:18px; height:18px; }

    .toolbar {
      display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;
    }

    /* Confirm modal */
    .modal.backdrop { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); place-items:center; z-index:1000; }
    .modal.backdrop.open { display:grid; }
    .confirm-panel {
      width:min(460px, 92vw); background:#fff; border-radius:16px; padding:18px; box-shadow:0 10px 30px rgba(0,0,0,.15);
      animation:pop .14s ease-out;
    }
    @keyframes pop { from { transform:scale(.98); opacity:.8; } to { transform:scale(1); opacity:1; } }
    .confirm-head { display:flex; gap:10px; align-items:center; }
    .confirm-head .icon {
      width:38px; height:38px; display:grid; place-items:center; border-radius:50%;
      background:#fef3c7; color:#b45309; font-size:18px;
    }
    .confirm-title { font-weight:700; font-size:16px; color:#111827; }
    .confirm-text { color:#475569; margin-top:6px; font-size:14px; line-height:1.5; }
    .confirm-actions { margin-top:14px; display:flex; justify-content:flex-end; gap:10px; }
    .btn-ghost { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:8px 12px; cursor:pointer; }
    .btn-danger-solid { background:#dc2626; color:#fff; border:none; border-radius:10px; padding:8px 12px; cursor:pointer; }
    .btn-primary-solid { background:#2563eb; color:#fff; border:none; border-radius:10px; padding:8px 12px; cursor:pointer; }

    /* Empty state */
    .empty { text-align:center; color:#94a3b8; padding:24px; }
  </style>
</head>

<body>

  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-users-cog"></i> Staff Account Management</h1>
      <div class="header-actions">
        <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </header>

  <main class="wrap">
    <p class="sub">Create and manage officials, search/filter accounts, view last login, and control access.</p>

    <section class="card">
      <div class="card-head toolbar">
        <div class="filters">
          <div class="f-group">
            <label for="filterSearch"><i class="fa-solid fa-magnifying-glass"></i></label>
            <input id="filterSearch" class="inp" type="text" placeholder="Search name or username…" />
          </div>

          <div class="f-group">
            <label for="filterStatus"><i class="fa-solid fa-filter"></i> Status</label>
            <select id="filterStatus" class="sel">
              <option value="all">All</option>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>

          <label class="toggle f-group" title="Hide suspended accounts from the table">
            <input type="checkbox" id="toggleHideSuspended">
            <span>Hide suspended</span>
          </label>
        </div>

        <button class="btn btn-solid" id="btnAdd"><i class="fa-solid fa-user-plus" style="margin-right:6px"></i>Create Staff Account</button>
      </div>

      <div class="card-body">
        <table class="table" id="officialsTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Last login</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="emptyState" class="empty" style="display:none">No matching accounts.</div>
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
                <input class="inp" name="contact_number" id="contact_number" placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11">
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

  <!-- Created Modal -->
  <div class="modal" id="createdModal" aria-hidden="true">
    <div class="panel" role="dialog" aria-modal="true">
      <div class="panel-head">
        <strong>Official Created</strong>
        <button class="x" id="closeCreated" aria-label="Close">×</button>
      </div>
      <div class="panel-body" id="createdBody"></div>
    </div>
  </div>

  <!-- Pretty confirm modal -->
  <div class="modal backdrop" id="confirmModal" aria-hidden="true">
    <div class="confirm-panel" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
      <div class="confirm-head">
        <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
          <div id="confirmTitle" class="confirm-title">Confirm action</div>
          <div id="confirmText" class="confirm-text">Are you sure?</div>
        </div>
      </div>
      <div class="confirm-actions">
        <button class="btn-ghost" id="confirmCancel">Cancel</button>
        <button class="btn-danger-solid" id="confirmOk">Confirm</button>
      </div>
    </div>
  </div>

  <script src="./script/officials.js"></script>
</body>
</html>
