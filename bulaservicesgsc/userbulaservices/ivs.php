<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/certificate_functions.php';

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

ensureUserAccess();
$csrfToken = generateCsrfToken();

$svc  = new CertificateRequest();
$user = $svc->getUserInfo();
if (empty($user)) {
    $_SESSION['last_error'] = 'User information not found. Please log in again.';
    redirect_root('index.php?error=' . urlencode('Please log in to access this page.'));
    exit;
}

/* === Live price for IVS (type_code = 'ivs') === */
try {
    $db  = getDBConnection();
    if ($db instanceof PDO) $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
    $stmt->execute(['ivs']);
    $row       = $stmt->fetch(PDO::FETCH_ASSOC);
    $IVS_PRICE = isset($row['price']) ? (float)$row['price'] : 100.00;
} catch (Throwable $e) {
    error_log("IVS price fetch error: ".$e->getMessage());
    $IVS_PRICE = 100.00;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Individual Voluntary Statement | Barangay Bula</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Shared wizard styles -->
  <link rel="stylesheet" href="./style/bc.css">
  <!-- Optional IVS tweaks -->
  <link rel="stylesheet" href="./style/ivs.css">
  <style>
    /* lightweight confirm modal */
    .ui-confirm{position:fixed;inset:0;display:none;place-items:center;background:rgba(0,0,0,.45);z-index:1000;padding:1rem}
    .ui-confirm.is-open{display:grid}
    .ui-confirm__dialog{width:100%;max-width:440px;background:#fff;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.2);padding:1.25rem 1.25rem 1rem}
    .ui-confirm__icon{font-size:28px;color:#e74c3c;margin-bottom:.5rem}
    .ui-confirm__title{margin:0 0 .25rem;font-size:1.25rem;color:#2c3e50}
    .ui-confirm__text{margin:0 0 1rem;color:#5b6b7a;line-height:1.45}
    .ui-confirm__actions{display:flex;gap:.5rem;justify-content:flex-end}
  </style>
</head>
<body>
  <nav class="navbar">
    <a href="home.php" class="navbar-brand">
      <img src="./pics/logo.png" alt="Barangay Bula Logo"> Barangay Bula
    </a>
  </nav>

  <main class="main-content">
    <div class="container">
      <section class="form-container">
        <div class="progress-steps">
          <div class="step active" id="step1"><div class="step-number">1</div><div class="step-label">Personal Information</div></div>
          <div class="step" id="step2"><div class="step-number">2</div><div class="step-label">Statement Details & Copies</div></div>
          <div class="step" id="step3"><div class="step-number">3</div><div class="step-label">Requirements Upload</div></div>
          <div class="step" id="step4"><div class="step-number">4</div><div class="step-label">Review & Submit</div></div>
        </div>

        <div class="form-header">
          <h2><i class="fas fa-file-signature"></i> Individual Voluntary Statement</h2>
          <p>Fill out the form to submit your statement</p>
        </div>

        <form id="ivsForm" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="service_type" id="serviceType" value="ivs"><!-- IMPORTANT -->

          <!-- Section 1: Personal -->
          <div class="form-section active" id="section1" aria-labelledby="step1">
            <div class="form-group">
              <label for="fullName">Full Name</label>
              <input type="text" id="fullName" class="form-control auto-filled" readonly value="<?= e($user['fullName'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="contactNumber">Contact Number</label>
              <input type="text" id="contactNumber" class="form-control auto-filled" readonly value="<?= e($user['contactNumber'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="address">Complete Address</label>
              <input type="text" id="address" class="form-control auto-filled" readonly value="<?= e($user['address'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="yearOfStay">Year Started Living in Barangay</label>
              <input type="text" id="yearOfStay" class="form-control auto-filled" readonly value="<?= e($user['yearOfStay'] ?? '') ?>">
            </div>

            <div class="nav-buttons">
              <button type="button" class="btn btn-danger" id="cancelBtn"><i class="fas fa-times"></i> Cancel</button>
              <button type="button" class="btn" id="nextBtn1">Next <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>

          <!-- Section 2: Purpose & Fee -->
          <div class="form-section" id="section2" aria-labelledby="step2">
            <div class="purpose-options">
              <label style="display:block;margin-bottom:.5rem;font-weight:500;color:#2c3e50;">Purpose of Statement*</label>

              <div class="purpose-option" onclick="selectPurpose('Legal')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="legalPurpose" name="purpose" value="Legal Documentation" required>
                <label for="legalPurpose">Legal Documentation</label>
              </div>
              <div class="purpose-option" onclick="selectPurpose('Affidavit')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="affidavitPurpose" name="purpose" value="Affidavit Requirement">
                <label for="affidavitPurpose">Affidavit Requirement</label>
              </div>
              <div class="purpose-option" onclick="selectPurpose('Government')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="governmentPurpose" name="purpose" value="Government Transaction">
                <label for="governmentPurpose">Government Transaction</label>
              </div>
              <div class="purpose-option" onclick="selectPurpose('Bank')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="bankPurpose" name="purpose" value="Bank Requirement">
                <label for="bankPurpose">Bank Requirement</label>
              </div>
              <div class="purpose-option" onclick="selectPurpose('Employment')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="employmentPurpose" name="purpose" value="Employment Requirement">
                <label for="employmentPurpose">Employment Requirement</label>
              </div>
              <div class="purpose-option" onclick="selectPurpose('Other')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="otherPurpose" name="purpose" value="Other">
                <label for="otherPurpose">Other Purpose</label>
              </div>

              <div class="other-purpose-container" id="otherPurposeContainer" style="display:none;">
                <label for="specifyPurpose">Please specify purpose*</label>
                <input type="text" id="specifyPurpose" name="purpose_details" class="form-control">
                <div class="error-message" id="specifyPurposeError">Please specify your purpose</div>
              </div>
              <div class="error-message" id="purposeError">Please select a purpose</div>
            </div>

            <!-- Fee Calculator -->
            <div class="fee-calculator">
              <h4><i class="fas fa-calculator"></i> Fee Calculator</h4>
              <div class="form-group">
                <label for="copyQuantity">Number of Copies Needed</label>
                <div class="copy-quantity">
                  <input type="number" id="copyQuantity" name="copies" class="form-control" min="1" max="10" value="1" inputmode="numeric">
                  <span id="perCopyText">× ₱<?= number_format($IVS_PRICE, 2) ?> per copy</span>
                </div>
              </div>
              <div id="feeResult" class="fee-result">
                Total Fee: ₱<span id="calculatedFee"><?= number_format($IVS_PRICE, 2) ?></span>
              </div>
            </div>

            <div class="nav-buttons">
              <button type="button" class="btn btn-secondary" id="prevBtn1"><i class="fas fa-arrow-left"></i> Previous</button>
              <button type="button" class="btn" id="nextBtn2">Next <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>

          <!-- Section 3: Requirements Upload (vertical cards) -->
          <div class="form-section" id="section3" aria-labelledby="step3">
            <div class="document-options">
              <label style="display:block;margin-bottom:.5rem;font-weight:500;color:#2c3e50;">Requirements (choose per requirement)</label>

              <!-- Purok Clearance -->
              <div class="req-block" data-key="purok_clearance">
                <div class="req-title"><i class="fas fa-file"></i> Purok Clearance*</div>
                <div class="req-method">
                  <label><input type="radio" name="req_method[purok_clearance]" value="upload" required> Upload now</label>
                  <label><input type="radio" name="req_method[purok_clearance]" value="hall"> Bring to Hall</label>
                </div>
                <div class="file-upload-container" id="req_ui_purok_clearance" style="display:none">
                  <input type="file" id="req_purok_clearance" name="requirements[purok_clearance]" class="file-upload-input" accept="image/*,.pdf" aria-describedby="purokHelp">
                  <label for="req_purok_clearance" class="file-upload-button"><i class="fas fa-upload"></i> Choose File (JPG, PNG, PDF, max 5MB)</label>
                  <div class="file-upload-name" id="name_req_purok_clearance">No file chosen</div>
                  <div class="error-message" id="err_req_purok_clearance">Please upload your Purok Clearance</div>
                  <p class="note" id="purokHelp">Maximum file size: 5MB. Accepted formats: JPG, PNG, PDF</p>
                </div>
              </div>

              <!-- Valid ID -->
              <div class="req-block" data-key="valid_id">
                <div class="req-title"><i class="fas fa-id-card"></i> Valid ID (Government-issued)*</div>
                <div class="req-method">
                  <label><input type="radio" name="req_method[valid_id]" value="upload" required> Upload now</label>
                  <label><input type="radio" name="req_method[valid_id]" value="hall"> Bring to Hall</label>
                </div>
                <div class="file-upload-container" id="req_ui_valid_id" style="display:none">
                  <input type="file" id="req_valid_id" name="requirements[valid_id]" class="file-upload-input" accept="image/*,.pdf" aria-describedby="validIdHelp">
                  <label for="req_valid_id" class="file-upload-button"><i class="fas fa-upload"></i> Choose File (JPG, PNG, PDF, max 5MB)</label>
                  <div class="file-upload-name" id="name_req_valid_id">No file chosen</div>
                  <div class="error-message" id="err_req_valid_id">Please upload a Valid ID</div>
                  <p class="note" id="validIdHelp">Maximum file size: 5MB. Accepted formats: JPG, PNG, PDF</p>
                </div>
              </div>

              <div class="error-message" id="documentMethodError">Please select a method for each requirement</div>
            </div>

            <div class="processing-info">
              <h4><i class="fas fa-clock"></i> Processing Information</h4>
              <p><strong>Processing Time:</strong> 3–5 business days</p>
              <p>You will receive an e-mail once your statement has been processed.</p>
              <p class="note">Note: Processing may take longer during peak periods.</p>
            </div>

            <div class="walkin-hours">
              <h4><i class="fas fa-door-open"></i> Office Hours</h4>
              <p><strong>Monday to Friday:</strong> 8:00 AM – 5:00 PM</p>
              <p><strong>Saturday:</strong> 8:00 AM – 12:00 PM</p>
              <p><strong>Location:</strong> Barangay Bula Hall, Purok 5</p>
              <p class="note">Please bring a valid ID when visiting the barangay hall.</p>
            </div>

            <div class="nav-buttons">
              <button type="button" class="btn btn-secondary" id="prevBtn2"><i class="fas fa-arrow-left"></i> Previous</button>
              <button type="button" class="btn" id="nextBtn3">Next <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>

          <!-- Section 4: Review & Submit -->
          <div class="form-section" id="section4" aria-labelledby="step4">
            <div class="review-wrap">
              <h4><i class="fas fa-eye"></i> Review your request</h4>

              <div class="review-grid">
                <div class="review-card">
                  <h5>Personal Information</h5>
                  <ul class="review-list" id="revPersonal"></ul>
                </div>

                <div class="review-card">
                  <h5>Statement Details & Copies</h5>
                  <ul class="review-list" id="revPurpose"></ul>
                </div>

                <div class="review-card">
                  <h5>Requirements</h5>
                  <div id="revReqs" class="review-reqs"></div>
                </div>

                <div class="review-card total">
                  <h5>Total Fee</h5>
                  <div class="fee-big">₱<span id="revTotal">0.00</span></div>
                </div>
              </div>
            </div>

            <div class="nav-buttons">
              <button type="button" class="btn btn-secondary" id="prevBtn3"><i class="fas fa-arrow-left"></i> Previous</button>
              <button type="submit" id="submitApplication" class="btn"><i class="fas fa-paper-plane"></i> Submit Statement</button>
            </div>
          </div>
        </form>

        <div class="requirements">
          <h3><i class="fas fa-clipboard-list"></i> Important Information</h3>
          <ul class="requirements-list">
            <li><i class="fas fa-check-circle"></i><span>Statement will be notarized at the Barangay Hall</span></li>
            <li><i class="fas fa-check-circle"></i><span>Please bring two valid IDs for notarization</span></li>
            <li><i class="fas fa-check-circle"></i><span>Witnesses must be present during notarization if applicable</span></li>
          </ul>
          <p class="note">Note: This statement becomes an official document once notarized.</p>
        </div>
      </section>
    </div>
  </main>

  <!-- Success Modal -->
  <div id="successModal" class="success-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="success-modal-content">
      <span class="close-modal" tabindex="0" aria-label="Close modal">&times;</span>
      <i class="fas fa-check-circle" aria-hidden="true"></i>
      <h3 id="modalTitle">Statement Submitted Successfully!</h3>
      <p>Your Individual Voluntary Statement has been received.</p>
      <div class="reference-number" id="referenceNumber"></div>
      <div class="amount-due" id="amountDue"></div>
      <button id="closeModalBtn" class="btn"><i class="fas fa-thumbs-up"></i> Return to Home Page</button>
    </div>
  </div>

  <!-- Pretty Confirm Modal -->
  <div id="uiConfirm" class="ui-confirm" aria-hidden="true">
    <div class="ui-confirm__dialog" role="dialog" aria-modal="true" aria-labelledby="uiConfirmTitle">
      <div class="ui-confirm__icon"><i class="fas fa-circle-exclamation"></i></div>
      <h3 id="uiConfirmTitle" class="ui-confirm__title">Cancel application?</h3>
      <p class="ui-confirm__text">Are you sure you want to cancel? Any unsaved changes will be lost.</p>
      <div class="ui-confirm__actions">
        <button type="button" id="uiConfirmCancel" class="btn btn-secondary">Stay</button>
        <button type="button" id="uiConfirmOk" class="btn btn-danger">Yes, cancel</button>
      </div>
    </div>
  </div>

  <script>
    window.PRICE_TYPE = 'ivs';
    window.IVS_PRICE  = <?= json_encode((float)$IVS_PRICE) ?>;
    window.REQUIRED_REQS = [
      { key: 'purok_clearance', label: 'Purok Clearance' },
      { key: 'valid_id',        label: 'Valid ID (Government-issued)' }
    ];
  </script>
  <script src="./script/ivs.js?v=4"></script>
</body>
</html>
