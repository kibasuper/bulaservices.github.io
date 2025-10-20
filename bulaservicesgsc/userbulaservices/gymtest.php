<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bula - Gym Reservation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>

  <style>
    :root {
      --primary:#4361ee; --primary-light:#5e72e4; --primary-dark:#3a4ab9;
      --secondary:#3f37c9; --success:#4cc9f0; --danger:#f72585;
      --warning:#ffbe0b; --light:#f8f9fa; --dark:#212529; --gray:#6c757d;
      --light-gray:#e9ecef; --border-radius:12px; --box-shadow:0 8px 24px rgba(0,0,0,.08);
      --transition:.3s ease; --morning-rate:#4cc9f0; --evening-rate:#ff6b35;
    }
    body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:#f8fafc;color:#1f2937;line-height:1.6;}
    .navbar{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.1);padding:.8rem 2rem;position:fixed;width:100%;top:0;z-index:1030;display:flex;justify-content:space-between;align-items:center;height:60px;transition:var(--transition);}
    .navbar-brand{font-size:1.25rem;font-weight:700;color:var(--primary);text-decoration:none;display:flex;align-items:center;gap:.5rem;}
    .navbar-brand img{height:36px;transition:transform .3s ease;}
    .navbar-brand:hover img{transform:scale(1.1);}
    .container{padding-top:80px;margin-top:0;}
    #calendar{background:#fff;border-radius:var(--border-radius);box-shadow:var(--box-shadow);padding:1.5rem;margin-bottom:2rem;}
    .fc .fc-daygrid-day-number{font-size:1.6rem;font-weight:600;padding:8px;color:var(--dark);width:100%;text-align:center;margin-top:8px;text-decoration:none!important;}
    .fc-day-past:not(.fc-day-other){opacity:.6;background-color:rgba(108,117,125,.1);}
    .fc-day-past .fc-daygrid-day-number{color:var(--gray);} .fc-day-past{pointer-events:none;cursor:default;}
    .fc .fc-daygrid-day.fc-day-today{background-color:rgba(67,97,238,.1);}
    .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number{color:var(--primary);font-weight:700;}
    .fc .fc-toolbar-title{font-size:1.8rem;font-weight:700;color:var(--primary);}
    .fc .fc-button{font-size:1rem;padding:.5rem 1rem;background:var(--primary);border:none;}
    .fc .fc-button:hover{background:var(--primary-dark);}
    .fc .fc-daygrid-day-frame{min-height:100px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;}
    @media(max-width:768px){.fc .fc-toolbar{flex-direction:column;gap:1rem}.fc .fc-toolbar-title{font-size:1.5rem}.fc .fc-daygrid-day-number{font-size:1.4rem;padding:4px;margin-top:4px}.fc .fc-daygrid-day-frame{min-height:80px}}
    @media(max-width:576px){.fc .fc-daygrid-day-number{font-size:1.2rem}.fc .fc-daygrid-day-frame{min-height:70px}.fc .fc-button{padding:.4rem .8rem;font-size:.9rem}}

    .calendar-legend{display:flex;flex-wrap:wrap;gap:1rem;margin-top:1rem;justify-content:center}
    .legend-item{display:flex;align-items:center;gap:.5rem;font-size:.9rem}
    .legend-color{width:20px;height:20px;border-radius:4px}

    .time-slot-card{width:140px;padding:12px 8px;border:2px solid #dee2e6;border-radius:8px;text-align:center;cursor:pointer;transition:all .2s ease;margin-bottom:8px;position:relative}
    .time-slot-card:hover:not(.booked){transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.1)}
    .time-slot-card.selected{background-color:rgba(67,97,238,.1);color:var(--primary);font-weight:600}
    .time-slot-card.booked{background:#f8f9fa;border-color:#dee2e6;color:#6c757d;cursor:not-allowed;opacity:.7}
    .time-slot-card.morning-rate{border-color:var(--morning-rate)}
    .time-slot-card.morning-rate:hover:not(.booked){box-shadow:0 4px 8px rgba(76,201,240,.3)}
    .time-slot-card.morning-rate.selected{border-color:var(--morning-rate);background-color:rgba(76,201,240,.1)}
    .time-slot-card.evening-rate{border-color:var(--evening-rate)}
    .time-slot-card.evening-rate:hover:not(.booked){box-shadow:0 4px 8px rgba(255,107,53,.3)}
    .time-slot-card.evening-rate.selected{border-color:var(--evening-rate);background-color:rgba(255,107,53,.1)}
    .rate-badge{position:absolute;top:4px;right:4px;font-size:10px;padding:2px 6px;border-radius:10px;font-weight:bold;color:#fff}
    .rate-badge.morning{background-color:var(--morning-rate)}
    .rate-badge.evening{background-color:var(--evening-rate)}
    .time-slot-range{font-size:.9rem;font-weight:500;margin-bottom:4px}
    .time-slot-price{font-size:.8rem;font-weight:600}
    .quick-select-container{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
    .quick-select-btn{flex:1;min-width:120px;padding:10px;border-radius:8px;font-weight:500;transition:all .2s ease}
    .quick-select-btn.active{background:var(--primary);color:#fff;border-color:var(--primary)}
    .rate-info-alert{background:linear-gradient(135deg, rgba(76,201,240,.1) 0%, rgba(255,107,53,.1) 100%);border-left:4px solid var(--primary)}
    .morning-badge{background-color:var(--morning-rate)!important}
    .evening-badge{background-color:var(--evening-rate)!important}
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
    </div>
  </div>

  <!-- Time Slot Modal -->
  <div class="modal fade" id="timeSlotModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Select Time Slots</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <h6 id="selectedDateHeader" class="mb-3"></h6>

          <div class="alert rate-info-alert">
            <strong>Rate Information:</strong>
            <span class="badge morning-badge ms-2">7AM–5PM: ₱200/hour</span>
            <span class="badge evening-badge ms-2">5PM–10PM: ₱300/hour</span>
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
  <div class="modal fade" id="reservationFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Complete Reservation</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
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
              <input type="text" id="residentName" class="form-control" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input type="tel" id="contactNumber" class="form-control" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Activity</label>
              <input type="text" id="activityType" class="form-control" required/>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea id="reservationNotes" class="form-control"></textarea>
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
  <div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content text-center">
        <div class="modal-header">
          <h5 class="modal-title">Reservation Submitted</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <i class="fas fa-check-circle text-success fa-3x"></i>
          <p class="mt-3">Reference: <strong id="confirmedReference"></strong></p>
          <p>Amount: ₱<span id="confirmedAmount"></span></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const timeSlotModal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
      const reservationModal = new bootstrap.Modal(document.getElementById('reservationFormModal'));
      const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));

      const RATES = { MORNING: 200, EVENING: 300 };
      let selectedDate = '';
      let selectedSlots = [];
      let activeQuickSelect = null;
      let calendar;

      // Calendar
      function initCalendar() {
        const el = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(el, {
          initialView: 'dayGridMonth',
          headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
          height: 'auto',
          dateClick: onDateClick,
          nowIndicator: true,
          validRange: { start: new Date() }
        });
        calendar.render();
      }

      function onDateClick(info) {
        selectedDate = info.dateStr;
        document.getElementById('selectedDateHeader').textContent =
          new Date(selectedDate).toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
        loadTimeSlots(selectedDate);
        timeSlotModal.show();
        activeQuickSelect = null;
        updateQuickButtons();
      }

      // Build 7AM–10PM slots
      function baseSlots() {
        const hours = [7,8,9,10,11,12,13,14,15,16,17,18,19,20,21];
        return hours.map(hour => {
          const isMorning = hour < 17;
          const rate = isMorning ? RATES.MORNING : RATES.EVENING;
          const display = (h) => {
            const p = h >= 12 ? 'PM' : 'AM';
            const dh = h > 12 ? h - 12 : h;
            return `${dh}:00 ${p}`;
          };
          return {
            hour,
            time: `${display(hour)} - ${display(hour+1)}`,
            booked: false,
            rate,
            rateType: isMorning ? 'morning' : 'evening'
          };
        });
      }

      async function loadTimeSlots(date) {
        const container = document.getElementById('time-slots-container');
        container.innerHTML = '';
        selectedSlots = [];
        document.getElementById('selectedSlotsSummary').style.display = 'none';
        document.getElementById('proceedToReservation').disabled = true;

        try {
          const resp = await fetch('Gymback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_slots', date })
          });
          const res = await resp.json();
          const bookedHours = Array.isArray(res.booked) ? res.booked.map(b=>b.hour) : [];

          const slots = baseSlots().map(s => ({ ...s, booked: bookedHours.includes(s.hour) }));

          slots.forEach(slot => renderSlot(container, slot));
        } catch (e) {
          container.innerHTML = `<div class="alert alert-danger"><i class="fa fa-triangle-exclamation me-2"></i>Failed to load slots.</div>`;
          console.error(e);
        }
      }

      function renderSlot(container, slot) {
        const card = document.createElement('div');
        card.className = `time-slot-card ${slot.rateType}-rate ${slot.booked ? 'booked' : ''}`;
        card.dataset.hour = slot.hour;
        card.dataset.rate = slot.rate;

        const badge = document.createElement('span');
        badge.className = `rate-badge ${slot.rateType}`;
        badge.textContent = `₱${slot.rate}`;
        card.appendChild(badge);

        const timeEl = document.createElement('div');
        timeEl.className = 'time-slot-range';
        timeEl.textContent = slot.time;
        card.appendChild(timeEl);

        const priceEl = document.createElement('div');
        priceEl.className = 'time-slot-price';
        priceEl.textContent = `${slot.rateType.toUpperCase()} RATE`;
        card.appendChild(priceEl);

        if (!slot.booked) {
          card.addEventListener('click', () => {
            const idx = selectedSlots.findIndex(s => s.hour === slot.hour);
            if (idx === -1) {
              selectedSlots.push(slot);
              card.classList.add('selected');
            } else {
              selectedSlots.splice(idx, 1);
              card.classList.remove('selected');
            }
            updateSummary();
            document.getElementById('proceedToReservation').disabled = selectedSlots.length === 0;
            activeQuickSelect = null;
            updateQuickButtons();
          });
        }

        container.appendChild(card);
      }

      function updateSummary() {
        const wrap = document.getElementById('selectedSlotsSummary');
        const list = document.getElementById('selectedSlotsList');
        const totalEl = document.getElementById('estimatedTotal');
        const breakdownEl = document.getElementById('rateBreakdown');

        if (selectedSlots.length === 0) {
          wrap.style.display = 'none';
          totalEl.textContent = '₱0';
          breakdownEl.textContent = '';
          return;
        }

        wrap.style.display = 'block';
        list.innerHTML = selectedSlots
          .sort((a,b)=>a.hour-b.hour)
          .map(s => `• ${s.time} <small class="text-muted">(₱${s.rate}/hr - ${s.rateType})</small>`)
          .join('<br/>');

        const morning = selectedSlots.filter(s => s.rateType === 'morning').length;
        const evening = selectedSlots.filter(s => s.rateType === 'evening').length;
        const total = selectedSlots.reduce((t, s) => t + s.rate, 0);
        totalEl.textContent = `₱${total}`;
        breakdownEl.textContent = [
          morning ? `${morning} morning = ₱${morning * RATES.MORNING}` : '',
          evening ? `${evening} evening = ₱${evening * RATES.EVENING}` : ''
        ].filter(Boolean).join(' + ');
      }

      function generateRef() {
        const letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const numbers = '0123456789';
        let out = 'GYM-';
        for (let i=0;i<3;i++) out += letters[Math.floor(Math.random()*letters.length)];
        for (let i=0;i<4;i++) out += numbers[Math.floor(Math.random()*numbers.length)];
        return out;
      }

      // Quick select
      function setupQuickSelect() {
        const map = {
          wholeDayBtn:{start:7,end:22},
          morningRateBtn:{start:7,end:17},
          eveningRateBtn:{start:17,end:22}
        };
        Object.keys(map).forEach(id=>{
          const btn = document.getElementById(id);
          btn.addEventListener('click', ()=>{
            if (activeQuickSelect === id) {
              clearSelection();
              activeQuickSelect = null;
            } else {
              selectRange(map[id].start, map[id].end);
              activeQuickSelect = id;
            }
            updateQuickButtons();
          });
        });
        document.getElementById('clearSelectionBtn').addEventListener('click', ()=>{
          clearSelection();
          activeQuickSelect = null;
          updateQuickButtons();
        });
      }

      function updateQuickButtons() {
        ['wholeDayBtn','morningRateBtn','eveningRateBtn'].forEach(id=>{
          const btn = document.getElementById(id);
          btn.classList.toggle('active', id === activeQuickSelect);
        });
      }

      function clearSelection() {
        selectedSlots = [];
        document.querySelectorAll('.time-slot-card.selected').forEach(c=>c.classList.remove('selected'));
        updateSummary();
        document.getElementById('proceedToReservation').disabled = true;
      }

      function selectRange(start,end) {
        clearSelection();
        const cards = Array.from(document.querySelectorAll('.time-slot-card:not(.booked)'));
        cards.forEach(card=>{
          const hour = parseInt(card.dataset.hour,10);
          if (hour>=start && hour<end) {
            card.classList.add('selected');
            selectedSlots.push({
              hour,
              time: card.querySelector('.time-slot-range').textContent,
              booked: false,
              rate: parseInt(card.dataset.rate,10),
              rateType: hour < 17 ? 'morning' : 'evening'
            });
          }
        });
        updateSummary();
        document.getElementById('proceedToReservation').disabled = selectedSlots.length === 0;
      }

      // Proceed → fill form modal
      document.getElementById('proceedToReservation').addEventListener('click', ()=>{
        const dateObj = new Date(selectedDate);
        document.getElementById('reservationDateDisplay').textContent =
          dateObj.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
        document.getElementById('reservationTimesDisplay').innerHTML =
          selectedSlots.sort((a,b)=>a.hour-b.hour).map(s=>`<li>${s.time} <small class="text-muted">(₱${s.rate}/hr - ${s.rateType})</small></li>`).join('');
        document.getElementById('reservationTotalDisplay').textContent = `₱${selectedSlots.reduce((t,s)=>t+s.rate,0)}`;

        document.getElementById('reservationDate').value = selectedDate;
        document.getElementById('selectedSlots').value = JSON.stringify(selectedSlots);

        const ref = generateRef();
        document.getElementById('referenceNumberDisplay').textContent = ref;
        document.getElementById('referenceNumber').value = ref;

        timeSlotModal.hide();
        reservationModal.show();
      });

      // Submit → call backend (server re-validates availability & price)
      document.getElementById('submitReservation').addEventListener('click', async ()=>{
        const form = document.getElementById('reservationForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const payload = {
          action: 'create_reservation',
          date: document.getElementById('reservationDate').value,
          slots: JSON.parse(document.getElementById('selectedSlots').value),
          resident: document.getElementById('residentName').value,
          contact: document.getElementById('contactNumber').value,
          activity: document.getElementById('activityType').value,
          notes: document.getElementById('reservationNotes').value,
          reference: document.getElementById('referenceNumber').value
        };

        try {
          const resp = await fetch('Gymback.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
          });
          const res = await resp.json();

          if (res.status === 'success') {
            document.getElementById('confirmedReference').textContent = res.reference || payload.reference;
            document.getElementById('confirmedAmount').textContent = res.total.toString();

            reservationModal.hide();
            confirmationModal.show();
            form.reset();

            // Refresh slot list for that date
            await loadTimeSlots(selectedDate);
          } else {
            alert(res.message || 'Reservation failed.');
          }
        } catch (e) {
          console.error(e);
          alert('Failed to submit reservation. Please try again.');
        }
      });

      // Init
      initCalendar();
      setupQuickSelect();
    });
  </script>
</body>
</html>
