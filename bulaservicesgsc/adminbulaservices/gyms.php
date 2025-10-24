<?php
declare(strict_types=1);

try {
  require_once __DIR__ . '/server/config.php';

  // Server "now" (Asia/Manila) for client alignment
  $serverNowISO = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('c');

  // Read live gym rates for initial banner
  $db = getDBConnection();
  $row = $db->query("SELECT morning_rate, evening_rate FROM gym_pricing WHERE id = 1")->fetch();
  $morningRate = isset($row['morning_rate']) ? (float)$row['morning_rate'] : 200.0;
  $eveningRate = isset($row['evening_rate']) ? (float)$row['evening_rate'] : 300.0;

} catch (Throwable $e) {
  error_log("GYMS page error: " . $e->getMessage());
  http_response_code(500);
  exit('Server error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Gym Reservation (Admin)</title>

  <!-- Vendor CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>

  <!-- App CSS -->
  <link href="./css/gyms.css" rel="stylesheet">

  <style>
    .app-header{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.08);position:fixed;top:0;left:0;right:0;z-index:1030}
    .app-header .header-content{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 0}
    .app-header h1{margin:0;font-size:1.4rem;font-weight:700;color:#1f2937;display:flex;align-items:center;gap:.6rem}
    .app-header h1 i{color:#4361ee}
    .header-actions{display:flex;align-items:center;gap:1rem}
    .dashboard-link{display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;font-weight:600;color:#4361ee;background:rgba(67,97,238,.08);padding:.5rem .75rem;border-radius:10px;transition:background .2s ease, transform .2s ease}
    .dashboard-link:hover{background:rgba(67,97,238,.15);transform:translateY(-1px)}
    .container{padding-top:80px}
  </style>
</head>
<body>

  <!-- App Header -->
  <header class="app-header">
    <div class="container header-content">
      <h1><i class="fas fa-warehouse"></i> Gym Reservation</h1>
      <div class="header-actions">
        <a href="gymrequest.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Back</a>
      </div>
    </div>
  </header>

  <!-- Calendar -->
  <div class="container mt-4">
    <h1><i class="fas fa-calendar-plus me-2"></i> Gym Reservation</h1>
    <p>Select a date from the calendar to view available slots.</p>

    <div id="calendar"></div>

    <div class="calendar-legend">
      <div class="legend-item">
        <div class="legend-color available"></div><span>Available</span>
      </div>
      <div class="legend-item">
        <div class="legend-color limited"></div><span>Limited Slots</span>
      </div>
      <div class="legend-item">
        <div class="legend-color full"></div><span>Fully Booked</span>
      </div>
      <div class="legend-item">
        <div class="legend-color past"></div><span>Past date</span>
      </div>
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

          <form id="reservationForm" novalidate>
            <input type="hidden" id="reservationDate"/>
            <input type="hidden" id="selectedSlots"/>
            <input type="hidden" id="referenceNumber"/>

            <!-- Honeypot anti-bot (should remain empty) -->
            <input type="text" id="website" name="website" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off"/>

            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" id="residentName" class="form-control" placeholder="Juan Dela Cruz" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input
                type="tel"
                id="contactNumber"
                class="form-control"
                placeholder="09XXXXXXXXX"
                required
                inputmode="numeric"
                pattern="^0\d{10}$"
                title="Please enter an 11-digit PH mobile number starting with 0 (e.g., 09XXXXXXXXX)"
              />
              <div class="form-text">Format: 11 digits, starts with 0 (e.g., 09XXXXXXXXX)</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Activity</label>
              <input type="text" id="activityType" class="form-control" placeholder="e.g., Basketball Practice" required/>
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

  <!-- Confirmation Modal (with Print button) -->
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
          <p class="text-muted" id="confirmedWho"></p>

          <div class="d-flex gap-2 justify-content-center mt-3">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button class="btn btn-primary" id="printReservationBtn">
              <i class="fas fa-print me-1"></i> Print Confirmation
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Printable Reservation Confirmation (simple only) -->
  <div id="printArea" aria-hidden="true">
    <div class="print-simple">
      <h3>Barangay Bula — Gym Reservation</h3>
      <div class="print-row"><strong>Reference:</strong> <span id="p_ref"></span></div>
      <div class="print-row"><strong>Name:</strong> <span id="p_name"></span></div>
      <div class="print-row"><strong>Amount:</strong> ₱<span id="p_total"></span></div>
    </div>
  </div>

  <!-- Expose server time & API URL only -->
  <script>
    window.SERVER_NOW_ISO  = <?= json_encode($serverNowISO) ?>;
    window.GYM_API_URL     = '/adminbulaservices/php/gyms_proxy.php'; /* if you use a proxy */
  </script>

  <!-- Vendor JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

  <!-- App JS -->
  <script src="./script/gyms.js"></script>
</body>
</html>
