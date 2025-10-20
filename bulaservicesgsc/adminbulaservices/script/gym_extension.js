// ===== DOM =====
const extSearch      = document.getElementById('extSearch');
const extBtn         = document.getElementById('extSearchBtn');
const extList        = document.getElementById('extList');     // <tbody>
const extMeta        = document.getElementById('extMeta');     // summary
const extSlots       = document.getElementById('extSlots');    // chips container
const btnPlus1h      = document.getElementById('btnPlus1h');
const btnPlus2h      = document.getElementById('btnPlus2h');
const btnCreateExt   = document.getElementById('btnCreateExt');
const quoteEl        = document.getElementById('extQuote');

let selected = null;
let addHours = 1;
let lastList = [];
let tickTimer = null;   // per-second UI timer
let pollTimer = null;   // server refresh

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function setText(el, txt){ if(el) el.textContent = txt ?? ''; }
function clear(el){ if(!el) return; while(el.firstChild) el.removeChild(el.firstChild); }
function busy(btn, on){ if(!btn) return; btn.disabled=!!on; btn.classList.toggle('is-loading',!!on); }

function renderTimeline(timeline){
  clear(extSlots);
  if(!Array.isArray(timeline) || !timeline.length){
    const s=document.createElement('span'); s.className='slot empty'; s.textContent='No timeline'; extSlots.appendChild(s); return;
  }
  timeline.forEach(s=>{
    const tag=document.createElement('span');
    tag.className='slot '+(s.busy?'busy':'free');
    tag.textContent=s.label;
    extSlots.appendChild(tag);
  });
}

function updateQuote() {
  if (!quoteEl) return;
  if (!selected) { setText(quoteEl, ''); return; }

  // Start charging from the session's current effective_end (server provided)
  const start = new Date((selected.effective_end || selected.end_time || '').replace(' ','T'));
  if (isNaN(start.getTime())) { setText(quoteEl, ''); return; }

  let total = 0;
  const rateDay   = Number(selected.rate_day   ?? 200);
  const rateNight = Number(selected.rate_night ?? 300);

  const cursor = new Date(start);
  for (let i = 0; i < addHours; i++) {
    const h = cursor.getHours();
    total += (h >= 7 && h < 17) ? rateDay : rateNight;
    cursor.setHours(h + 1);
  }

  setText(quoteEl, `Extension: +${addHours} hour(s) • ${peso.format(total)} (Day ${peso.format(rateDay)}/hr, Night ${peso.format(rateNight)}/hr)`);
}

function markSelectedRow(tr) {
  extList?.querySelectorAll('tr').forEach(r => r.classList.remove('row-selected'));
  tr?.classList.add('row-selected');
}

function selectReservationById(id) {
  const data = lastList.find(x=>Number(x.id)===Number(id));
  if(!data) return;
  selected = data;

  setText(extMeta, `${data.customer || '—'} • ${data.date || ''} • ${data.code || ''}`);
  renderTimeline(data.timeline || []);
  addHours = 1;
  updateQuote();

  // Enable/disable create based on status
  const canExtend = data.status === 'ongoing';
  if (btnCreateExt) btnCreateExt.disabled = !canExtend;
}

function formatSeconds(s) {
  if (s == null) return '—';
  if (s < 0) s = 0;
  const hh = Math.floor(s / 3600);
  const mm = Math.floor((s % 3600) / 60);
  const ss = Math.floor(s % 60);
  const parts = [];
  if (hh > 0) parts.push(String(hh).padStart(2,'0'));
  parts.push(String(mm).padStart(2,'0'));
  parts.push(String(ss).padStart(2,'0'));
  return parts.join(':');
}

function startTicking() {
  if (tickTimer) clearInterval(tickTimer);
  tickTimer = setInterval(() => {
    // decrement visible timers
    extList?.querySelectorAll('tr').forEach(tr => {
      const idx = Number(tr.dataset.idx ?? -1);
      if (idx < 0 || !lastList[idx]) return;
      const it = lastList[idx];
      const timerCell = tr.querySelector('td[data-role="timer"]');
      const statusCell = tr.querySelector('td[data-role="status"]');

      if (it.status === 'upcoming' && typeof it.seconds_to_start === 'number') {
        it.seconds_to_start -= 1;
        if (it.seconds_to_start <= 0) {
          // flip locally to ongoing; real truth will come from next poll
          it.status = 'ongoing';
          it.seconds_to_end = (new Date(it.effective_end.replace(' ','T')) - new Date()) / 1000;
          statusCell && (statusCell.innerHTML = `<span class="badge ongoing">Ongoing</span>`);
        }
        timerCell && (timerCell.textContent = it.status === 'upcoming'
          ? `Starts in ${formatSeconds(it.seconds_to_start)}`
          : `Time left ${formatSeconds(Math.max(0, Math.floor(it.seconds_to_end||0)))}`);
      } else if (it.status === 'ongoing' && typeof it.seconds_to_end === 'number') {
        it.seconds_to_end -= 1;
        timerCell && (timerCell.textContent = `Time left ${formatSeconds(it.seconds_to_end)}`);
      }
    });
  }, 1000);
}

function startPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(() => loadSessions(false), 10000); // refresh from server every 10s
}

async function loadSessions(showBusy = true){
  if(!extList) return;

  if (showBusy) extList.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:10px;">Loading…</td></tr>`;
  busy(extBtn,true);

  try{
    const q = (extSearch?.value || '').trim();
    const url = `./php/extension_list.php?q=${encodeURIComponent(q)}`;
    const res = await fetch(url, { credentials:'same-origin', cache:'no-store' });
    const data = await res.json();
    if(!data.success) throw new Error(data.message||'Load failed');

    const list = Array.isArray(data.items)?data.items:[];
    lastList = list;
    selected = null; setText(extMeta,''); renderTimeline([]); updateQuote();
    if (btnCreateExt) btnCreateExt.disabled = true;

    if(!list.length){
      extList.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#666;padding:10px;">No upcoming or ongoing sessions</td></tr>`;
      return;
    }

    // Render rows — whole row clickable
    extList.innerHTML='';
    list.forEach((it, i)=>{
      const tr=document.createElement('tr');
      tr.dataset.id = String(it.id);
      tr.dataset.idx = String(i);
      tr.classList.add('row-clickable');
      tr.tabIndex = 0;           // keyboard focusable
      tr.setAttribute('role','button');
      tr.style.cursor = 'pointer';

      const statusBadge = it.status === 'ongoing'
        ? `<span class="badge ongoing">Ongoing</span>`
        : `<span class="badge upcoming">Upcoming</span>`;

      const timerText = it.status === 'ongoing'
        ? `Time left ${formatSeconds(Math.floor(it.seconds_to_end||0))}`
        : `Starts in ${formatSeconds(Math.floor(it.seconds_to_start||0))}`;

      tr.innerHTML=`
        <td>${it.code || ''}</td>
        <td>${it.customer || ''}</td>
        <td>${it.times || ''}</td>
        <td data-role="status">${statusBadge}</td>
        <td data-role="timer">${timerText}</td>
        <td><span class="muted">${it.date || ''}</span></td>
      `;

      tr.addEventListener('click', () => { selectReservationById(it.id); markSelectedRow(tr); });
      tr.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectReservationById(it.id); markSelectedRow(tr); }
      });

      extList.appendChild(tr);
    });

    // (re)start ticking
    startTicking();
    startPolling();

  }catch(err){
    console.error(err);
    extList.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#c00;padding:10px;">Error loading</td></tr>`;
  }finally{
    busy(extBtn,false);
  }
}

async function createExtension(){
  if(!selected){
    if(window.Swal) Swal.fire({icon:'warning',title:'Pick a session',text:'Select a session first.'});
    else alert('Select a session first.');
    return;
  }
  if(selected.status !== 'ongoing'){
    if(window.Swal) Swal.fire({icon:'info',title:'Not started yet',text:'You can only extend when the session is ongoing.'});
    else alert('You can only extend when the session is ongoing.');
    return;
  }

  busy(btnCreateExt,true);
  try{
    const res = await fetch('./php/extension_create.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ base_id: selected.id, hours: addHours })
    });
    const data = await res.json();
    if(!data.success) throw new Error(data.message||'Failed to create extension');

    if(window.Swal){
      await Swal.fire({ icon:'success', title:'Extension created',
        html:`Ref: <b>${data.code}</b><br>Amount: ${peso.format(Number(data.amount||0))}<br><br>Go to <b>Cashier</b> to collect payment.`});
    }else{
      alert(`Extension created\nRef: ${data.code}\nAmount: ${peso.format(Number(data.amount||0))}`);
    }

    await loadSessions(false); // refresh list/timelines quickly

  }catch(err){
    console.error(err);
    if(window.Swal) Swal.fire({icon:'error',title:'Error',text:err.message});
    else alert(err.message);
  }finally{
    busy(btnCreateExt,false);
  }
}

// Boot
document.addEventListener('DOMContentLoaded', ()=>{
  loadSessions(true);
});
extBtn?.addEventListener('click', ()=>loadSessions(true));
extSearch?.addEventListener('keydown', e=>{ if(e.key==='Enter') loadSessions(true); });
btnPlus1h?.addEventListener('click', ()=>{ addHours=1; updateQuote(); });
btnPlus2h?.addEventListener('click', ()=>{ addHours=2; updateQuote(); });
btnCreateExt?.addEventListener('click', createExtension);

// Optional global if your HTML uses inline handlers
window.gym_extension = { reload: ()=>loadSessions(true) };
