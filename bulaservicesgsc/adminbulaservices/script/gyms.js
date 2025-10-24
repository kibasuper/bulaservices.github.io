// script/gyms.js (ADMIN, month coloring + server-aligned "past date" click guard + Manila TZ + no autofill)
document.addEventListener('DOMContentLoaded', () => {
  const timeSlotModal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
  const reservationModal = new bootstrap.Modal(document.getElementById('reservationFormModal'));
  const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));

  // ----- Server-aligned clock (prevents local clock mismatch)
  let SERVER_NOW_BASE_ISO = window.SERVER_NOW_ISO || null;
  const CLIENT_ANCHOR_MS = Date.now();
  let SERVER_ANCHOR_MS = SERVER_NOW_BASE_ISO ? Date.parse(SERVER_NOW_BASE_ISO) : Date.now();
  function alignServerNow(serverIso) {
    if (serverIso) { SERVER_NOW_BASE_ISO = serverIso; SERVER_ANCHOR_MS = Date.parse(serverIso); }
  }
  function serverNowMs() { return (SERVER_ANCHOR_MS + (Date.now() - CLIENT_ANCHOR_MS)); }

  // Return YYYY-MM-DD for "now" in Asia/Manila based on server clock
  function serverTodayYMD() {
    const d = new Date(serverNowMs());
    // en-CA prints YYYY-MM-DD; force Manila timezone so "today" is correct
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year: 'numeric', month: '2-digit', day: '2-digit'
    }).format(d);
  }

  function serverNowParts() {
    const d = new Date(serverNowMs());
    // Get date/time components for Manila to judge “past hours” on today
    const str = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', hour12:false
    }).formatToParts(d).reduce((acc,p)=>{acc[p.type]=p.value; return acc;}, {});
    return { y:parseInt(str.year,10), m:parseInt(str.month,10), d:parseInt(str.day,10), hour:parseInt(str.hour,10) };
  }
  function isSameYMD(dateStr, parts) {
    const [Y,M,D] = dateStr.split('-').map(n=>parseInt(n,10));
    return Y===parts.y && M===parts.m && D===parts.d;
  }
  function iso(d){ return d.toISOString().slice(0,10); } // YYYY-MM-DD

  // Live-editable rates (fetched from backend each load)
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
  let dayStatesSource = null; // event source for background day colors

  function initCalendar() {
    const el = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(el, {
      timeZone: 'Asia/Manila',               // <-- make FC “today” match Manila
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
      height: 'auto',
      dateClick: onDateClick,
      nowIndicator: true,
      // Keep navigation to past months visible; CSS + JS will block interaction on past days.
      // validRange: { start: new Date() },
      datesSet: onDatesSet // refresh month colors when view changes
    });
    calendar.render();
  }

  // Repaint month colors when the visible range changes
  function onDatesSet(info) {
    const viewStart = info.view.currentStart; // first day of the month in month view
    const year = viewStart.getFullYear();
    const month = viewStart.getMonth() + 1; // 1..12
    refreshMonthColors(year, month).catch(err => console.error(err));
  }

  // Pull month summary and paint background events
  async function refreshMonthColors(year, month) {
    const res = await postGym({ action: 'get_month_summary', year, month });
    if (res.server_now) alignServerNow(res.server_now);

    const TOTAL_SLOTS = Number(res.total_per_day ?? 15); // 7:00–22:00 hourly
    const todayYMD = serverTodayYMD();

    const bgEvents = [];
    for (const d of (res.days || [])) {
      const ymd = d.date;
      const count = Number(d.booked || 0);

      // Skip past dates (CSS keeps them gray + unclickable)
      if (ymd < todayYMD) continue;

      let className = '';
      if (count <= 0) className = 'bg-day-available';          // BLUE
      else if (count >= TOTAL_SLOTS) className = 'bg-day-full'; // RED
      else className = 'bg-day-limited';                        // AMBER

      bgEvents.push({
        start: ymd,
        allDay: true,
        display: 'background',
        classNames: [className]
      });
    }

    // Replace old background event source
    if (dayStatesSource) {
      try { await dayStatesSource.remove(); } catch(_) {}
      dayStatesSource = null;
    }
    dayStatesSource = calendar.addEventSource(bgEvents);
  }

  // --- STRICT: block clicking past dates (server clock, Manila TZ)
  function onDateClick(info) {
    const todayYMD = serverTodayYMD(); // e.g., "2025-10-23"
    // info.dateStr is "YYYY-MM-DD" (FC uses calendar timeZone), so string-compare is safe
    if (info.dateStr < todayYMD) {
      // Ignore clicks on past dates
      return;
    }

    selectedDate = info.dateStr;
    document.getElementById('selectedDateHeader').textContent =
      new Date(selectedDate + 'T00:00:00+08:00').toLocaleDateString('en-US',{
        timeZone:'Asia/Manila',
        weekday:'long',year:'numeric',month:'long',day:'numeric'
      });
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

  // Keep selected slot rates in sync with latest RATES
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

      // adopt live rates from server
      if (res.rates && typeof res.rates.morning === 'number' && typeof res.rates.evening === 'number') {
        RATES.MORNING = res.rates.morning;
        RATES.EVENING = res.rates.evening;
        renderCurrentRates();
        refreshSelectedRates();
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
      if (!res.rates) renderCurrentRates();
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger"><i class="fa fa-triangle-exclamation me-2"></i>Failed to load slots.</div>`;
      console.error(e);
    }
  }

  function renderCurrentRates() {
    const el = document.getElementById('currentRates');
    if (!el) return;
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
          rate: hour < 17 ? RATES.MORNING : RATES.EVENING,
          rateType: hour < 17 ? 'morning' : 'evening'
        });
      }
    });
    updateSummary();
    document.getElementById('proceedToReservation').disabled = selectedSlots.length === 0;
  }

  document.getElementById('proceedToReservation').addEventListener('click', ()=>{
    const dateObj = new Date(selectedDate + 'T00:00:00+08:00');
    document.getElementById('reservationDateDisplay').textContent =
      dateObj.toLocaleDateString('en-US',{ timeZone:'Asia/Manila', weekday:'long',year:'numeric',month:'long',day:'numeric' });
    document.getElementById('reservationTimesDisplay').innerHTML =
      selectedSlots.sort((a,b)=>a.hour-b.hour).map(s=>`<li>${s.time} <small class="text-muted">(₱${s.rate}/hr)</small></li>`).join('');
    document.getElementById('reservationTotalDisplay').textContent = `₱${selectedSlots.reduce((t,s)=>t+s.rate,0)}`;

    document.getElementById('reservationDate').value = selectedDate;
    document.getElementById('selectedSlots').value = JSON.stringify(selectedSlots);

    const ref = generateRef();
    document.getElementById('referenceNumberDisplay').textContent = ref;
    document.getElementById('referenceNumber').value = ref;

    // Admin: leave name/contact blank + editable
    reservationModal.show();
    timeSlotModal.hide();
  });

  document.getElementById('submitReservation').addEventListener('click', async ()=>{
    const form = document.getElementById('reservationForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    // honeypot (anti-bot)
    const honey = (document.getElementById('website').value || '').trim();
    if (honey) { alert('Submission blocked.'); return; }

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

      // update simple print area
      document.getElementById('p_ref').textContent = res.reference || payload.reference;
      document.getElementById('p_name').textContent = residentName || '';
      document.getElementById('p_total').textContent = String(res.total ?? selectedSlots.reduce((t,s)=>t+s.rate,0));

      reservationModal.hide();
      confirmationModal.show();
      form.reset();
      await loadTimeSlots(selectedDate);

      // Also refresh month colors after booking
      const currentStart = calendar.view.currentStart;
      await refreshMonthColors(currentStart.getFullYear(), currentStart.getMonth()+1);
    } catch (e) {
      console.error(e);
      alert(e.message || 'Failed to submit reservation. Please try again.');
    }
  });

  document.getElementById('printReservationBtn')?.addEventListener('click', () => window.print());

  initCalendar();
  setupQuickSelect();
});
