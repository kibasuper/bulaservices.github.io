<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Custom error handler to capture errors before redirect
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Store error in session for display on index page
    $_SESSION['last_error'] = "Error #$errno: $errstr in $errfile on line $errline";
    
    // Also log the error
    error_log($_SESSION['last_error']);
    
    // Don't execute PHP internal error handler
    return true;
});

// Include your files with error handling
try {
    require_once __DIR__ . '/server/config.php';
    require_once __DIR__ . '/server/business_permit_functions.php';
} catch (Exception $e) {
    // Store the error and redirect
    $_SESSION['last_error'] = "Failed to include required files: " . $e->getMessage();
    header("Location: index.php?error=" . urlencode($_SESSION['last_error']));
    exit();
}

// Check if user is logged in
if (!isLoggedIn()) {
    $errorMsg = "Access denied: You must be logged in to request a business permit.";
    $_SESSION['last_error'] = $errorMsg;
    header("Location: index.php?error=" . urlencode($errorMsg));
    exit();
}

// Get user information
$businessPermit = new BusinessPermitRequest();
$userInfo = $businessPermit->getUserInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Permit | Barangay Bula</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./style/bp.css">
</head>
<body>
    <nav class="navbar">
        <a href="home.php" class="navbar-brand">
            <img src="./pics/logo.png" alt="Barangay Logo">
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
                        <div class="step-label">Business Details</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-label">Review & Submit</div>
                    </div>
                </div>
                
                <div class="form-header">
                    <h2><i class="fas fa-briefcase"></i> Business Permit</h2>
                    <p>Fill out the form to request a business permit certificate</p>
                </div>
                   
                <form id="businessPermitForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    
                    <!-- Section 1: Personal Information -->
                    <div class="form-section active" id="section1" aria-labelledby="step1">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" class="form-control auto-filled" readonly 
                                   value="<?= htmlspecialchars($userInfo['fullName'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" id="contactNumber" class="form-control auto-filled" readonly 
                                   value="<?= htmlspecialchars($userInfo['contactNumber'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Complete Address</label>
                            <input type="text" id="address" class="form-control auto-filled" readonly 
                                   value="<?= htmlspecialchars($userInfo['address'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="yearOfStay">Year Started Living in Barangay</label>
                            <input type="text" id="yearOfStay" class="form-control auto-filled" readonly 
                                   value="<?= htmlspecialchars($userInfo['yearOfStay'] ?? '') ?>">
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
                    
                    <!-- Section 2: Business Details -->
                    <div class="form-section" id="section2" aria-labelledby="step2">
                        <div class="form-group">
                            <label for="businessName">Business Name*</label>
                            <input type="text" id="businessName" name="business_name" class="form-control" required>
                            <div class="error-message" id="businessNameError">Please enter your business name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="businessType">Business Type*</label>
                            <select id="businessType" name="business_type" class="form-control" required>
                                <option value="" disabled selected>Select business type</option>
                                <option value="Retail">Retail</option>
                                <option value="Food Service">Food Service</option>
                                <option value="Services">Services</option>
                                <option value="Manufacturing">Manufacturing</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="error-message" id="businessTypeError">Please select your business type</div>
                        </div>
                        
                        <div id="otherBusinessTypeContainer" class="form-group" style="display:none;">
                            <label for="otherBusinessType">Specify Business Type*</label>
                            <input type="text" id="otherBusinessType" name="business_type_other" class="form-control">
                            <div class="error-message" id="otherBusinessTypeError">Please specify your business type</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="businessAddress">Business Address*</label>
                            <input type="text" id="businessAddress" name="business_address" class="form-control" required>
                            <div class="error-message" id="businessAddressError">Please enter your business address</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose of Request*</label>
                            <select id="purpose" name="purpose" class="form-control" required>
                                <option value="" disabled selected>Select purpose</option>
                                <option value="New Business">New Business</option>
                                <option value="Business Renewal">Business Renewal</option>
                                <option value="Business Update">Business Update</option>
                                <option value="Other">Other (please specify)</option>
                            </select>
                            <div class="error-message" id="purposeError">Please select a purpose</div>
                        </div>
                        
                        <div id="otherPurposeContainer" class="form-group" style="display:none;">
                            <label for="otherPurpose">Specify Other Purpose*</label>
                            <input type="text" id="otherPurpose" name="purpose_details" class="form-control">
                            <div class="error-message" id="otherPurposeError">Please specify your purpose</div>
                        </div>
                        
                        <!-- Fee Calculator -->
                        <div class="fee-calculator">
                            <h4><i class="fas fa-calculator"></i> Fee Calculator</h4>
                            <div class="form-group">
                                <label for="copyQuantity">Number of Copies Needed</label>
                                <div class="copy-quantity">
                                    <input type="number" id="copyQuantity" name="copies" class="form-control" min="1" max="10" value="1">
                                    <span>× ₱80.00 per copy</span>
                                </div>
                            </div>
                            <div id="feeResult" class="fee-result">
                                Total Fee: ₱<span id="calculatedFee">80.00</span>
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
                    
                    <!-- Section 3: Documents and Submission -->
                    <div class="form-section" id="section3" aria-labelledby="step3">
                        <!-- Purok Clearance Options -->
                        <div class="purok-clearance-options">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #2c3e50;">
                                Purok Clearance Submission Method*
                            </label>
                            
                            <div class="clearance-option" onclick="selectClearanceOption('upload')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="uploadOption" name="clearance_method" value="upload" required>
                                <label for="uploadOption">Upload Purok Clearance Online</label>
                                <div class="file-upload-container" id="uploadContainer">
                                    <input type="file" id="purokClearance" name="purok_clearance" class="file-upload-input" accept="image/*,.pdf" aria-describedby="fileUploadHelp">
                                    <label for="purokClearance" class="file-upload-button">
                                        <i class="fas fa-upload"></i> Choose File (JPG, PNG, PDF, max 5MB)
                                    </label>
                                    <div class="file-upload-name" id="fileName">No file chosen</div>
                                    <div class="error-message" id="fileUploadError">Please upload your Purok Clearance</div>
                                    <p class="note" id="fileUploadHelp">Maximum file size: 5MB. Accepted formats: JPG, PNG, PDF</p>
                                </div>
                            </div>
                            
                            <div class="clearance-option" onclick="selectClearanceOption('hall')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="hallOption" name="clearance_method" value="hall">
                                <label for="hallOption">Bring Purok Clearance to Barangay Hall</label>
                                <div class="bring-to-hall-info" id="hallInfo">
                                    <p><i class="fas fa-info-circle"></i> Please bring your Purok Clearance to:</p>
                                    <p><strong>Barangay Bula Hall</strong></p>
                                    <p>Open Monday-Friday, 8:00 AM - 5:00 PM</p>
                                    <p>Saturday, 8:00 AM - 12:00 PM</p>
                                </div>
                            </div>
                            <div class="error-message" id="clearanceMethodError">Please select a submission method</div>
                        </div>

                        <!-- Processing Information -->
                        <div class="processing-info">
                            <h4><i class="fas fa-clock"></i> Processing Information</h4>
                            <p><strong>Processing Time:</strong> 3-5 business days</p>
                            <p>You will receive an SMS notification once your business permit is ready for pickup.</p>
                            <p class="note">Note: Processing may take longer during peak periods.</p>
                        </div>

                        <!-- Walk-in Hours Information -->
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
                
                <!-- Required Documents Section -->
                <div class="requirements">
                    <h3><i class="fas fa-clipboard-list"></i> Required Documents</h3>
                    <ul class="requirements-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Purok Clearance (Upload or bring to Hall)</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Valid ID (Government-issued)</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Business Registration Documents (if applicable)</span>
                        </li>
                    </ul>
                    <p class="note">Note: This permit is valid for one year from issuance.</p>
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
            <p>Your business permit application has been received.</p>
            <div class="reference-number" id="referenceNumber"></div>
            <div class="amount-due" id="amountDue"></div>
            <p>We will notify you via SMS when your documents are ready for pickup.</p>
            <button id="closeModalBtn" class="btn">
                <i class="fas fa-thumbs-up"></i> Return to Home Page
            </button>
        </div>
    </div>

    <script src="./script/bp.js"></script>
</body>
</html>