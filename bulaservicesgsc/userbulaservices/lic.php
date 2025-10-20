<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Low Income Certificate | Barangay Bula</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./style/lic.css">
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
                        <div class="step-label">Income Details</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-label">Review & Submit</div>
                    </div>
                </div>
                
                <div class="form-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Low Income Certificate</h2>
                    <p>Fill out the form to request a Low Income Certificate</p>
                </div>
                   
                <form id="lowIncomeForm">
                    <!-- Section 1: Personal Information -->
                    <div class="form-section active" id="section1" aria-labelledby="step1">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" class="form-control auto-filled" readonly aria-readonly="true">
                        </div>
                        
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" id="contactNumber" class="form-control auto-filled" readonly aria-readonly="true">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Complete Address</label>
                            <input type="text" id="address" class="form-control auto-filled" readonly aria-readonly="true">
                        </div>
                        
                        <div class="form-group">
                            <label for="yearOfStay">Year Started Living in Barangay</label>
                            <input type="text" id="yearOfStay" class="form-control auto-filled" readonly aria-readonly="true">
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
                    
                    <!-- Section 2: Income Details -->
                    <div class="form-section" id="section2" aria-labelledby="step2">
                        <div class="income-details">
                            <h4><i class="fas fa-money-bill-wave"></i> Monthly Income Details</h4>
                            
                            <div class="form-group">
                                <label for="monthlyIncome">Your Monthly Income (₱)*</label>
                                <input type="number" id="monthlyIncome" class="form-control" min="0" required>
                                <div class="error-message" id="monthlyIncomeError">Please enter your monthly income</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="incomeSource">Main Source of Income*</label>
                                <select id="incomeSource" class="form-control" required>
                                    <option value="" disabled selected>Select income source</option>
                                    <option value="Employment">Employment</option>
                                    <option value="Self-Employment">Self-Employment</option>
                                    <option value="Remittance">Remittance</option>
                                    <option value="Government Assistance">Government Assistance</option>
                                    <option value="Other">Other (please specify)</option>
                                </select>
                                <div class="error-message" id="incomeSourceError">Please select your income source</div>
                            </div>
                            
                            <div id="otherIncomeSourceContainer" class="form-group" style="display:none;">
                                <label for="otherIncomeSource">Specify Other Income Source*</label>
                                <input type="text" id="otherIncomeSource" class="form-control">
                                <div class="error-message" id="otherIncomeSourceError">Please specify your income source</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose of Request*</label>
                            <select id="purpose" class="form-control" required>
                                <option value="" disabled selected>Select purpose</option>
                                <option value="Scholarship Application">Scholarship Application</option>
                                <option value="Government Assistance">Government Assistance</option>
                                <option value="Loan Application">Loan Application</option>
                                <option value="Other">Other (please specify)</option>
                            </select>
                            <div class="error-message" id="purposeError">Please select a purpose</div>
                        </div>
                        
                        <div id="otherPurposeContainer" class="form-group" style="display:none;">
                            <label for="otherPurpose">Specify Other Purpose*</label>
                            <input type="text" id="otherPurpose" class="form-control">
                            <div class="error-message" id="otherPurposeError">Please specify your purpose</div>
                        </div>
                        
                        <!-- Fee Calculator -->
                        <div class="fee-calculator">
                            <h4><i class="fas fa-calculator"></i> Fee Calculator</h4>
                            <div class="form-group">
                                <label for="copyQuantity">Number of Copies Needed</label>
                                <div class="copy-quantity">
                                    <input type="number" id="copyQuantity" class="form-control" min="1" max="5" value="1">
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
                        <div class="document-options">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #2c3e50;">
                                Purok Clearance Submission Method*
                            </label>
                            
                            <div class="document-option" onclick="selectDocumentOption('upload')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="uploadOption" name="documentMethod" value="upload" required>
                                <label for="uploadOption">Upload Purok Clearance Online</label>
                                <div class="file-upload-container" id="uploadContainer">
                                    <input type="file" id="purokClearance" class="file-upload-input" accept="image/*,.pdf" aria-describedby="fileUploadHelp">
                                    <label for="purokClearance" class="file-upload-button">
                                        <i class="fas fa-upload"></i> Choose File (JPG, PNG, PDF, max 5MB)
                                    </label>
                                    <div class="file-upload-name" id="fileName">No file chosen</div>
                                    <div class="error-message" id="fileUploadError">Please upload your purok clearance</div>
                                    <p class="note" id="fileUploadHelp">Maximum file size: 5MB. Accepted formats: JPG, PNG, PDF</p>
                                </div>
                            </div>
                            
                            <div class="document-option" onclick="selectDocumentOption('hall')" tabindex="0" role="button" aria-pressed="false">
                                <input type="radio" id="hallOption" name="documentMethod" value="hall">
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

                        <!-- Processing Information -->
                        <div class="processing-info">
                            <h4><i class="fas fa-clock"></i> Processing Information</h4>
                            <p><strong>Processing Time:</strong> 3-5 business days</p>
                            <p>You will receive an SMS notification once your certificate is ready for pickup.</p>
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
                            <span>Purok Clearance</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Valid ID (Government-issued)</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Proof of Income (Payslip, Certificate of Employment, etc.)</span>
                        </li>
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
            <p>We will notify you via SMS when your certificate is ready for pickup.</p>
            <button id="closeModalBtn" class="btn">
                <i class="fas fa-thumbs-up"></i> Return to Home Page
            </button>
        </div>
    </div>

    <script src="./script/lic.js"></script>
</body>
</html>