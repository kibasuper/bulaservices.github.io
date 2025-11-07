<?php
require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/file_urls.php';
ensureUserAccess();
$CSRF = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - Barangay Bula</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="./style/profile.css">
  <style>
    /* lightweight modal */
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:9999}
    .modal{background:#fff;border-radius:.75rem;padding:1.25rem;max-width:420px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .modal h3{margin-bottom:.75rem}
    .modal-actions{display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem}
    .input-inline{width:100%}
  </style>
</head>
<div class="toast-host" id="toastHost" aria-live="polite" aria-atomic="true"></div>
<body>
  <div class="container">
    <div class="profile-card">
      <div class="profile-header">
        <div class="profile-pic-container">
          <img id="profileImage" src="./pics/profile-placeholder.jpg" alt="Profile Picture" class="profile-pic">
          <button class="edit-pic-btn" id="editPicBtn"><i class="fas fa-camera"></i></button>
          <input type="file" id="profilePicInput" accept="image/*" style="display:none;">
        </div>
        <h1 class="profile-name" id="profileName">—</h1>
        <p class="profile-subtitle">Resident of Barangay Bula</p>
      </div>

      <div class="profile-content">
        <!-- Left: Personal -->
        <div>
          <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-user-circle"></i> Personal Information</h2>

            <div class="info-grid">
              <!-- First Name -->
              <div class="info-item">
                <div class="info-label">First Name:</div>
                <div class="info-value" id="firstNameValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('firstName')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="firstNameForm">
                <div class="form-group"><label for="firstNameInput">First Name</label><input type="text" id="firstNameInput" class="form-control"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('firstName')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('firstName')">Cancel</button></div>
              </div>

              <!-- Middle Name -->
              <div class="info-item">
                <div class="info-label">Middle Name:</div>
                <div class="info-value" id="middleNameValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('middleName')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="middleNameForm">
                <div class="form-group"><label for="middleNameInput">Middle Name</label><input type="text" id="middleNameInput" class="form-control"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('middleName')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('middleName')">Cancel</button></div>
              </div>

              <!-- Last Name -->
              <div class="info-item">
                <div class="info-label">Last Name:</div>
                <div class="info-value" id="lastNameValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('lastName')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="lastNameForm">
                <div class="form-group"><label for="lastNameInput">Last Name</label><input type="text" id="lastNameInput" class="form-control"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('lastName')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('lastName')">Cancel</button></div>
              </div>

              <!-- Suffix -->
              <div class="info-item">
                <div class="info-label">Suffix:</div>
                <div class="info-value" id="suffixValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('suffix')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="suffixForm">
                <div class="form-group"><label for="suffixInput">Suffix</label><input type="text" id="suffixInput" class="form-control" placeholder="Jr, Sr, II, etc."></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('suffix')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('suffix')">Cancel</button></div>
              </div>

              <!-- Place of Birth -->
              <div class="info-item">
                <div class="info-label">Place of Birth:</div>
                <div class="info-value" id="birthPlaceValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('birthPlace')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="birthPlaceForm">
                <div class="form-group"><label for="birthPlaceInput">Place of Birth</label><input type="text" id="birthPlaceInput" class="form-control"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('birthPlace')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('birthPlace')">Cancel</button></div>
              </div>

              <!-- Birthdate -->
              <div class="info-item">
                <div class="info-label">Birthdate:</div>
                <div class="info-value" id="birthDateValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('birthDate')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="birthDateForm">
                <div class="form-group"><label for="birthDateInput">Birthdate</label><input type="date" id="birthDateInput" class="form-control"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('birthDate')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('birthDate')">Cancel</button></div>
              </div>

              <!-- Age (read-only) -->
              <div class="info-item"><div class="info-label">Age:</div><div class="info-value read-only" id="ageValue">—</div></div>

              <!-- Sex -->
              <div class="info-item">
                <div class="info-label">Sex:</div>
                <div class="info-value" id="sexValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('sex')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="sexForm">
                <div class="form-group">
                  <label for="sexInput">Sex</label>
                  <select id="sexInput" class="form-control">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('sex')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('sex')">Cancel</button></div>
              </div>

              <!-- Civil Status -->
              <div class="info-item">
                <div class="info-label">Civil Status:</div>
                <div class="info-value" id="civilStatusValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('civilStatus')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="civilStatusForm">
                <div class="form-group">
                  <label for="civilStatusInput">Civil Status</label>
                  <select id="civilStatusInput" class="form-control">
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                  </select>
                </div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('civilStatus')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('civilStatus')">Cancel</button></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Residence + Account -->
        <div>
          <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-home"></i> Residence Information</h2>
            <div class="info-grid">
              <div class="info-item">
                <div class="info-label">Purok:</div>
                <div class="info-value" id="purokValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('purok')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="purokForm">
                <div class="form-group"><label for="purokInput">Purok</label><input
                                          type="number"
                                          id="purokInput"
                                          class="form-control"
                                          min="1" max="25" step="1"
                                          inputmode="numeric" pattern="\d*"
                                          placeholder="Enter a number from 1 to 25">
                                        <small class="muted">Allowed values: 1–25.</small></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('purok')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('purok')">Cancel</button></div>
              </div>

              <div class="info-item">
                <div class="info-label">Year Started Staying:</div>
                <div class="info-value" id="yearStartedValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('yearStarted')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="yearStartedForm">
                <div class="form-group"><label for="yearStartedInput">Year</label><input type="number" id="yearStartedInput" class="form-control" min="1900" max="2099"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('yearStarted')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('yearStarted')">Cancel</button></div>
              </div>

              <div class="info-item">
                <div class="info-label">Contact Number:</div>
                <div class="info-value" id="contactNumberValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('contactNumber')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="contactNumberForm">
                <div class="form-group"><label for="contactNumberInput">Contact Number</label><input
                                                                                                type="tel"
                                                                                                id="contactNumberInput"
                                                                                                class="form-control"
                                                                                                inputmode="numeric"
                                                                                                maxlength="11"
                                                                                                pattern="09\d{9}"
                                                                                                placeholder="09XXXXXXXXX"
                                                                                                aria-describedby="contactHelp">
                                                                                              <small id="contactHelp" class="muted">Format: 11 digits, must start with 09.</small></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('contactNumber')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('contactNumber')">Cancel</button></div>
              </div>

              <div class="info-item">
                <div class="info-label">Occupation:</div>
                <div class="info-value" id="occupationValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('occupation')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="occupationForm">
                <div class="form-group"><label for="occupationInput">Occupation</label><input type="text" id="occupationInput" class="form-control"></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('occupation')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('occupation')">Cancel</button></div>
              </div>

              <div class="info-item">
                <div class="info-label">Complete Address:</div>
                <div class="info-value" id="addressValue">—</div>
                <button class="edit-btn" onclick="toggleEdit('address')"><i class="fas fa-edit"></i></button>
              </div>
              <div class="edit-form" id="addressForm">
                <div class="form-group"><label for="addressInput">Complete Address</label><textarea id="addressInput" class="form-control"></textarea></div>
                <div class="form-actions"><button class="btn btn-primary" onclick="saveField('address')">Save</button><button class="btn btn-secondary" onclick="cancelEdit('address')">Cancel</button></div>
              </div>
            </div>
          </div>

          <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-id-card"></i> Account Information</h2>
            <div class="info-grid">
              <div class="info-item"><div class="info-label">Email (read-only):</div><div class="info-value read-only" id="emailValue">—</div></div>
              <div class="info-item"><div class="info-label">Username (read-only):</div><div class="info-value read-only" id="usernameValue">—</div></div>
              <div class="info-item"><div class="info-label">User Type:</div><div class="info-value read-only" id="userTypeValue">—</div></div>
              <div class="info-item"><div class="info-label">Status:</div><div class="info-value read-only" id="statusValue">—</div></div>
              <div class="info-item"><div class="info-label">Active:</div><div class="info-value read-only" id="activeValue">—</div></div>
              <div class="info-item"><div class="info-label">Email Verified:</div><div class="info-value read-only" id="emailVerifiedValue">—</div></div>
              <div class="info-item"><div class="info-label">Last Login:</div><div class="info-value read-only" id="lastLoginValue">—</div></div>
              <div class="info-item"><div class="info-label">Created At:</div><div class="info-value read-only" id="createdAtValue">—</div></div>
              <div class="info-item"><div class="info-label">Updated At:</div><div class="info-value read-only" id="updatedAtValue">—</div></div>
            </div>
          </div>
        </div>

        <!-- Change Password -->
        <div class="profile-section">
          <h2 class="section-title"><i class="fas fa-key"></i> Change Password</h2>
          <p class="muted" style="margin: .25rem 0 .75rem;">
            Enter your current password and your new password twice to confirm.
          </p>

          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Current Password:</div>
              <div class="info-value">
                <input type="password" id="pwOld" class="form-control" autocomplete="current-password" placeholder="Current password">
              </div>
            </div>

            <div class="info-item">
              <div class="info-label">New Password:</div>
              <div class="info-value">
                <input type="password" id="pwNew" class="form-control" autocomplete="new-password" placeholder="New password (min 8 chars)">
              </div>
            </div>

            <div class="info-item">
              <div class="info-label">Confirm New Password:</div>
              <div class="info-value">
                <input type="password" id="pwNew2" class="form-control" autocomplete="new-password" placeholder="Repeat new password">
              </div>
            </div>
          </div>

          <div class="form-actions" style="margin-top:.75rem; display:flex; gap:.5rem;">
            <button class="btn btn-primary" id="changePwBtn"><i class="fas fa-save"></i> Update Password</button>
            <button class="btn btn-secondary" id="changePwClearBtn" type="button">Clear</button>
          </div>
        </div>


        <!-- Security note -->
        <div class="security-section">
          <h2 class="section-title"><i class="fas fa-lock"></i> Edit Security</h2>
          <p>To save any profile changes, you will be asked to <strong>enter your account password</strong> for confirmation.</p>
        </div>

        <!-- Actions -->
        <div class="profile-actions">
          <button class="btn btn-secondary btn-lg" onclick="window.location.href='home.php'"><i class="fas fa-arrow-left"></i> Back to Home</button>
          <button class="btn btn-primary btn-lg" id="saveAllBtn"><i class="fas fa-save"></i> Save All Changes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Password confirmation modal -->
  <div class="modal-backdrop" id="pwModalBackdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="pwModalTitle">
      <h3 id="pwModalTitle">Enter Password to Save</h3>
      <p>Please confirm your identity to apply the changes.</p>
      <input type="password" id="pwConfirmInput" class="form-control input-inline" placeholder="Your account password">
      <div class="modal-actions">
        <button class="btn btn-secondary" onclick="closePwModal()">Cancel</button>
        <button class="btn btn-primary" id="pwConfirmBtn">Confirm & Save</button>
      </div>
    </div>
  </div>

  <script>
    window.CSRF_TOKEN = <?= json_encode($CSRF) ?>;
    window.PROFILE_API = "./php/profile_api.php";
  </script>
  <script src="./script/profile.js"></script>
</body>
</html>
