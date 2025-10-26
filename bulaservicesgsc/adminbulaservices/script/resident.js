// ====== DOM ======
const tbody = document.getElementById('residentsTbody');
const searchBox = document.getElementById('searchBox');
const filterGender = document.getElementById('filterGender');
const filterResidency = document.getElementById('filterResidency');
const filterStatus = document.getElementById('filterStatus');
const totalCountEl = document.getElementById('totalCount');
const pageInfo = document.getElementById('pageInfo');
const prevPageBtn = document.getElementById('prevPage');
const nextPageBtn = document.getElementById('nextPage');
const btnRefresh = document.getElementById('btnRefresh');
const btnAddResident = document.getElementById('btnAddResident');

const viewModal = document.getElementById('viewModal');
const btnToggleStatus = document.getElementById('btnToggleStatus');

// ====== State ======
let page = 1;
let perPage = 20;
let lastPageMeta = { total:0, hasPrev:false, hasNext:false };
let currentResidentId = null;
let currentResidentStatus = 'active';

// ====== Utils ======
const fmtDate = s => {
  if (!s) return '—';
  const d = new Date(s);
  return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
};
const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const toTitle = s => (s ? (s.charAt(0).toUpperCase()+s.slice(1)) : '—');

function computeAge(ymd) {
  if (!ymd) return '—';
  const [y, m, d] = (ymd || '').split('-').map(n=>parseInt(n,10));
  if (!y || !m || !d) return '—';
  const now = new Date();
  let age = now.getFullYear() - y;
  const hadBday = (now.getMonth()+1 > m) || ((now.getMonth()+1 === m) && (now.getDate() >= d));
  if (!hadBday) age -= 1;
  return age >= 0 ? String(age) : '—';
}
// ====== Helpers ======
function resolveProfileUrl(p) {
  if (!p) return './images/profile-placeholder.jpg';

  // If API gives us an admin-serve URL or absolute URL, use as-is.
  // Do NOT rewrite to /uploads anymore.
  return String(p).trim();
}


// ====== API ======
async function apiGet(url) {
  const res = await fetch(url, { credentials:'same-origin', cache:'no-store' });
  const text = await res.text();
  let json; try { json = JSON.parse(text); } catch { throw new Error(text || 'Bad JSON'); }
  if (!res.ok || json.success === false) throw new Error(json.message || `HTTP ${res.status}`);
  return json;
}
async function apiPost(url, payload) {
  const res = await fetch(url, {
    method:'POST',
    headers:{ 'Content-Type':'application/json' },
    credentials:'same-origin',
    body: JSON.stringify(payload)
  });
  const text = await res.text();
  let json; try { json = JSON.parse(text); } catch { throw new Error(text || 'Bad JSON'); }
  if (!res.ok || json.success === false) throw new Error(json.message || `HTTP ${res.status}`);
  return json;
}

// ====== List ======
async function loadResidents(resetPage=false) {
  if (resetPage) page = 1;

  const q = (searchBox?.value || '').trim();
  const gender = filterGender?.value || 'all';
  const residency = filterResidency?.value || 'all';
  const status = filterStatus?.value || 'all';

  const params = new URLSearchParams({
    q, gender, residency, status,
    page:String(page), per_page:String(perPage)
  });
  const url = `./php/resident_list.php?${params.toString()}`;

  // Keep colspan in sync with <thead> column count
  tbody.innerHTML = `<tr><td colspan="6" class="center muted">Loading…</td></tr>`;

  try {
    const data = await apiGet(url);
    const rows = Array.isArray(data.items) ? data.items : [];
    lastPageMeta = data.meta || { total: rows.length, hasPrev:false, hasNext:false };

    totalCountEl.textContent = String(lastPageMeta.total ?? rows.length);
    pageInfo.textContent = `Page ${page}`;
    prevPageBtn.disabled = !lastPageMeta.hasPrev;
    nextPageBtn.disabled = !lastPageMeta.hasNext;

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="center muted">No residents found</td></tr>`;
      return;
    }

  tbody.innerHTML = '';
  rows.forEach((r) => {
    const full = (r.full_name || `${r.first_name || ''} ${r.last_name || ''}`.trim()) || '—';
    const genderDisp = r.gender ? toTitle(r.gender) : '—';
    const residencyDisp = toTitle(r.resident_type || r.residency || '—');
    const status = (r.account_status || 'active').toLowerCase();
    const statusHtml = `<span class="status-badge ${status === 'suspended' ? 'suspended' : 'active'}">${status === 'suspended' ? 'Suspended' : 'Active'}</span>`;
    const prof = resolveProfileUrl(r.profile_picture_url);

    const tr = document.createElement('tr');
    tr.dataset.id = String(r.id);
    tr.innerHTML = `
      <td class="avatar">
        <img src="${escapeHtml(prof)}" alt="Profile" onerror="this.src='./images/profile-placeholder.jpg'">
      </td>
      <td class="cell-name">${escapeHtml(full)}</td>
      <td>${escapeHtml(genderDisp)}</td>
      <td>${escapeHtml(residencyDisp)}</td>
      <td>${statusHtml}</td>
      <td class="cell-actions">
        <button class="btn tiny view-btn" type="button">View</button>
      </td>
    `;

    // Row click opens view; don't double fire when clicking the button
    tr.addEventListener('click', (e) => {
      if ((e.target && e.target.closest('.view-btn'))) return;
      openView(r.id);
    });
    tr.querySelector('.view-btn')?.addEventListener('click', () => openView(r.id));

    tbody.appendChild(tr);
  });

  } catch (e) {
    console.error(e);
    tbody.innerHTML = `<tr><td colspan="6" class="center" style="color:#c00">${escapeHtml(e.message || 'Load failed')}</td></tr>`;
  }
}


// ====== View Modal ======
async function openView(id) {
  try {
    const data = await apiGet(`./php/resident_get.php?id=${encodeURIComponent(id)}`);
    const r = data.item || {};
    currentResidentId = r.id;

    const full = (r.full_name || `${r.first_name || ''} ${r.last_name || ''}`.trim()) || '—';
    const prof = r.profile_picture_url || './images/profile-placeholder.jpg';
    const status = (r.account_status || 'active').toLowerCase();
    currentResidentStatus = status;

    document.getElementById('vProfile').src = prof;
    document.getElementById('vName').textContent = full;
    document.getElementById('vEmail').textContent = r.email || '—';

    document.getElementById('vGender').textContent = (r.gender || '—');
    document.getElementById('vAge').textContent = computeAge(r.date_of_birth);
    document.getElementById('vResidency').textContent = toTitle(r.resident_type || '—');
    document.getElementById('vYearStarted').textContent = r.year_started_staying ? String(r.year_started_staying) : '—';
    document.getElementById('vAddress').textContent = r.address || '—';
    document.getElementById('vCreated').textContent = fmtDate(r.created_at);
    document.getElementById('vStatus').textContent = status === 'suspended' ? 'Suspended' : 'Active';

    // Button text + style
    const willActivate = (status === 'suspended');
    btnToggleStatus.textContent = willActivate ? 'Activate' : 'Deactivate';
    btnToggleStatus.className = 'btn ' + (willActivate ? 'primary' : 'outline');

    viewModal.setAttribute('aria-hidden', 'false');
    viewModal.querySelector('.modal-card').focus();
  } catch (e) {
    alert(e.message || 'Failed to load resident');
  }
}
function closeView(){ viewModal.setAttribute('aria-hidden','true'); }
document.querySelectorAll('.close-view').forEach(b=> b.addEventListener('click', closeView));
viewModal.addEventListener('click', (e)=>{ if(e.target===viewModal) closeView(); });

btnToggleStatus?.addEventListener('click', async () => {
  if (!currentResidentId) return;
  const goingActive = (currentResidentStatus === 'suspended');
  const action = goingActive ? 'restore' : 'suspend';

  const ok = window.confirm(goingActive
    ? 'Activate this account? The user will be able to sign in again.'
    : 'Deactivate this account? The user will be signed out and blocked until reactivated.');
  if (!ok) return;

  try {
    await apiPost('./php/resident_status.php', { id: currentResidentId, action });
    closeView();
    loadResidents(false);
  } catch (e) {
    alert(e.message || 'Failed to update status');
  }
});

// ====== Events ======
document.addEventListener('DOMContentLoaded', ()=> loadResidents(true));
document.getElementById('btnSaveResident')?.remove();

btnRefresh?.addEventListener('click', () => loadResidents(false));

// Redirect instead of showing Add modal
btnAddResident?.addEventListener('click', () => {
  window.location.href = 'https://bulaservicesgsc.com/';
});

searchBox?.addEventListener('input', debounce(()=> loadResidents(true), 250));
filterGender?.addEventListener('change', ()=> loadResidents(true));
filterResidency?.addEventListener('change', ()=> loadResidents(true));
filterStatus?.addEventListener('change', ()=> loadResidents(true));

prevPageBtn?.addEventListener('click', ()=>{
  if (!lastPageMeta.hasPrev) return;
  page = Math.max(1, page-1);
  loadResidents(false);
});
nextPageBtn?.addEventListener('click', ()=>{
  if (!lastPageMeta.hasNext) return;
  page = page+1;
  loadResidents(false);
});

// ====== small helpers ======
function debounce(fn, ms=250){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
