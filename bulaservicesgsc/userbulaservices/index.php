<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$BASE = __DIR__;

// 1) Always load config first (sessions, headers, DB, etc.)
$cfg = $BASE . '/server/config.php';
if (!is_file($cfg)) {
    http_response_code(500);
    echo 'Fatal: missing config.php at ' . htmlspecialchars($cfg);
    exit;
}
require_once $cfg;

// 2) Pull in auth_functions.php (try both locations)
$authPaths = [
    $BASE . '/server/auth_functions.php', 
    dirname(__DIR__) . '/server/auth_functions.php',
];
$authLoaded = false;
foreach ($authPaths as $p) {
    if (is_file($p) && is_readable($p)) {
        require_once $p;
        $authLoaded = true;
        break;
    }
}

// 3) Last-resort: provide a CSRF shim so the page never 500s
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// 4) Create the page token
$CSRF = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Bula – Login & Register</title>
  <link rel="preload" href="./pics/bula.png" as="image" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="./style/index.css" />
</head>

<body>
<script>
function showError(message, details='') {
  console.error('System Notification:', message);
  if (details) console.error('Details:', details);
}
</script>

<?php if (isset($_SESSION['registration_success']) && $_SESSION['registration_success']): ?>
  <div class="success-dialog" data-email="<?= htmlspecialchars($_SESSION['registered_email'] ?? '') ?>">
    <div class="dialog-content">
      <i class="fas fa-check-circle success-icon"></i>
      <h3>Registration Successful!</h3>
      <p>Your account has been created successfully.</p>
      <p>You can now log in using your credentials.</p>
      <button class="btn" id="closeSuccessDialog">OK</button>
    </div>
  </div>
  <?php unset($_SESSION['registration_success'], $_SESSION['registered_email']); ?>
<?php endif; ?>

<div class="auth-container" id="authContainer">
  <div class="auth-header">
    <div class="auth-logo">
      <img src="./pics/logo.png" alt="Barangay Bula Logo" />
    </div>
    <h1 class="auth-title">Barangay Bula<br>Modern Services Portal</h1>
  </div>

  <div class="tabs">
    <div class="tab active" data-tab="login">Login</div>
    <div class="tab" data-tab="register">Register</div>
  </div>

  <div class="auth-body">

    <!-- LOGIN TAB -->
    <div class="tab-content active" id="login">
      <form id="loginForm" method="POST">
        <div class="form-group">
          <label for="loginEmail">Username or Email</label>
          <input type="text" id="loginEmail" name="identifier" class="form-control"
                 placeholder="Enter username or email" required />
        </div>

        <div class="form-group">
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="password"
                 class="form-control" placeholder="Enter password" required />
        </div>

        <button type="submit" class="btn">Login</button>
        <p><a href="#" id="forgotLink">Forgot password?</a></p>
      </form>
    </div>
    

    <!-- REGISTER TAB -->
    <div class="tab-content" id="register">
      <form id="registerForm" method="POST" enctype="multipart/form-data">

        <!-- Resident Status -->
        <div class="form-section resident-status-section">
          <div class="form-group resident-status-container">
            <h3 class="resident-status-title">Are you a resident of Barangay Bula?</h3>
            <div class="radio-group resident-status-options">
              <label class="resident-option">
                <input type="radio" name="resident_status" value="resident" checked />
                <span class="radio-custom"></span> Yes, I'm a resident
              </label>
              <label class="resident-option">
                <input type="radio" name="resident_status" value="outsider" />
                <span class="radio-custom"></span> No, I'm an outsider
              </label>
            </div>
            <div class="form-note resident-status-note">
              Note: Outsiders can only access facility services (e.g., gymnasium reservation)
            </div>
          </div>
        </div>

        <!-- Personal Info -->
        <div class="form-section">
          <div class="form-row">
            <div class="form-group">
              <label for="firstName">First Name</label>
              <input type="text" id="firstName" name="first_name" placeholder="Enter first name" required />
            </div>
            <div class="form-group">
              <label for="middleName">Middle Name</label>
              <input type="text" id="middleName" name="middle_name" placeholder="Enter middle name" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="lastName">Last Name</label>
              <input type="text" id="lastName" name="last_name" placeholder="Enter last name" required />
            </div>
            <div class="form-group">
              <label for="suffix">Suffix</label>
              <select id="suffix" name="suffix">
                <option value="">None</option>
                <option value="Jr">Jr</option>
                <option value="Sr">Sr</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
              </select>
            </div>
          </div>

          <div class="form-row resident-only-field">
            <div class="form-group">
              <label for="birthPlace">Place of Birth</label>
              <input type="text" id="birthPlace" name="birth_place" placeholder="Enter birthplace" />
            </div>
            <div class="form-group">
              <label for="birthDate">Birthdate</label>
              <input type="text" id="birthDate" name="birth_date"
                     placeholder="mm/dd/yyyy" onfocus="this.type='date'"
                     onblur="if(!this.value) this.type='text'" />
            </div>
          </div>

          <div class="form-row resident-only-field">
            <div class="form-group">
              <label for="age">Age</label>
              <input type="number" id="age" name="age" placeholder="Enter age" />
            </div>
            <div class="form-group">
              <label for="civilStatus">Civil Status</label>
              <select id="civilStatus" name="civil_status">
                <option value="">Select</option>
                <option value="single">Single</option>
                <option value="married">Married</option>
                <option value="widowed">Widowed</option>
                <option value="separated">Separated</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Contact Info -->
        <div class="form-section">
          <div class="form-row">
            <div class="form-group">
              <label for="gender">Sex</label>
              <select id="gender" name="gender">
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>

                                <!-- Add this button near your purok selection -->
          <button type="button" class="purok-list-btn" id="showPurokList">
            <i class="fas fa-list"></i> View Purok List
          </button>

            <div class="form-group resident-only-field">
              <label for="purok">Purok</label>
              <select id="purok" name="purok">
                <option value="">Select</option>
                <?php for ($i=1; $i<=25; $i++) echo "<option value='$i'>Purok $i</option>"; ?>
              </select>
            </div>
          </div>
                    <!-- Purok List Floating Dialog -->
          <div class="purok-list-dialog" id="purokListDialog">
            <div class="purok-list-content">
              <div class="purok-list-header">
                <h3>Purok List</h3>
                <button class="purok-list-close" id="purokListClose">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div class="purok-list-items">
                <div class="purok-list-item">Purok 1: Pearly Shell</div>
                <div class="purok-list-item">Purok 2: Fishermans Village</div>
                <div class="purok-list-item">Purok 3: Rajah Muda</div>
                <div class="purok-list-item">Purok 4: Rajah Muda 4A</div>
                <div class="purok-list-item">Purok 5: Rajah Muda 4B</div>
                <div class="purok-list-item">Purok 6: Rajah Muda 5</div>
                <div class="purok-list-item">Purok 7: Lagang-Lagang</div>
                <div class="purok-list-item">Purok 8: Zone 1A</div>
                <div class="purok-list-item">Purok 9: Zone 2B</div>
                <div class="purok-list-item">Purok 10: Zone 2A</div>
                <div class="purok-list-item">Purok 11: Zone 2B</div>
                <div class="purok-list-item">Purok 12: Zone 2C</div>
                <div class="purok-list-item">Purok 13: Zone 3,4,5</div>
                <div class="purok-list-item">Purok 14: Zone 6</div>
                <div class="purok-list-item">Purok 15: Zone 7</div>
                <div class="purok-list-item">Purok 16: Zone 8</div>
                <div class="purok-list-item">Purok 17: Zone 9</div>
                <div class="purok-list-item">Purok 18: Calsanter</div>
                <div class="purok-list-item">Purok 19: Sagrada Corazon</div>
                <div class="purok-list-item">Purok 20: Gonzales Subd.</div>
                <div class="purok-list-item">Purok 21: Gensanville Phase 1</div>
                <div class="purok-list-item">Purok 22: Gensanville Phase 2</div>
                <div class="purok-list-item">Purok 23: Sitio Rapoa</div>
                <div class="purok-list-item">Purok 24: San Pedro</div>
                <div class="purok-list-item">Purok 25: Asai Village</div>
              </div>
            </div>
          </div>

          <div class="form-group resident-only-field">
            <label for="yearStartedStaying">Year Started Staying</label>
            <select id="yearStartedStaying" name="year_started_staying">
              <option value="">Select Year</option>
              <?php $y=date("Y"); for($i=$y;$i>=1900;$i--) echo "<option value='$i'>$i</option>"; ?>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="contact">Contact Number</label>
              <input
                type="text"
                id="contact"
                name="contact_number"
                placeholder="09XXXXXXXXX"
                required
                inputmode="numeric"
                autocomplete="tel"
                maxlength="11"
                pattern="^09\d{9}$"
                title="Enter a valid PH mobile number starting with 09 (11 digits)."
              />
            </div>
          </div>


          <div class="form-row">
            <div class="form-group resident-only-field">
              <label for="occupation">Occupation</label>
              <input type="text" id="occupation" name="occupation" placeholder="Enter occupation" />
            </div>
          </div>
        </div>

        <!-- Account Info -->
        <div class="form-section">
          <div class="form-group">
            <label for="address">Address</label>
            <input type="text" id="address" name="address"
                   placeholder="Enter address" required />
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   placeholder="Enter email" required />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username"
                     placeholder="Enter username" required />
            </div>
            <div class="form-group">
              <label for="password">Password</label>
              <input type="password" id="password" name="password"
                     placeholder="Password" required />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="confirmPassword">Confirm Password</label>
              <input type="password" id="confirmPassword" name="confirm_password"
                     placeholder="Confirm password" required />
            </div>
          </div>
        </div>

        <!-- Profile Picture -->
        <div class="profile-picture-section">
          <h3 style="text-align:center;margin-bottom:15px;">Profile Picture</h3>
          <div class="profile-picture-container">
            <div class="profile-picture-preview">
              <img src="./pics/profile-placeholder.jpg" id="profileImage" alt="Profile Picture" />
            </div>
            <div class="profile-picture-options">
              <label class="profile-picture-btn" for="profilePictureInput">
                <i class="fas fa-upload"></i> Upload Photo
               <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" capture="environment">
              </label>
              <button type="button" class="profile-picture-btn" id="takePhotoBtn">
                <i class="fas fa-camera"></i> Take Photo
              </button>
            </div>
          </div>
        </div>

        <div class="register-actions">
          <button type="submit" name="register" class="btn btn-register">Register</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Forgot Password Modal (overlay) -->
<div id="forgotModal" class="forgot-modal" aria-hidden="true">
  <div class="forgot-card" role="dialog" aria-modal="true" aria-labelledby="forgotTitle">
    <h3 id="forgotTitle">Reset your password</h3>
    <p class="muted">Enter your account email and we’ll send you a reset link.</p>

    <form id="forgotForm" method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF,ENT_QUOTES) ?>">
      <div class="form-group">
        <label for="forgotEmail">Email</label>
        <input type="email" id="forgotEmail" name="email" class="form-control">
      </div>

      <div class="forgot-actions">
        <button type="button" class="btn btn-outline" id="forgotCancel">Cancel</button>
        <button type="submit" class="btn btn-primary">Send reset link</button>
      </div>

      <div id="forgotStatus" class="form-note" style="margin-top:.5rem"></div>
    </form>
  </div>
</div>


<!-- Terms Modal -->
<div id="tosModal" class="tos-modal" aria-hidden="true">
  <div class="tos-backdrop"></div>
  <div class="tos-dialog" role="dialog" aria-modal="true">
    <div class="tos-header">
      <h2 id="tosTitle">Barangay Bula Online Services – Terms of Service and Privacy Policy</h2>
    </div>
    <div class="tos-body">
      <div class="tos-content" id="tosScroll" >
        <div id="tosText">
              Barangay Bula Online Services – Terms of Service and Privacy Policy

              1. Introduction & Scope

              These Terms of Service (“Terms”) and Privacy Policy (“Policy”) govern your use of the Barangay Bula Online Services Portal (the “System”), which includes online requests for barangay certificates and documents, facility reservations (e.g., gymnasium), and related services. By creating an account or using the System, you acknowledge that you have read, understood, and agree to these Terms and this Policy.

              2. Eligibility & Account Registration

              You must provide accurate and complete information when registering and when submitting requests.

              You are responsible for maintaining the confidentiality of your login credentials and for any activity that occurs under your account.

              We may suspend or terminate accounts that provide false information, violate these Terms, or abuse the System.

              3. Email Verification & Notices

              You must verify your email address to activate your account and receive status updates, reminders, and official notices.

              Transactional messages (e.g., verification links, reservation notices, status updates) will be sent to your registered email.

              4. Acceptable Use

              You agree not to:

              Impersonate another person or misrepresent your affiliation with any entity.

              Interfere with or disrupt the System, servers, or networks.

              Upload or transmit harmful code (e.g., malware).

              Attempt to gain unauthorized access to the System or other user accounts.

              5. Services & Processing Times

              Processing times and availability are subject to barangay schedules, public holidays, and operational constraints.

              Submitting a request does not guarantee approval; approvals are subject to barangay policies, documentary requirements, and verification.

              6. No Refund Policy (Certificates and Gymnasium)

              All payments are final and non-refundable.

              This includes fees for certificate requests, facility reservations (including gymnasium bookings), and any related service charges.

              Rescheduling or cancellations, if available, are subject to barangay policies and scheduling constraints; fees will not be refunded under any circumstances.

              7. Fees & Payments

              Official fees are set by the barangay and may change without prior notice. Any change will apply prospectively.

              You are responsible for any charges and taxes associated with your transactions.

              8. Data We Collect (Privacy)

              We collect and process the following data to deliver services:

              Identity & Contact: name, address, contact number, email, age/sex (if applicable), and related demographic information.

              Service Data: request types, supporting details, timestamps, reservation schedules, and status updates.

              Account & Security: username, hashed password, verification tokens, login timestamps, and IP/user-agent metadata.

              Uploads: profile photo and any attachments required to process your request (if any).

              9. Purpose & Legal Basis

              We process personal data to:

              Provide and manage online barangay services;

              Authenticate users, prevent fraud/abuse, and ensure system security;

              Communicate request updates and official notices;

              Comply with legal obligations and maintain barangay records.
              Processing is based on your consent, performance of a public task, and/or compliance with law.

              10. Data Sharing & Disclosure

              We may share data with:

              Authorized barangay personnel for verification and processing;

              Law enforcement or other government agencies when required by law;

              Service providers acting on our behalf (e.g., email delivery), bound by confidentiality and data protection obligations.
              We do not sell your personal data.

              11. Data Security

              We implement appropriate technical and organizational measures (e.g., encryption in transit, hashed passwords, access controls, logging) to protect your data against unauthorized access, alteration, disclosure, or destruction. No method is 100% secure; you also play a role by safeguarding your credentials.

              12. Data Retention

              We retain data for as long as needed to deliver services, comply with legal obligations, resolve disputes, and enforce policies. Retention periods may be affected by applicable laws and barangay record-keeping requirements.

              13. Your Rights (Philippine Data Privacy Act of 2012)

              Subject to law, you may:

              Access and request a copy of your personal data;

              Request correction of inaccurate or incomplete data;

              Withdraw consent to processing (where applicable);

              Lodge a complaint with the National Privacy Commission (NPC).
              Requests may be sent to the Contact Information below.

              14. Cookies & Logs

              The System may use cookies or similar technologies to maintain sessions, enhance functionality, and collect usage analytics for security and service improvement.

              15. Limitation of Liability

              The System is provided “as is.” To the maximum extent permitted by law, Barangay Bula is not liable for any indirect, incidental, or consequential damages arising from your use of or inability to use the System, service schedules, processing delays, or third-party actions.

              16. Changes to These Terms & Policy

              We may update these Terms and this Policy to reflect changes in services, laws, or operational needs. Material changes will be posted on the System with a new effective date. Continued use after changes constitutes acceptance.

              17. Contact Information

              For privacy or service inquiries, requests, or concerns, you may contact:
              Barangay Bula – Online Services

              By checking “I agree” you confirm that you have read, understood, and agree to be bound by these Terms of Service and Privacy Policy.
        </div>
      </div>
    </div>
    <div class="tos-footer">
      <label class="tos-check">
        <input type="checkbox" id="tosAgree" disabled>
        <span>I have read and agree to the Terms of Service and Privacy Policy.</span>
      </label>
      <div class="tos-actions">
        <button type="button" id="tosDecline" class="btn btn-outline">Decline</button>
        <button type="button" id="tosAccept" class="btn btn-primary" disabled>Accept & Continue</button>
      </div>
    </div>
  </div>
</div>

<script src="./script/index.js"></script>
</body>
</html>
