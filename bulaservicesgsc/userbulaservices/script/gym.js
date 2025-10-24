// script/gym.js — solid color mapping, today clickable, past disabled
document.addEventListener('DOMContentLoaded', () => {
  const timeSlotModal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
  const reservationModal = new bootstrap.Modal(document.getElementById('reservationFormModal'));
  const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));

  const USER_NAME = (window.USER_FULL_NAME || '').trim();
  const USER_CONTACT = (window.USER_CONTACT || '').trim();

  let SERVER_NOW_BASE_ISO = window.SERVER_NOW_ISO || null;
  const CLIENT_ANCHOR_MS = Date.now();
  let SERVER_ANCHOR_MS = SERVER_NOW_BASE_ISO ? Date.parse(SERVER_NOW_BASE_ISO) : Date.now();

  function alignServerNow(serverIso) {
    if (serverIso) { SERVER_NOW_BASE_ISO = serverIso; SERVER_ANCHOR_MS = Date.parse(serverIso); }
  }
  function serverNowMs() { return (SERVER_ANCHOR_MS + (Date.now() - CLIENT_ANCHOR_MS)); }
  function serverNowParts() { const d = new Date(serverNowMs()); return { y:d.getFullYear(), m:d.getMonth()+1, d:d.getDate(), hour:d.getHours() }; }
  function isSameYMD(dateStr, parts) { const [Y,M,D] = dateStr.split('-').map(n=>parseInt(n,10)); return Y===parts.y && M===parts.m && D===parts.d; }

  const RATES = { MORNING: 200, EVENING: 300 };
  let monthSummaryCache = {};

  const GYM_API = './php/Gymback.php';
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

  function setDayColorBackgrounds(items) {
    const prev = calendar.getEventSources().find(s => s.internalTag === 'dayColors');
    if (prev) prev.remove();
    const events = items.map(it => ({
      start: it.date,
      end: new Date(new Date(it.date).getTime() + 86400000).toISOString().slice(0,10),
      display: 'background',
      classNames: [it.className],
      allDay: true
    }));
    const src = calendar.addEventSource(events);
    src.internalTag = 'dayColors';
  }

  function classifyDay(bookedCount, totalPerDay) {
    // mapping is handled in CSS: available=BLUE, limited=AMBER, full=RED
    if (bookedCount <= 0) return 'bg-day-available';
    if (bookedCount >= totalPerDay) return 'bg-day-full';
    return 'bg-day-limited';
  }

  function ymd(dateObj) {
    const y = dateObj.getFullYear();
    const m = (dateObj.getMonth()+1).toString().padStart(2,'0');
    const d = dateObj.getDate().toString().padStart(2,'0');
    return `${y}-${m}-${d}`;
  }

  async function loadMonthSummary() {
    const vis = calendar.view.currentStart;
    const y = vis.getFullYear();
    const m = vis.getMonth() + 1;
    const key = `${y}-${String(m).padStart(2,'0')}`;

    if (monthSummaryCache[key]) return monthSummaryCache[key];

    const res = await postGym({ action: 'get_month_summary', year: y, month: m });

    if (res.server_now) alignServerNow(res.server_now);
    if (res.rates && typeof res.rates.morning === 'number' && typeof res.rates.evening === 'number') {
      RATES.MORNING = res.rates.morning;
      RATES.EVENING = res.rates.evening;
    }

    const totalPerDay = res.total_per_day || 15;
    const map = {};
    (res.days || []).forEach(d => { map[d.date] = { booked: d.booked }; });

    const summary = { daysMap: map, totalPerDay };
    monthSummaryCache[key] = summary;
    return summary;
  }

  function markPastDays() {
    const parts = serverNowParts();
    const todayStr = `${parts.y}-${String(parts.m).padStart(2,'0')}-${String(parts.d).padStart(2,'0')}`;
    document.querySelectorAll('.fc-daygrid-day').forEach(cell => {
      const cellDate = cell.getAttribute('data-date');
      if (!cellDate) return;
      if (cellDate < todayStr) cell.classList.add('fc-day-past'); else cell.classList.remove('fc-day-past');
    });
  }

  function initCalendar() {
    const el = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
      height: 'auto',
      dateClick: onDateClick,
      datesSet: async () => {
        try {
          const summary = await loadMonthSummary();
          const parts = serverNowParts();
          const todayStr = `${parts.y}-${String(parts.m).padStart(2,'0')}-${String(parts.d).padStart(2,'0')}`;

          const items = [];
          Object.keys(summary.daysMap).forEach(d => {
            if (d < todayStr) return; // past is gray only
            const booked = summary.daysMap[d].booked || 0;
            const className = classifyDay(booked, summary.totalPerDay);
            items.push({ date: d, className });
          });

          setDayColorBackgrounds(items);
          setTimeout(markPastDays, 0);
        } catch (e) {
          console.error('month summary error:', e);
        }
      }
    });
    calendar.render();
  }

  function onDateClick(info) {
    const clickedYMD = ymd(info.date);
    const parts = serverNowParts();
    const todayStr = `${parts.y}-${String(parts.m).padStart(2,'0')}-${String(parts.d).padStart(2,'0')}`;
    if (clickedYMD < todayStr) return; // past disabled
    selectedDate = clickedYMD;
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

  async function loadTimeSlots(date) {
    const container = document.getElementById('time-slots-container');
    const quickWrap = document.getElementById('quickSelectWrap');
    const noSlotsMsg = document.getElementById('noSlotsMsg');

    if (container) container.innerHTML = '';
    selectedSlots = [];
    const summaryEl = document.getElementById('selectedSlotsSummary');
    if (summaryEl) summaryEl.style.display = 'none';
    const proceedBtn = document.getElementById('proceedToReservation');
    if (proceedBtn) proceedBtn.disabled = true;
    if (noSlotsMsg) noSlotsMsg.classList.add('d-none');
    if (quickWrap) quickWrap.classList.remove('d-none');

    try {
      const res = await postGym({ action: 'get_slots', date });

      if (res.rates && typeof res.rates.morning === 'number' && typeof res.rates.evening === 'number') {
        RATES.MORNING = res.rates.morning;
        RATES.EVENING = res.rates.evening;
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

      const selectable = slots.filter(s => !s.booked && !s.past);
      if (selectable.length === 0) {
        if (noSlotsMsg) noSlotsMsg.classList.remove('d-none');
        if (quickWrap) quickWrap.classList.add('d-none');
      }

      if (container) slots.forEach(slot => renderSlot(container, slot));
      renderCurrentRates();

      // refresh month summary next time
      const vis = calendar.view.currentStart;
      const key = `${vis.getFullYear()}-${String(vis.getMonth()+1).padStart(2,'0')}`;
      if (monthSummaryCache[key]) delete monthSummaryCache[key];

    } catch (e) {
      if (container) {
        container.innerHTML = `<div class="alert alert-danger"><i class="fa fa-triangle-exclamation me-2"></i>Failed to load slots.</div>`;
      }
      console.error(e);
    }
  }

  function renderCurrentRates() {
    const el = document.getElementById('currentRates');
    if (!el) return;
    el.textContent = `Current Rates — Morning: ₱${RATES.MORNING} / Evening: ₱${RATES.EVENING}`;
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
    updateSummary();
    const proceedBtn = document.getElementById('proceedToReservation');
    if (proceedBtn) proceedBtn.disabled = selectedSlots.length === 0;
    activeQuickSelect = null;
    updateQuickButtons();
  }

  function updateSummary() {
    const wrap = document.getElementById('selectedSlotsSummary');
    const list = document.getElementById('selectedSlotsList');
    const totalEl = document.getElementById('estimatedTotal');
    const breakdownEl = document.getElementById('rateBreakdown');

    if (selectedSlots.length === 0) {
      if (wrap) wrap.style.display = 'none';
      if (totalEl) totalEl.textContent = '₱0';
      if (breakdownEl) breakdownEl.textContent = '';
      return;
    }

    if (wrap) wrap.style.display = 'block';
    if (list) {
      list.innerHTML = selectedSlots
        .sort((a,b)=>a.hour-b.hour)
        .map(s => `• ${s.time} <small class="text-muted">(₱${s.rate}/hr)</small>`)
        .join('<br/>');
    }

    const morning = selectedSlots.filter(s => s.rateType === 'morning').length;
    const evening = selectedSlots.filter(s => s.rateType === 'evening').length;
    const total = selectedSlots.reduce((t, s) => t + s.rate, 0);
    if (totalEl) totalEl.textContent = `₱${total}`;
    if (breakdownEl) breakdownEl.textContent = [
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
        if (activeQuickSelect === id) {
          clearSelection(); activeQuickSelect = null;
        } else {
          selectRange(map[id].start, map[id].end); activeQuickSelect = id;
        }
        updateQuickButtons();
      });
    });
    const clearBtn = document.getElementById('clearSelectionBtn');
    if (clearBtn) clearBtn.addEventListener('click', ()=>{
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
    const proceedBtn = document.getElementById('proceedToReservation');
    if (proceedBtn) proceedBtn.disabled = true;
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
          rate: parseInt(card.dataset.rate,10),
          rateType: hour < 17 ? 'morning' : 'evening'
        });
      }
    });
    updateSummary();
    const proceedBtn = document.getElementById('proceedToReservation');
    if (proceedBtn) proceedBtn.disabled = selectedSlots.length === 0;
  }

  document.getElementById('proceedToReservation')?.addEventListener('click', ()=>{
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
    if (nameEl) { nameEl.value = USER_NAME; nameEl.readOnly = true; nameEl.setAttribute('aria-readonly','true'); }
    if (contactEl) { contactEl.value = USER_CONTACT; contactEl.readOnly = true; contactEl.setAttribute('aria-readonly','true'); }

    timeSlotModal.hide();
    reservationModal.show();
  });

  document.getElementById('submitReservation')?.addEventListener('click', async ()=>{
    const form = document.getElementById('reservationForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const payload = {
      action: 'create_reservation',
      date: document.getElementById('reservationDate').value,
      slots: JSON.parse(document.getElementById('selectedSlots').value),
      resident: USER_NAME,
      contact: USER_CONTACT,
      activity: document.getElementById('activityType').value,
      notes: document.getElementById('reservationNotes').value,
      reference: document.getElementById('referenceNumber').value
    };

    try {
      const res = await postGym(payload);
      document.getElementById('confirmedReference').textContent = res.reference || payload.reference;
      document.getElementById('confirmedAmount').textContent = String(res.total ?? selectedSlots.reduce((t,s)=>t+s.rate,0));
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
