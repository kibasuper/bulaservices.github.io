// script/gyms.js (ADMIN)
document.addEventListener('DOMContentLoaded', () => {
  const timeSlotModal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
  const reservationModal = new bootstrap.Modal(document.getElementById('reservationFormModal'));
  const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));

  const PREFILL_NAME = (window.PREFILL_NAME || '').trim();
  const PREFILL_CONTACT = (window.PREFILL_CONTACT || '').trim();

  let SERVER_NOW_BASE_ISO = window.SERVER_NOW_ISO || null;
  const CLIENT_ANCHOR_MS = Date.now();
  let SERVER_ANCHOR_MS = SERVER_NOW_BASE_ISO ? Date.parse(SERVER_NOW_BASE_ISO) : Date.now();

  function alignServerNow(serverIso) { if (serverIso) { SERVER_NOW_BASE_ISO = serverIso; SERVER_ANCHOR_MS = Date.parse(serverIso); } }
  function serverNowMs() { return (SERVER_ANCHOR_MS + (Date.now() - CLIENT_ANCHOR_MS)); }
  function serverNowParts() { const d = new Date(serverNowMs()); return { y:d.getFullYear(), m:d.getMonth()+1, d:d.getDate(), hour:d.getHours() }; }
  function isSameYMD(dateStr, parts) { const [Y,M,D] = dateStr.split('-').map(n=>parseInt(n,10)); return Y===parts.y && M===parts.m && D===parts.d; }

  // Live-editable rates; updated from server on load
  const RATES = { MORNING: 200, EVENING: 300 };

  // ---- API base ----
  const GYM_API = './php/gymsback.php';
  async function postGym(payload) {
    const res = await fetch(GYM_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    let data, text;
    try { data = await res.clone().json(); } catch { text = await res.text(); }
    if (!res.ok || (data && data.status !== 'success')) {
      throw new Error((data && data.message) || text || `HTTP ${res.status}`);
    }
    return data;
  }

  let selectedDate = '';
  let selectedSlots = [];
  let activeQuickSelect = null;
  let calendar;

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
        rateType: isMorning ? 'morning' : 'evening',
        past: false
      };
    });
  }

  // --- NEW: keep selected slot rates in sync with latest RATES ---
  function refreshSelectedRates() {
    selectedSlots = selectedSlots.map(s => ({
      ...s,
      rate: (s.hour < 17) ? RATES.MORNING : RATES.EVENING
    }));
  }

  async function loadTimeSlots(date) {
    const container = document.getElementById('time-slots-container');
    container.innerHTML = '';
    selectedSlots = [];
    document.getElementById('selectedSlotsSummary').style.display = 'none';
    document.getElementById('proceedToReservation').disabled = true;

    try {
      const res = await postGym({ action: 'get_slots', date });

      // adopt live rates from server (NEW)
      if (res.rates && typeof res.rates.morning === 'number' && typeof res.rates.evening === 'number') {
        RATES.MORNING = res.rates.morning;
        RATES.EVENING = res.rates.evening;
        renderCurrentRates();           // update banner immediately
        refreshSelectedRates();         // in case something was preselected (safety)
      }

      const bookedHours = Array.isArray(res.booked) ? res.booked.map(b=>b.hour) : [];
      if (res.server_now) alignServerNow(res.server_now);

      const parts = serverNowParts();
      const isToday = isSameYMD(date, parts);

      const slots = baseSlots().map(s => ({
        ...s,
        booked: bookedHours.includes(s.hour),
        past: isToday ? (s.hour <= parts.hour) : false
      }));

      slots.forEach(slot => renderSlot(container, slot));
      // banner already rendered above; keep for first paint if no rates yet:
      if (!res.rates) renderCurrentRates();
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger"><i class="fa fa-triangle-exclamation me-2"></i>Failed to load slots.</div>`;
      console.error(e);
    }
  }

  function renderCurrentRates() {
    const el = document.getElementById('currentRates');
    if (!el) return;
    // match the format shown in gyms.php banner
    el.textContent = `7AM–5PM: ₱${Number(RATES.MORNING)} /hour • 5PM–10PM: ₱${Number(RATES.EVENING)} /hour`;
  }

  function renderSlot(container, slot) {
    const card = document.createElement('div');
    const isDisabled = slot.booked || slot.past;
    card.className = `time-slot-card ${slot.rateType}-rate${slot.booked ? ' booked' : ''}${slot.past ? ' past' : ''}`;
    card.dataset.hour = String(slot.hour);
    card.dataset.rate = String(slot.rate);

    if (isDisabled) {
      card.setAttribute('aria-disabled', 'true');
      card.setAttribute('tabindex', '-1');
      card.title = slot.booked ? 'Already booked' : 'Past slot';
    } else {
      card.setAttribute('role', 'button');
      card.setAttribute('tabindex', '0');
      card.title = 'Click to select';
      card.addEventListener('click', () => toggleSelect(card, slot));
      card.addEventListener('keydown', (e) => {
        if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggleSelect(card, slot); }
      });
    }

    const badge = document.createElement('span');
    badge.className = `rate-badge ${slot.rateType}`;
    badge.textContent = `₱${slot.rate}`;

    const timeEl = document.createElement('div');
    timeEl.className = 'time-slot-range';
    timeEl.textContent = slot.time;

    card.appendChild(badge);
    card.appendChild(timeEl);
    container.appendChild(card);
  }

  function toggleSelect(card, slot) {
    const idx = selectedSlots.findIndex(s => s.hour === slot.hour);
    if (idx === -1) { selectedSlots.push(slot); card.classList.add('selected'); }
    else { selectedSlots.splice(idx, 1); card.classList.remove('selected'); }

    // ensure selected slots use the latest live rates (NEW)
    refreshSelectedRates();

    updateSummary();
    document.getElementById('proceedToReservation').disabled = selectedSlots.length === 0;
    activeQuickSelect = null;
    updateQuickButtons();
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
      .map(s => `• ${s.time} <small class="text-muted">(₱${s.rate}/hr)</small>`).join('<br/>');

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

  function setupQuickSelect() {
    const map = {
      wholeDayBtn:{start:7,end:22},
      morningRateBtn:{start:7,end:17},
      eveningRateBtn:{start:17,end:22}
    };
    Object.keys(map).forEach(id=>{
      const btn = document.getElementById(id);
      if (!btn) return;
      btn.addEventListener('click', ()=>{
        if (activeQuickSelect === id) { clearSelection(); activeQuickSelect = null; }
        else { selectRange(map[id].start, map[id].end); activeQuickSelect = id; }
        updateQuickButtons();
      });
    });
    document.getElementById('clearSelectionBtn')?.addEventListener('click', ()=>{
      clearSelection(); activeQuickSelect = null; updateQuickButtons();
    });
  }

  function updateQuickButtons() {
    ['wholeDayBtn','morningRateBtn','eveningRateBtn'].forEach(id=>{
      const btn = document.getElementById(id);
      if (btn) btn.classList.toggle('active', id === activeQuickSelect);
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
    const cards = Array.from(document.querySelectorAll('.time-slot-card'));
    cards.forEach(card=>{
      const hour = parseInt(card.dataset.hour,10);
      const isDisabled = card.classList.contains('booked') || card.classList.contains('past');
      if (!isDisabled && hour>=start && hour<end) {
        card.classList.add('selected');
        selectedSlots.push({
          hour,
          time: card.querySelector('.time-slot-range').textContent,
          booked: false,
          // use CURRENT live rates (NEW)
          rate: hour < 17 ? RATES.MORNING : RATES.EVENING,
          rateType: hour < 17 ? 'morning' : 'evening'
        });
      }
    });
    updateSummary();
    document.getElementById('proceedToReservation').disabled = selectedSlots.length === 0;
  }

  document.getElementById('proceedToReservation').addEventListener('click', ()=>{
    const dateObj = new Date(selectedDate);
    document.getElementById('reservationDateDisplay').textContent =
      dateObj.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
    document.getElementById('reservationTimesDisplay').innerHTML =
      selectedSlots.sort((a,b)=>a.hour-b.hour).map(s=>`<li>${s.time} <small class="text-muted">(₱${s.rate}/hr)</small></li>`).join('');
    document.getElementById('reservationTotalDisplay').textContent = `₱${selectedSlots.reduce((t,s)=>t+s.rate,0)}`;

    document.getElementById('reservationDate').value = selectedDate;
    document.getElementById('selectedSlots').value = JSON.stringify(selectedSlots);

    const ref = generateRef();
    document.getElementById('referenceNumberDisplay').textContent = ref;
    document.getElementById('referenceNumber').value = ref;

    const nameEl = document.getElementById('residentName');
    const contactEl = document.getElementById('contactNumber');
    if (nameEl && !nameEl.value) nameEl.value = PREFILL_NAME;
    if (contactEl && !contactEl.value) contactEl.value = PREFILL_CONTACT;

    timeSlotModal.hide();
    reservationModal.show();
  });

  document.getElementById('submitReservation').addEventListener('click', async ()=>{
    const form = document.getElementById('reservationForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const residentName = (document.getElementById('residentName').value || '').trim();
    const contactNumber = (document.getElementById('contactNumber').value || '').trim();

    const payload = {
      action: 'create_reservation',
      date: document.getElementById('reservationDate').value,
      slots: JSON.parse(document.getElementById('selectedSlots').value),
      resident: residentName,
      contact: contactNumber,
      activity: document.getElementById('activityType').value,
      notes: document.getElementById('reservationNotes').value,
      reference: document.getElementById('referenceNumber').value
    };

    try {
      const res = await postGym(payload);
      document.getElementById('confirmedReference').textContent = res.reference || payload.reference;
      document.getElementById('confirmedAmount').textContent = String(res.total ?? selectedSlots.reduce((t,s)=>t+s.rate,0));
      document.getElementById('confirmedWho').textContent =
        residentName ? `${residentName}${contactNumber ? ' • ' + contactNumber : ''}` : '';

      reservationModal.hide();
      confirmationModal.show();
      form.reset();
      await loadTimeSlots(selectedDate);
    } catch (e) {
      console.error(e);
      alert(e.message || 'Failed to submit reservation. Please try again.');
    }
  });

  document.getElementById('printReservationBtn')?.addEventListener('click', () => window.print());

  initCalendar();
  setupQuickSelect();
});
