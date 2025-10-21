<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/certificate_functions.php';

function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

ensureUserAccess();
$csrfToken = generateCsrfToken();

$certificate = new CertificateRequest();
$userInfo    = $certificate->getUserInfo();
if (empty($userInfo)) {
    $_SESSION['last_error'] = 'User information not found. Please log in again.';
    redirect_root('index.php?error=' . urlencode('Please log in to access this page.'));
    exit;
}

/* === NEW: read live price for Barangay Clearance (type_code = 'bc') === */
try {
    $db  = getDBConnection();
    $stmt = $db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
    $stmt->execute(['bc']);
    $row  = $stmt->fetch();
    // fallback if not configured yet in admin Pricing
    $BC_PRICE = isset($row['price']) ? (float)$row['price'] : 80.00;
} catch (Throwable $e) {
    error_log("BC price fetch error: " . $e->getMessage());
    $BC_PRICE = 80.00; // safe fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Clearance | Barangay Bula</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./style/bc.css">
</head>
<body>
    <nav class="navbar">
        <a href="home.php" class="navbar-brand">
            <img src="./pics/logo.png" alt="Barangay Bula Logo">
            Barangay Bula
        </a>
    </nav>

    <main class="main-content">
        <div class="container">
            <section class="form-container">
                <div class="progress-steps">
                    <div class="step active" id="step1">
                        <div class="step-number">1</div>
                        <div class="step-label">Personal Information</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-number">2</div>
                        <div class="step-label">Purpose & Documents</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-label">Review & Submit</div>
                    </div>
                </div>
                
                <div class="form-header">
                    <h2><i class="fas fa-file-alt"></i> Barangay Clearance</h2>
                    <p>Fill out the form to request a Barangay Clearance</p>
                </div>
                   
                <form id="clearanceForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    
                    <!-- Section 1: Personal Information -->
                    <div class="form-section active" id="section1" aria-labelledby="step1">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <!-- FIXED: removed extra '<' -->
                            <input type="text" id="fullName" class="form-control auto-filled" readonly
                                   value="<?= e($userInfo['fullName'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" id="contactNumber" class="form-control auto-filled" readonly
                                   value="<?= e($userInfo['contactNumber'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Complete Address</label>
                            <input type="text" id="address" class="form-control auto-filled" readonly
                                   value="<?= e($userInfo['address'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="yearOfStay">Year Started Living in Barangay</label>
                            <input type="text" id="yearOfStay" class="form-control auto-filled" readonly
                                   value="<?= e($userInfo['yearOfStay'] ?? '') ?>">
                        </div>
                        
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-danger" id="cancelBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn" id="nextBtn1">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Section 2: Purpose & Documents -->
                    <div class="form-section" id="section2" aria-labelledby="step2">
                        <div class="purpose-options">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #2c3e50;">
                                Purpose of Clearance*
                            </label>
                            
                            <div class="purpose-option" onclick="selectPurpose('Employment')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="employmentPurpose" name="purpose" value="Employment" required>
                                <label for="employmentPurpose">Employment</label>
                            </div>
                            
                            <div class="purpose-option" onclick="selectPurpose('Business')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="businessPurpose" name="purpose" value="Business">
                                <label for="businessPurpose">Business Permit/Registration</label>
                            </div>
                            
                            <div class="purpose-option" onclick="selectPurpose('School')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="schoolPurpose" name="purpose" value="School">
                                <label for="schoolPurpose">School Requirement</label>
                            </div>
                            
                            <div class="purpose-option" onclick="selectPurpose('Government')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="governmentPurpose" name="purpose" value="Government">
                                <label for="governmentPurpose">Government Transaction</label>
                            </div>
                            
                            <div class="purpose-option" onclick="selectPurpose('Other')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="otherPurpose" name="purpose" value="Other">
                                <label for="otherPurpose">Other Purpose</label>
                            </div>
                            
                            <div class="other-purpose-container" id="otherPurposeContainer">
                                <label for="specifyPurpose">Please specify purpose*</label>
                                <input type="text" id="specifyPurpose" name="purpose_details" class="form-control">
                                <div class="error-message" id="specifyPurposeError">Please specify your purpose</div>
                            </div>
                            <div class="error-message" id="purposeError">Please select a purpose</div>
                        </div>
                        
                        <!-- Fee Calculator (DYNAMIC PRICE) -->
                        <div class="fee-calculator">
                            <h4><i class="fas fa-calculator"></i> Fee Calculator</h4>
                            <div class="form-group">
                                <label for="copyQuantity">Number of Copies Needed</label>
                                <div class="copy-quantity">
                                    <input type="number" id="copyQuantity" name="copies" class="form-control" min="1" max="5" value="1">
                                    <!-- price text will be filled by JS from window.BC_PRICE -->
                                    <span id="perCopyText">× ₱<?= number_format($BC_PRICE, 2) ?> per copy</span>
                                </div>
                            </div>
                            <div id="feeResult" class="fee-result">
                                Total Fee: ₱<span id="calculatedFee"><?= number_format($BC_PRICE, 2) ?></span>
                            </div>
                        </div>
                        
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" id="prevBtn1">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn" id="nextBtn2">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Section 3: Review & Submit -->
                    <div class="form-section" id="section3" aria-labelledby="step3">
                        <!-- Purok Clearance Options -->
                        <div class="document-options">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #2c3e50;">
                                Purok Clearance Submission Method*
                            </label>
                            
                            <div class="document-option" onclick="selectDocumentOption('upload')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="uploadOption" name="document_method" value="upload" required>
                                <label for="uploadOption">Upload Purok Clearance Online</label>
                                <div class="file-upload-container" id="uploadContainer">
                                    <input type="file" id="purokClearance" name="purok_clearance" class="file-upload-input" accept="image/*,.pdf" aria-describedby="fileUploadHelp">
                                    <label for="purokClearance" class="file-upload-button">
                                        <i class="fas fa-upload"></i> Choose File (JPG, PNG, PDF, max 5MB)
                                    </label>
                                    <div class="file-upload-name" id="fileName">No file chosen</div>
                                    <div class="error-message" id="fileUploadError">Please upload your purok clearance</div>
                                    <p class="note" id="fileUploadHelp">Maximum file size: 5MB. Accepted formats: JPG, PNG, PDF</p>
                                </div>
                            </div>
                            
                            <div class="document-option" onclick="selectDocumentOption('hall')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="hallOption" name="document_method" value="hall">
                                <label for="hallOption">Bring Purok Clearance to Barangay Hall</label>
                                <div class="bring-to-hall-info" id="hallInfo">
                                    <p><i class="fas fa-info-circle"></i> Please bring your purok clearance to:</p>
                                    <p><strong>Barangay Bula Hall</strong></p>
                                    <p>Open Monday-Friday, 8:00 AM - 5:00 PM</p>
                                    <p>Saturday, 8:00 AM - 12:00 PM</p>
                                </div>
                            </div>
                            <div class="error-message" id="documentMethodError">Please select a submission method</div>
                        </div>

                        <div class="processing-info">
                            <h4><i class="fas fa-clock"></i> Processing Information</h4>
                            <p><strong>Processing Time:</strong> 1-3 business days</p>
                            <p><strong>note after 7 days upon approval you will need to request again.</strong></p>
                            <p>You will receive an e-mail notification once your clearance is ready for pickup.</p>
                            <p class="note">Note: Processing may take longer during peak periods.</p>
                        </div>

                        <div class="walkin-hours">
                            <h4><i class="fas fa-door-open"></i> Document Pickup Hours</h4>
                            <p><strong>Monday to Friday:</strong> 8:00 AM - 5:00 PM</p>
                            <p><strong>Saturday:</strong> 8:00 AM - 12:00 PM</p>
                            <p><strong>Location:</strong> Barangay Bula Hall, Purok 5</p>
                            <p class="note">Please bring your valid ID when picking up your documents.</p>
                        </div>
                        
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" id="prevBtn2">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="submit" id="submitApplication" class="btn">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="requirements">
                    <h3><i class="fas fa-clipboard-list"></i> Required Documents</h3>
                    <ul class="requirements-list">
                        <li><i class="fas fa-check-circle"></i><span>Purok Clearance</span></li>
                        <li><i class="fas fa-check-circle"></i><span>Valid ID (Government-issued)</span></li>
                        <li><i class="fas fa-check-circle"></i><span>Community Tax Certificate (Cedula)</span></li>
                    </ul>
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
            <p>Your Barangay Clearance application has been received.</p>
            <div class="reference-number" id="referenceNumber"></div>
            <div class="amount-due" id="amountDue"></div>
            <p>We will notify you via e-mail when your clearance is ready for pickup.</p>
            <button id="closeModalBtn" class="btn">
                <i class="fas fa-thumbs-up"></i> Return to Home Page
            </button>
        </div>
    </div>

            <!-- Confirm Modal -->
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


    <!-- Pass the dynamic price to the JS -->
    <script>
      window.BC_PRICE = <?= json_encode((float)$BC_PRICE) ?>;
    </script>

    <script src="./script/bc.js"></script>
</body>
</html>
