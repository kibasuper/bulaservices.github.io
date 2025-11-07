<?php
declare(strict_types=1);
require_once __DIR__ . '/server/config.php';

// Only allow logged-in admins
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$certificateType = $_GET['type'] ?? '';
$certificateName = $_GET['name'] ?? '';

if (!$certificateType || !$certificateName) {
    header('Location: request.php');
    exit;
}

// Get price for this certificate type
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
    $stmt->execute([$certificateType]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $certificatePrice = $row['price'] ?? 0.00;
} catch (Exception $e) {
    error_log("Price fetch error: " . $e->getMessage());
    $certificatePrice = 0.00;
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Transaction - <?= htmlspecialchars($certificateName) ?> | Barangay Bula</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin_certificate.css">
</head>
<body>
    <nav class="navbar">
        <a href="request.php" class="navbar-brand">
            <i class="fas fa-arrow-left"></i> Back to Requests
        </a>
        <div class="navbar-title">
            New Transaction: <?= htmlspecialchars($certificateName) ?>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <section class="form-container">
                <div class="progress-steps">
                    <div class="step active" id="step1">
                        <div class="step-number">1</div>
                        <div class="step-label">Resident Information</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-number">2</div>
                        <div class="step-label">Purpose & Details</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-label">Review & Submit</div>
                    </div>
                </div>

                <form id="adminCertificateForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="certificate_type" value="<?= htmlspecialchars($certificateType) ?>">
                    <input type="hidden" name="certificate_name" value="<?= htmlspecialchars($certificateName) ?>">
                    <input type="hidden" name="admin_created" value="1">

                    <!-- Section 1: Resident Information -->
                    <div class="form-section active" id="section1">
                        <div class="resident-search-section">
                            <h3><i class="fas fa-search"></i> Find Existing Resident</h3>
                            <div class="search-box">
                                <input type="text" id="residentSearch" placeholder="Search by name, email, or contact number..." class="form-control">
                                <button type="button" id="searchBtn" class="btn btn-secondary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                            <div id="searchResults" class="search-results"></div>
                            
                            <div class="divider">
                                <span>OR</span>
                            </div>
                            
                            <h3><i class="fas fa-user-plus"></i> Add New Resident</h3>
                        </div>

                        <div class="form-group">
                            <label for="fullName">Full Name *</label>
                            <input type="text" id="fullName" name="full_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="contactNumber">Contact Number *</label>
                            <input type="text" id="contactNumber" name="contact_number" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Complete Address *</label>
                            <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
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

                    <!-- Section 2: Purpose & Details -->
                    <div class="form-section" id="section2">
                        <div class="form-group">
                            <label for="purpose">Purpose of Request *</label>
                            <select id="purpose" name="purpose" class="form-control" required>
                                <option value="">Select Purpose</option>
                                <option value="Employment">Employment</option>
                                <option value="Business">Business Permit/Registration</option>
                                <option value="School">School Requirement</option>
                                <option value="Government">Government Transaction</option>
                                <option value="Other">Other Purpose</option>
                            </select>
                        </div>

                        <div class="form-group" id="otherPurposeContainer" style="display: none;">
                            <label for="purposeDetails">Please specify purpose *</label>
                            <input type="text" id="purposeDetails" name="purpose_details" class="form-control">
                        </div>

                        <div class="fee-calculator">
                            <h4><i class="fas fa-calculator"></i> Fee Calculator</h4>
                            <div class="form-group">
                                <label for="copyQuantity">Number of Copies Needed</label>
                                <div class="copy-quantity">
                                    <input type="number" id="copyQuantity" name="copies" class="form-control" min="1" max="10" value="1">
                                    <span id="perCopyText">× ₱<?= number_format($certificatePrice, 2) ?> per copy</span>
                                </div>
                            </div>
                            <div id="feeResult" class="fee-result">
                                Total Fee: ₱<span id="calculatedFee"><?= number_format($certificatePrice, 2) ?></span>
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
                    <div class="form-section" id="section3">
                        <div class="review-section">
                            <h3><i class="fas fa-clipboard-check"></i> Review Information</h3>
                            <div class="review-details">
                                <div class="detail-row">
                                    <span class="detail-label">Certificate Type:</span>
                                    <span class="detail-value"><?= htmlspecialchars($certificateName) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Resident Name:</span>
                                    <span class="detail-value" id="reviewFullName"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value" id="reviewEmail"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contact Number:</span>
                                    <span class="detail-value" id="reviewContact"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Address:</span>
                                    <span class="detail-value" id="reviewAddress"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Purpose:</span>
                                    <span class="detail-value" id="reviewPurpose"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Number of Copies:</span>
                                    <span class="detail-value" id="reviewCopies"></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Total Fee:</span>
                                    <span class="detail-value" id="reviewFee"></span>
                                </div>
                            </div>
                        </div>

                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" id="prevBtn2">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="submit" class="btn" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Create Request
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <!-- Success Modal -->
    <div id="successModal" class="success-modal">
        <div class="success-modal-content">
            <i class="fas fa-check-circle"></i>
            <h3>Request Created Successfully!</h3>
            <p>New certificate request has been created.</p>
            <div class="reference-number" id="referenceNumber"></div>
            <button id="closeModalBtn" class="btn">
                <i class="fas fa-thumbs-up"></i> Return to Requests
            </button>
        </div>
    </div>

    <script>
        window.CERTIFICATE_PRICE = <?= json_encode((float)$certificatePrice) ?>;
    </script>
    <script src="../script/admin_certificate.js"></script>
</body>
</html>