<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/file_urls.php';

ensureUserAccess();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Current user (safe defaults)
$currentUser = [
    'id'    => $_SESSION['user_id']   ?? null,
    'name'  => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email']?? '',
    'profilePic' => null,
];

// Pull profile_picture from DB and normalize it
try {
    if (!empty($currentUser['id'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbPath = $row['profile_picture'] ?: null;
            $currentUser['profilePic'] = $dbPath ? user_upload_url($dbPath) : null;
        }
    }
} catch (Throwable $e) {
    error_log("terms.php profile fetch: " . $e->getMessage());
}

// Final URL used by <img>
$picUrl = $currentUser['profilePic'] ?: './pics/profile-placeholder.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="uploads-origin" content="https://admin.bulaservicesgsc.com">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service & Privacy Policy - Barangay Bula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="./style/home.css">
    <style>
        /* Additional styles specific to terms page */
        .terms-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .terms-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
        }
        
        .terms-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 800;
        }
        
        .terms-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .terms-content {
            background: white;
            border-radius: 1rem;
            padding: 3rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        
        .terms-section {
            margin-bottom: 2.5rem;
        }
        
        .terms-section:last-child {
            margin-bottom: 0;
        }
        
        .terms-section h2 {
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
            font-size: 1.5rem;
        }
        
        .terms-section h3 {
            color: var(--dark);
            margin: 1.5rem 0 0.75rem;
            font-size: 1.2rem;
        }
        
        .terms-section p, .terms-section ul {
            margin-bottom: 1rem;
            line-height: 1.7;
            color: var(--muted);
        }
        
        .terms-section ul {
            padding-left: 1.5rem;
        }
        
        .terms-section li {
            margin-bottom: 0.5rem;
        }
        
        .highlight-box {
            background: #f8fafc;
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0 0.5rem 0.5rem 0;
        }
        
        .last-updated {
            text-align: center;
            color: var(--gray);
            font-style: italic;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        @media (max-width: 768px) {
            .terms-content {
                padding: 2rem 1.5rem;
            }
            
            .terms-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Modern Navbar -->
    <nav class="navbar">
        <div class="logo">
            <img src="./pics/logo.png" alt="Barangay Logo">
            <span class="logo-text">Barangay Bula</span>
        </div>
        <ul class="nav-links">
            <li><a href="track.php">My Request</a></li>
            <li><a href="home.php">Home</a></li>
            <li><a href="home.php#services">Services</a></li>
            <li><a href="home.php#about">About</a></li>
            <li class="profile-dropdown">
                <button class="profile-btn">
                    <img src="<?= htmlspecialchars($picUrl, ENT_QUOTES) ?>" alt="Profile" class="profile-pic">
                    <span class="user-name"><?= htmlspecialchars((string)$currentUser['name'], ENT_QUOTES) ?></span>
                </button>
                <div class="dropdown-content" id="dropdownMenu">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="track.php" class="dropdown-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>My Requests</span>
                    </a>
                    <a href="terms.php" class="dropdown-item">
                        <i class="fas fa-info-circle"></i>
                        <span>Terms & Privacy Policy</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item" id="logoutLink" onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Log Out</span>
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <div class="terms-container">
        <div class="terms-header">
            <h1>Terms of Service & Privacy Policy</h1>
            <p>Please read these terms carefully before using the Barangay Bula Online Services Portal</p>
        </div>
        
        <div class="terms-content">
            <div class="terms-section">
                <h2>1. Introduction & Scope</h2>
                <p>These Terms of Service ("Terms") and Privacy Policy ("Policy") govern your use of the Barangay Bula Online Services Portal (the "System"), which includes online requests for barangay certificates and documents, facility reservations (e.g., gymnasium), and related services. By creating an account or using the System, you acknowledge that you have read, understood, and agree to these Terms and this Policy.</p>
            </div>
            
            <div class="terms-section">
                <h2>2. Eligibility & Account Registration</h2>
                <p>You must provide accurate and complete information when registering and when submitting requests.</p>
                <p>You are responsible for maintaining the confidentiality of your login credentials and for any activity that occurs under your account.</p>
                <p>We may suspend or terminate accounts that provide false information, violate these Terms, or abuse the System.</p>
            </div>
            
            <div class="terms-section">
                <h2>3. Email Verification & Notices</h2>
                <p>You must verify your email address to activate your account and receive status updates, reminders, and official notices.</p>
                <p>Transactional messages (e.g., verification links, reservation notices, status updates) will be sent to your registered email.</p>
            </div>
            
            <div class="terms-section">
                <h2>4. Acceptable Use</h2>
                <p>You agree not to:</p>
                <ul>
                    <li>Impersonate another person or misrepresent your affiliation with any entity.</li>
                    <li>Interfere with or disrupt the System, servers, or networks.</li>
                    <li>Upload or transmit harmful code (e.g., malware).</li>
                    <li>Attempt to gain unauthorized access to the System or other user accounts.</li>
                </ul>
            </div>
            
            <div class="terms-section">
                <h2>5. Services & Processing Times</h2>
                <p>Processing times and availability are subject to barangay schedules, public holidays, and operational constraints.</p>
                <p>Submitting a request does not guarantee approval; approvals are subject to barangay policies, documentary requirements, and verification.</p>
            </div>
            
            <div class="terms-section">
                <h2>6. No Refund Policy (Certificates and Gymnasium)</h2>
                <div class="highlight-box">
                    <p><strong>All payments are final and non-refundable.</strong></p>
                    <p>This includes fees for certificate requests, facility reservations (including gymnasium bookings), and any related service charges.</p>
                    <p>Rescheduling or cancellations, if available, are subject to barangay policies and scheduling constraints; fees will not be refunded under any circumstances.</p>
                </div>
            </div>
            
            <div class="terms-section">
                <h2>7. Fees & Payments</h2>
                <p>Official fees are set by the barangay and may change without prior notice. Any change will apply prospectively.</p>
                <p>You are responsible for any charges and taxes associated with your transactions.</p>
            </div>
            
            <div class="terms-section">
                <h2>8. Data We Collect (Privacy)</h2>
                <p>We collect and process the following data to deliver services:</p>
                <ul>
                    <li><strong>Identity & Contact:</strong> name, address, contact number, email, age/sex (if applicable), and related demographic information.</li>
                    <li><strong>Service Data:</strong> request types, supporting details, timestamps, reservation schedules, and status updates.</li>
                    <li><strong>Account & Security:</strong> username, hashed password, verification tokens, login timestamps, and IP/user-agent metadata.</li>
                    <li><strong>Uploads:</strong> profile photo and any attachments required to process your request (if any).</li>
                </ul>
            </div>
            
            <div class="terms-section">
                <h2>9. Purpose & Legal Basis</h2>
                <p>We process personal data to:</p>
                <ul>
                    <li>Provide and manage online barangay services;</li>
                    <li>Authenticate users, prevent fraud/abuse, and ensure system security;</li>
                    <li>Communicate request updates and official notices;</li>
                    <li>Comply with legal obligations and maintain barangay records.</li>
                </ul>
                <p>Processing is based on your consent, performance of a public task, and/or compliance with law.</p>
            </div>
            
            <div class="terms-section">
                <h2>10. Data Sharing & Disclosure</h2>
                <p>We may share data with:</p>
                <ul>
                    <li>Authorized barangay personnel for verification and processing;</li>
                    <li>Law enforcement or other government agencies when required by law;</li>
                    <li>Service providers acting on our behalf (e.g., email delivery), bound by confidentiality and data protection obligations.</li>
                </ul>
                <p>We do not sell your personal data.</p>
            </div>
            
            <div class="terms-section">
                <h2>11. Data Security</h2>
                <p>We implement appropriate technical and organizational measures (e.g., encryption in transit, hashed passwords, access controls, logging) to protect your data against unauthorized access, alteration, disclosure, or destruction. No method is 100% secure; you also play a role by safeguarding your credentials.</p>
            </div>
            
            <div class="terms-section">
                <h2>12. Data Retention</h2>
                <p>We retain data for as long as needed to deliver services, comply with legal obligations, resolve disputes, and enforce policies. Retention periods may be affected by applicable laws and barangay record-keeping requirements.</p>
            </div>
            
            <div class="terms-section">
                <h2>13. Your Rights (Philippine Data Privacy Act of 2012)</h2>
                <p>Subject to law, you may:</p>
                <ul>
                    <li>Access and request a copy of your personal data;</li>
                    <li>Request correction of inaccurate or incomplete data;</li>
                    <li>Withdraw consent to processing (where applicable);</li>
                    <li>Lodge a complaint with the National Privacy Commission (NPC).</li>
                </ul>
                <p>Requests may be sent to the Contact Information below.</p>
            </div>
            
            <div class="terms-section">
                <h2>14. Cookies & Logs</h2>
                <p>The System may use cookies or similar technologies to maintain sessions, enhance functionality, and collect usage analytics for security and service improvement.</p>
            </div>
            
            <div class="terms-section">
                <h2>15. Limitation of Liability</h2>
                <p>The System is provided "as is." To the maximum extent permitted by law, Barangay Bula is not liable for any indirect, incidental, or consequential damages arising from your use of or inability to use the System, service schedules, processing delays, or third-party actions.</p>
            </div>
            
            <div class="terms-section">
                <h2>16. Changes to These Terms & Policy</h2>
                <p>We may update these Terms and this Policy to reflect changes in services, laws, or operational needs. Material changes will be posted on the System with a new effective date. Continued use after changes constitutes acceptance.</p>
            </div>
            
            <div class="terms-section">
                <h2>17. Contact Information</h2>
                <p>For privacy or service inquiries, requests, or concerns, you may contact:</p>
                <div class="highlight-box">
                    <p><strong>Barangay Bula – Online Services</strong></p>
                    <p>Address: 453V+CFW, Edilberto Lopez Sr. St, General Santos City (Dadiangas), 9500 South Cotabato</p>
                    <p>Phone: <a href="tel:+639123456789">(083) 552-9692</a> / <a href="tel:+639987654321">+63 912 345 6789</a></p>
                    <p>Email: <a href="mailto:support@bulaservicesgsc.com">support@bulaservicesgsc.com</a></p>
                </div>
            </div>
            
            <div class="last-updated">
                <p>Last updated: January 2025</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="social-links">
                <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
            </div>
            <p class="copyright">© 2025 Barangay Bula. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Profile dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const profileBtn = document.querySelector('.profile-btn');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            if (profileBtn && dropdownMenu) {
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    dropdownMenu.classList.remove('show');
                });
            }
            
            // Logout confirmation dialog
            window.confirmLogout = function(event) {
                event.preventDefault();
                
                // Create dialog
                const dialog = document.createElement('div');
                dialog.className = 'logout-dialog';
                dialog.innerHTML = `
                    <div class="logout-dialog-content">
                        <div class="logout-dialog-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <h3>Confirm Logout</h3>
                        <p>Are you sure you want to log out of your account?</p>
                        <div class="logout-dialog-buttons">
                            <button class="logout-dialog-btn logout-dialog-btn-cancel">Cancel</button>
                            <button class="logout-dialog-btn logout-dialog-btn-confirm">Log Out</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(dialog);
                
                // Button handlers
                dialog.querySelector('.logout-dialog-btn-cancel').addEventListener('click', function() {
                    document.body.removeChild(dialog);
                });
                
                dialog.querySelector('.logout-dialog-btn-confirm').addEventListener('click', function() {
                    window.location.href = 'index.php';
                });
                
                // Close on backdrop click
                dialog.addEventListener('click', function(e) {
                    if (e.target === dialog) {
                        document.body.removeChild(dialog);
                    }
                });
            };
        });
    </script>
</body>
</html>