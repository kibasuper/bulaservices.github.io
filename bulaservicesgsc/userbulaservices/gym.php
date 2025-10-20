<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/certificate_functions.php';

// Require a logged-in user
ensureUserAccess();

try {
    $certificate = new CertificateRequest();
    $userInfo = $certificate->getUserInfo(); // typically: fullName, contactNumber, address, etc.

    // Build robust fallbacks for name & contact
    $fullNameRaw =
        ($userInfo['fullName'] ?? '') !== '' ? $userInfo['fullName'] :
        (($userInfo['full_name'] ?? '') !== '' ? $userInfo['full_name'] : '');

    if ($fullNameRaw === '') {
        $fn = trim((string)($userInfo['first_name'] ?? $_SESSION['first_name'] ?? ''));
        $ln = trim((string)($userInfo['last_name']  ?? $_SESSION['last_name']  ?? ''));
        $fullNameRaw = trim(($userInfo['fullName'] ?? '') . ' ');
        if ($fullNameRaw === '' || $fullNameRaw === ' ') $fullNameRaw = trim($fn . ' ' . $ln);
        if ($fullNameRaw === '') $fullNameRaw = trim((string)($_SESSION['full_name'] ?? ''));
    }

    $contactRaw =
        ($userInfo['contactNumber']  ?? '') !== '' ? $userInfo['contactNumber']  :
        (($userInfo['contact_number'] ?? '') !== '' ? $userInfo['contact_number'] : '');

    if ($contactRaw === '') {
        $contactRaw = trim((string)($_SESSION['contact_number'] ?? $_SESSION['contact'] ?? ''));
    }

    if ($fullNameRaw === '' || $contactRaw === '') {
        $errorMsg = "User information missing. Please log in again.";
        $_SESSION['last_error'] = $errorMsg;
        header("Location: index.php?error=" . urlencode($errorMsg));
        exit();
    }

    // Escaped for HTML inputs
    $fullName  = htmlspecialchars($fullNameRaw, ENT_QUOTES, 'UTF-8');
    $contactNo = htmlspecialchars($contactRaw,  ENT_QUOTES, 'UTF-8');

    // Server "now" (source of truth) in Asia/Manila, ISO-8601 with offset
    $serverNowISO = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('c');

    // --- NEW: read live gym rates for initial banner ---
    $db = getDBConnection();
    $row = $db->query("SELECT morning_rate, evening_rate FROM gym_pricing WHERE id = 1")->fetch();
    $morningRate = isset($row['morning_rate']) ? (float)$row['morning_rate'] : 200.0;
    $eveningRate = isset($row['evening_rate']) ? (float)$row['evening_rate'] : 300.0;

} catch (Throwable $e) {
    error_log("GYM page error: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Gym Reservation</title>

  <!-- Vendor CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>

  <!-- App CSS -->
  <link href="./style/gym.css" rel="stylesheet">
  <style>
    /* minor visual for past slots */
    .time-slot-card.past {
      opacity: .45;
      cursor: not-allowed !important;
      filter: grayscale(20%);
    }
    .rate-info-alert{background:linear-gradient(135deg, rgba(76,201,240,.1) 0%, rgba(255,107,53,.1) 100%);border-left:4px solid #4361ee}
    .morning-badge{background-color:#4cc9f0!important}
    .evening-badge{background-color:#ff6b35!important}
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar bg-light">
    <div class="container-fluid">
      <a href="home.php" class="navbar-brand d-flex align-items-center">
        <img src="./pics/logo.png" alt="Logo" style="height:40px;"/>
        <span class="ms-2">Barangay Bula</span>
      </a>
      <div class="d-flex align-items-center">
        <i class="fas fa-user-circle me-2"></i>
        <span><?= $fullName ?></span>
      </div>
    </div>
  </nav>

  <!-- Calendar -->
  <div class="container mt-4">
    <h1><i class="fas fa-calendar-plus me-2"></i> Gym Reservation</h1>
    <p>Select a date from the calendar to view available slots.</p>

    <div id="calendar"></div>

    <div class="calendar-legend">
      <div class="legend-item"><div class="legend-color" style="background-color: rgba(76,201,240,.1)"></div><span>Available</span></div>
      <div class="legend-item"><div class="legend-color" style="background-color: rgba(255,190,11,.1)"></div><span>Limited Slots</span></div>
      <div class="legend-item"><div class="legend-color" style="background-color: rgba(247,37,133,.1)"></div><span>Fully Booked</span></div>
      <div class="legend-item"><div class="legend-color" style="background-color: rgba(255,193,7,.2)"></div><span>Maintenance</span></div>
      <div class="legend-item"><div class="legend-color" style="background-color: rgba(0,0,0,.05)"></div><span>Past (today)</span></div>
    </div>
  </div>

  <!-- Time Slot Modal -->
  <div class="modal fade" id="timeSlotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Select Time Slots</h5>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h6 id="selectedDateHeader" class="mb-3"></h6>

          <!-- NEW: dynamic, server-rendered banner + JS-updatable span -->
          <div class="alert rate-info-alert">
            <strong>Rate Information:</strong>
            <span id="currentRates" class="ms-2">
              7AM–5PM: ₱<?= number_format($morningRate, 2) ?>/hour • 5PM–10PM: ₱<?= number_format($eveningRate, 2) ?>/hour
            </span>
          </div>

          <div class="quick-select-container mb-3">
            <button type="button" class="btn btn-outline-primary quick-select-btn" id="wholeDayBtn">Full Day (7AM–10PM)</button>
            <button type="button" class="btn btn-outline-primary quick-select-btn" id="morningRateBtn">Morning (7AM–5PM)</button>
            <button type="button" class="btn btn-outline-primary quick-select-btn" id="eveningRateBtn">Evening (5PM–10PM)</button>
            <button type="button" class="btn btn-outline-secondary quick-select-btn" id="clearSelectionBtn">Clear Selection</button>
          </div>

          <div id="time-slots-container" class="d-flex flex-wrap gap-2"></div>

          <div id="selectedSlotsSummary" class="mt-3" style="display:none;">
            <h6>Selected Slots:</h6>
            <div id="selectedSlotsList"></div>
            <div class="rate-breakdown mt-2"><small class="text-muted" id="rateBreakdown"></small></div>
            <strong>Total: <span id="estimatedTotal">₱0</span></strong>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="proceedToReservation" disabled>Continue</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Reservation Form Modal -->
  <div class="modal fade" id="reservationFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Complete Reservation</h5>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            Date: <strong id="reservationDateDisplay"></strong>
            <ul id="reservationTimesDisplay" class="mb-1"></ul>
            Total: <strong id="reservationTotalDisplay"></strong><br/>
            Reference: <strong id="referenceNumberDisplay"></strong>
          </div>

          <form id="reservationForm">
            <input type="hidden" id="reservationDate"/>
            <input type="hidden" id="selectedSlots"/>
            <input type="hidden" id="referenceNumber"/>

            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" id="residentName" class="form-control" value="<?= $fullName ?>" readonly aria-readonly="true" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input type="tel" id="contactNumber" class="form-control" value="<?= $contactNo ?>" readonly aria-readonly="true" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Activity</label>
              <input type="text" id="activityType" class="form-control" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea id="reservationNotes" class="form-control" placeholder="Optional details"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="submitReservation">Submit</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content text-center">
        <div class="modal-header">
          <h5 class="modal-title">Reservation Submitted</h5>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <i class="fas fa-check-circle text-success fa-3x"></i>
          <p class="mt-3">Reference: <strong id="confirmedReference"></strong></p>
          <p>Amount: ₱<span id="confirmedAmount"></span></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Expose user + server time to JS -->
  <script>
    window.USER_FULL_NAME = <?= json_encode($fullNameRaw) ?>;
    window.USER_CONTACT   = <?= json_encode($contactRaw) ?>;
    window.SERVER_NOW_ISO = <?= json_encode($serverNowISO) ?>;
  </script>

  <!-- Vendor JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

  <!-- App JS -->
  <script src="./script/gym.js"></script>
</body>
</html>
