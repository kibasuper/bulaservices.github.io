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
    q, gender, residency, status, page:String(page), per_page:String(perPage)
  });
  const url = `./php/resident_list.php?${params.toString()}`;

  tbody.innerHTML = `<tr><td colspan="8" class="center muted">Loading…</td></tr>`;

  try {
    const data = await apiGet(url);
    const rows = Array.isArray(data.items) ? data.items : [];
    lastPageMeta = data.meta || { total: rows.length, hasPrev:false, hasNext:false };

    totalCountEl.textContent = String(lastPageMeta.total ?? rows.length);
    pageInfo.textContent = `Page ${page}`;
    prevPageBtn.disabled = !lastPageMeta.hasPrev;
    nextPageBtn.disabled = !lastPageMeta.hasNext;

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="center muted">No residents found</td></tr>`;
      return;
    }

    tbody.innerHTML = '';
    rows.forEach((r) => {
      const full = (r.full_name || `${r.first_name || ''} ${r.last_name || ''}`.trim()) || '—';
      const prof = r.profile_picture_url || './pics/profile-placeholder.jpg';
      const genderDisp = r.gender ? (r.gender.charAt(0).toUpperCase()+r.gender.slice(1)) : '—';
      const residencyDisp = r.resident_type ? (r.resident_type.charAt(0).toUpperCase()+r.resident_type.slice(1)) : '—';
      const status = (r.account_status || 'active').toLowerCase();
      const statusHtml = `<span class="status-badge ${status === 'suspended' ? 'suspended' : 'active'}">${status === 'suspended' ? 'Suspended' : 'Active'}</span>`;

      const tr = document.createElement('tr');
      tr.dataset.id = String(r.id);
      tr.innerHTML = `
        <td>
          <div class="profile-mini"><img src="${escapeHtml(prof)}" alt=""></div>
        </td>
        <td>${escapeHtml(full)}</td>
        <td>${escapeHtml(genderDisp)}</td>
        <td>${fmtDate(r.date_of_birth)}</td>
        <td>${escapeHtml(residencyDisp)}</td>
        <td>${statusHtml}</td>
        <td>${escapeHtml(r.contact_number || '')}</td>
        <td>${escapeHtml(r.address || '')}</td>
      `;
      // row click opens view
      tr.addEventListener('click', () => openView(r.id));
      tbody.appendChild(tr);
    });

  } catch (e) {
    console.error(e);
    tbody.innerHTML = `<tr><td colspan="8" class="center" style="color:#c00">${escapeHtml(e.message || 'Load failed')}</td></tr>`;
  }
}

// ====== View Modal ======
async function openView(id) {
  try {
    const data = await apiGet(`./php/resident_get.php?id=${encodeURIComponent(id)}`);
    const r = data.item || {};
    currentResidentId = r.id;

    const full = (r.full_name || `${r.first_name || ''} ${r.last_name || ''}`.trim()) || '—';
    const prof = r.profile_picture_url || './pics/profile-placeholder.jpg';
    const status = (r.account_status || 'active').toLowerCase();
    currentResidentStatus = status;

    document.getElementById('vProfile').src = prof;
    document.getElementById('vName').textContent = full;
    document.getElementById('vEmail').textContent = r.email || '—';

    document.getElementById('vGender').textContent = (r.gender || '—');
    document.getElementById('vDob').textContent = fmtDate(r.date_of_birth);
    document.getElementById('vResidency').textContent = (r.resident_type || '—');
    document.getElementById('vContact').textContent = r.contact_number || '—';
    document.getElementById('vAddress').textContent = r.address || '—';
    document.getElementById('vCreated').textContent = fmtDate(r.created_at);
    document.getElementById('vStatus').textContent = status === 'suspended' ? 'Suspended' : 'Active';

    // toggle button text
    btnToggleStatus.textContent = status === 'suspended' ? 'Restore Account' : 'Suspend Account';
    btnToggleStatus.className = 'btn ' + (status === 'suspended' ? 'primary' : 'outline');

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
  const action = currentResidentStatus === 'suspended' ? 'restore' : 'suspend';
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
document.getElementById('btnSaveResident')?.remove(); // safeguard if old DOM lingers

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
