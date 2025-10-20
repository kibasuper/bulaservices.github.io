<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Tax Certificate (Cedula) | Barangay Bula</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./style/cedula.css">
</head>
<body>
    <nav class="navbar">
        <a href="home.php" class="navbar-brand">
            <img src="./pics/logs.png" alt="Barangay Bula Logo">
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
                        <div class="step-label">Tax Details</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-label">Review & Submit</div>
                    </div>
                </div>
                
                <div class="form-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Community Tax Certificate (Cedula)</h2>
                    <p>Fill out the form to request your Community Tax Certificate from Barangay Bula</p>
                </div>
                   
                <form id="cedulaCertificateForm">
                    <!-- Section 1: Personal Information -->
                    <div class="form-section active" id="section1" aria-labelledby="step1">
                        <div class="form-group">
                            <label for="fullName">Full Name*</label>
                            <input type="text" id="fullName" class="form-control" required value="Juan Dela Cruz" readonly>
                            <div class="error-message" id="fullNameError">Please enter your full name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="contactNumber">Contact Number*</label>
                            <input type="tel" id="contactNumber" class="form-control" required value="09123456789" readonly>
                            <div class="error-message" id="contactNumberError">Please enter your contact number</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Complete Address*</label>
                            <input type="text" id="address" class="form-control" required value="Purok 5, Barangay Bula, General Santos City" readonly>
                            <div class="error-message" id="addressError">Please enter your address</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="yearStarted">Year Started Living in Barangay*</label>
                            <input type="number" id="yearStarted" class="form-control" required value="2015" readonly>
                            <div class="error-message" id="yearStartedError">Please enter the year you started living in the barangay</div>
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
                    
                    <!-- Section 2: Tax Details -->
                    <div class="form-section" id="section2" aria-labelledby="step2">
                        <div class="form-group">
                            <label for="purpose">Purpose of Cedula*</label>
                            <select id="purpose" class="form-control" required>
                                <option value="" disabled selected>Select purpose</option>
                                <option value="Government Transaction">Government Transaction</option>
                                <option value="Employment Requirement">Employment Requirement</option>
                                <option value="Business Permit">Business Permit</option>
                                <option value="Bank Transaction">Bank Transaction</option>
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
                            <h4><i class="fas fa-calculator"></i> Cedula Quantity</h4>
                            <div class="form-group">
                                <label for="cedulaQuantity">Number of Cedulas Needed*</label>
                                <input type="number" id="cedulaQuantity" class="form-control" min="1" value="1" required>
                                <div class="error-message" id="quantityError">Please enter a valid quantity (minimum 1)</div>
                            </div>
                            
                            <div id="taxResult" class="fee-result">
                                Base Fee per Cedula: ₱5.00
                                <br>Total for <span id="displayQuantity">1</span> cedula(s): ₱<span id="totalTax">5.00</span>
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
                        <!-- Purok Clearance Submission Options -->
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
                            <p><strong>Processing Time:</strong> Immediate issuance upon payment</p>
                            <p>You will need to pay the calculated amount to receive your cedula(s).</p>
                            <p class="note">Note: Bring exact amount for faster transaction.</p>
                        </div>

                        <!-- Walk-in Hours Information -->
                        <div class="walkin-hours">
                            <h4><i class="fas fa-door-open"></i> Office Hours</h4>
                            <p><strong>Monday to Friday:</strong> 8:00 AM - 5:00 PM</p>
                            <p><strong>Saturday:</strong> 8:00 AM - 12:00 PM</p>
                            <p><strong>Location:</strong> Barangay Bula Hall, Purok 5</p>
                            <p class="note">Please bring your purok clearance when claiming your cedula.</p>
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
                    <h3><i class="fas fa-clipboard-list"></i> Requirements</h3>
                    <ul class="requirements-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Purok Clearance</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Payment for the requested cedula(s)</span>
                        </li>
                    </ul>
                    <p class="note">Note: This certificate is valid for the current calendar year.</p>
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
            <p>Your Community Tax Certificate (Cedula) application has been received.</p>
            <div class="reference-number" id="referenceNumber"></div>
            <p>Please proceed to the barangay hall with your payment of ₱<span id="displayTaxAmount">5.00</span> to claim your <span id="displayQuantityModal">1</span> cedula(s).</p>
            <button id="closeModalBtn" class="btn">
                <i class="fas fa-thumbs-up"></i> Return to Home Page
            </button>
        </div>
    </div>

    <script src="./script/cedula.js"></script>
</body>
</html>