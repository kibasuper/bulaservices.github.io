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

/* Live price for Low Income Certificate (type_code = 'lic') */
try {
    $db  = getDBConnection();
    if ($db instanceof PDO) $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
    $stmt->execute(['lic']);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $LIC_PRICE = isset($row['price']) ? (float)$row['price'] : 80.00; // fallback
} catch (Throwable $e) {
    error_log("LIC price fetch error: " . $e->getMessage());
    $LIC_PRICE = 80.00;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Low Income Certificate | Barangay Bula</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="./style/lic.css">
  <link rel="stylesheet" href="./style/bc.css">
  <style>
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
      <img src="./pics/logo.png" alt="Barangay Logo"> Barangay Bula
    </a>
  </nav>

  <main class="main-content">
    <div class="container">
      <section class="form-container">
        <div class="progress-steps">
          <div class="step active" id="step1"><div class="step-number">1</div><div class="step-label">Personal Information</div></div>
          <div class="step" id="step2"><div class="step-number">2</div><div class="step-label">Purpose & Fees</div></div>
          <div class="step" id="step3"><div class="step-number">3</div><div class="step-label">Requirements Upload</div></div>
          <div class="step" id="step4"><div class="step-number">4</div><div class="step-label">Review & Submit</div></div>
        </div>

        <div class="form-header">
          <h2><i class="fas fa-file-invoice-dollar"></i> Low Income Certificate</h2>
          <p>Fill out the form to request a Low Income Certificate</p>
        </div>

        <form id="licForm" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="service_type" id="serviceType" value="low_income">

          <!-- Section 1 -->
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

          <!-- Section 2 -->
          <div class="form-section" id="section2" aria-labelledby="step2">
            <div class="purpose-options">
              <label style="display:block;margin-bottom:.5rem;font-weight:500;color:#2c3e50;">Purpose of Certificate*</label>

              <div class="purpose-option" onclick="selectPurpose('Scholarship')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="scholarshipPurpose" name="purpose" value="Scholarship Application" required>
                <label for="scholarshipPurpose">Scholarship Application</label>
              </div>

              <div class="purpose-option" onclick="selectPurpose('GovtAssist')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="govtassistPurpose" name="purpose" value="Government Assistance">
                <label for="govtassistPurpose">Government Assistance</label>
              </div>

              <div class="purpose-option" onclick="selectPurpose('Loan')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="loanPurpose" name="purpose" value="Loan Application">
                <label for="loanPurpose">Loan Application</label>
              </div>

              <div class="purpose-option" onclick="selectPurpose('Other')" tabindex="0" role="button" aria-pressed="false">
                <input type="radio" id="otherPurposeRadio" name="purpose" value="Other">
                <label for="otherPurposeRadio">Other Purpose</label>
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
                  <input type="number" id="copyQuantity" name="copies" class="form-control" min="1" max="5" value="1" inputmode="numeric">
                  <span id="perCopyText">× ₱<?= number_format($LIC_PRICE, 2) ?> per copy</span>
                </div>
              </div>
              <div id="feeResult" class="fee-result">
                Total Fee: ₱<span id="calculatedFee"><?= number_format($LIC_PRICE, 2) ?></span>
              </div>
            </div>

            <div class="nav-buttons">
              <button type="button" class="btn btn-secondary" id="prevBtn1"><i class="fas fa-arrow-left"></i> Previous</button>
              <button type="button" class="btn" id="nextBtn2">Next <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>

          <!-- Section 3: Requirements -->
          <div class="form-section" id="section3" aria-labelledby="step3">
            <div class="document-options">
              <label style="display:block;margin-bottom:.5rem;font-weight:500;color:#2c3e50;">Requirements (choose method for each)</label>

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

              <!-- Proof of Income -->
              <div class="req-block" data-key="proof_of_income">
                <div class="req-title"><i class="fas fa-receipt"></i> Proof of Income (Payslip, COE, etc.)*</div>
                <div class="req-method">
                  <label><input type="radio" name="req_method[proof_of_income]" value="upload" required> Upload now</label>
                  <label><input type="radio" name="req_method[proof_of_income]" value="hall"> Bring to Hall</label>
                </div>
                <div class="file-upload-container" id="req_ui_proof_of_income" style="display:none">
                  <input type="file" id="req_proof_of_income" name="requirements[proof_of_income]" class="file-upload-input" accept="image/*,.pdf" aria-describedby="poiHelp">
                  <label for="req_proof_of_income" class="file-upload-button"><i class="fas fa-upload"></i> Choose File (JPG, PNG, PDF, max 5MB)</label>
                  <div class="file-upload-name" id="name_req_proof_of_income">No file chosen</div>
                  <div class="error-message" id="err_req_proof_of_income">Please upload Proof of Income</div>
                  <p class="note" id="poiHelp">Maximum file size: 5MB. Accepted formats: JPG, PNG, PDF</p>
                </div>
              </div>

              <div class="error-message" id="documentMethodError">Please select a method for each requirement</div>
            </div>

            <div class="processing-info">
              <h4><i class="fas fa-clock"></i> Processing Information</h4>
              <p><strong>Processing Time:</strong> 3–5 business days</p>
              <p>You will receive an e-mail notification once your certificate is ready for pickup.</p>
            </div>

            <div class="walkin-hours">
              <h4><i class="fas fa-door-open"></i> Pickup Hours</h4>
              <p><strong>Monday to Friday:</strong> 8:00 AM – 5:00 PM</p>
              <p><strong>Saturday:</strong> 8:00 AM – 12:00 PM</p>
              <p><strong>Location:</strong> Barangay Bula Hall, Purok 5</p>
              <p class="note">Please bring a valid ID when picking up your documents.</p>
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
                  <h5>Purpose & Copies</h5>
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
              <button type="submit" id="submitApplication" class="btn"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </div>
          </div>
        </form>

        <div class="requirements">
          <h3><i class="fas fa-clipboard-list"></i> Required Documents</h3>
          <ul class="requirements-list">
            <li><i class="fas fa-check-circle"></i><span>Purok Clearance</span></li>
            <li><i class="fas fa-check-circle"></i><span>Valid ID (Government-issued)</span></li>
            <li><i class="fas fa-check-circle"></i><span>Proof of Income (Payslip, COE, etc.)</span></li>
          </ul>
          <p class="note">Note: This certificate is valid for six months from issuance.</p>
        </div>
      </section>
    </div>
  </main>

  <!-- Success Modal -->
  <div id="successModal" class="success-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="success-modal-content">
      <span class="close-modal" tabindex="0" aria-label="Close modal">&times;</span>
      <i class="fas fa-check-circle" aria-hidden="true"></i>
      <h3 id="modalTitle">Application Submitted Successfully!</h3>
      <p>Your Low Income Certificate application has been received.</p>
      <div class="reference-number" id="referenceNumber"></div>
      <div class="amount-due" id="amountDue"></div>
      <button id="closeModalBtn" class="btn"><i class="fas fa-thumbs-up"></i> Return to Home Page</button>
    </div>
  </div>

  <!-- Pretty Confirm -->
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
    window.PRICE_TYPE = 'lic';
    window.LIC_PRICE = <?= json_encode((float)$LIC_PRICE) ?>;
    window.REQUIRED_REQS = [
      { key: 'purok_clearance',   label: 'Purok Clearance' },
      { key: 'valid_id',          label: 'Valid ID (Government-issued)' },
      { key: 'proof_of_income',   label: 'Proof of Income' }
    ];
  </script>
  <script src="./script/lic.js?v=5"></script>
</body>
</html>
