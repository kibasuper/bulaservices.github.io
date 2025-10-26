document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.querySelector('#officialsTable tbody');
  const emptyState = document.getElementById('emptyState');

  const btnAdd = document.getElementById('btnAdd');
  const createModal = document.getElementById('createModal');
  const closeCreate = document.getElementById('closeCreate');
  const closeCreate2 = document.getElementById('closeCreate2');
  const detailsModal = document.getElementById('detailsModal');
  const closeDetails = document.getElementById('closeDetails');
  const formCreate = document.getElementById('formCreate');
  const detailsBody = document.getElementById('detailsBody');

  // Filters
  const filterSearch = document.getElementById('filterSearch');
  const filterStatus = document.getElementById('filterStatus');
  const toggleHideSuspended = document.getElementById('toggleHideSuspended');

  // Success modal
  const createdModal = document.getElementById('createdModal');
  const createdBody  = document.getElementById('createdBody');
  const closeCreated = document.getElementById('closeCreated');
  const openCreated = () => { if (createdModal) createdModal.style.display = 'grid'; };
  const closeCreatedModal = () => { if (createdModal) createdModal.style.display = 'none'; };
  if (closeCreated) closeCreated.onclick = closeCreatedModal;
  if (createdModal) createdModal.addEventListener('click', (e)=>{ if (e.target === createdModal) closeCreatedModal(); });

  // Pretty confirm modal
  const confirmModal = document.getElementById('confirmModal');
  const confirmTitle = document.getElementById('confirmTitle');
  const confirmText  = document.getElementById('confirmText');
  const confirmOk    = document.getElementById('confirmOk');
  const confirmCancel= document.getElementById('confirmCancel');

  function niceConfirm({ title, text, danger=false, confirmText='Confirm', cancelText='Cancel' }) {
    return new Promise((resolve) => {
      if (confirmTitle) confirmTitle.textContent = title || 'Confirm action';
      if (confirmText)  confirmText.textContent  = text  || 'Are you sure?';
      if (confirmOk) {
        confirmOk.textContent = confirmText;
        confirmOk.classList.toggle('btn-danger-solid', danger);
        confirmOk.classList.toggle('btn-primary-solid', !danger);
      }
      confirmModal.classList.add('open');

      const onClose = (val) => {
        confirmModal.classList.remove('open');
        confirmOk.onclick = null;
        confirmCancel.onclick = null;
        confirmModal.onclick = null;
        resolve(val);
      };
      confirmOk.onclick = () => onClose(true);
      confirmCancel.onclick = () => onClose(false);
      confirmModal.onclick = (e) => { if (e.target === confirmModal) onClose(false); };
      document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { onClose(false); document.removeEventListener('keydown', esc); }
      });
    });
  }

  // Photo preview (create)
  const createPhotoInput = document.getElementById('createPhoto');
  const createPhotoPreview = document.getElementById('createPhotoPreview');
  if (createPhotoInput && createPhotoPreview) {
    createPhotoInput.addEventListener('change', () => {
      const f = createPhotoInput.files?.[0];
      if (f) createPhotoPreview.src = URL.createObjectURL(f);
      else createPhotoPreview.removeAttribute('src');
    });
  }

  // Birthdate -> auto age
  const birthdateEl = document.getElementById('birthdate');
  const ageEl = document.getElementById('age');
  function computeAgeFromBirthdate(iso) {
    if (!iso) return '';
    try {
      const [y, m, d] = iso.split('-').map(n=>parseInt(n,10));
      if (!y || !m || !d) return '';
      const now = new Date();
      let age = now.getFullYear() - y;
      const hadBday = (now.getMonth()+1 > m) || ((now.getMonth()+1 === m) && (now.getDate() >= d));
      if (!hadBday) age -= 1;
      return age < 0 ? '' : String(age);
    } catch { return ''; }
  }
  if (birthdateEl && ageEl) {
    birthdateEl.addEventListener('change', () => {
      ageEl.value = computeAgeFromBirthdate(birthdateEl.value);
    });
  }

  // Religion "Other" toggle
  const religionSel = document.getElementById('religion');
  const religionOtherWrap = document.getElementById('religionOtherWrap');
  const religionOther = document.getElementById('religion_other');
  if (religionSel && religionOtherWrap) {
    religionSel.addEventListener('change', () => {
      const isOther = religionSel.value === 'Other';
      religionOtherWrap.style.display = isOther ? '' : 'none';
      if (!isOther && religionOther) religionOther.value = '';
    });
  }

  // Phone input: digits only, max 11
  const phoneEl = document.getElementById('contact_number');
  if (phoneEl) {
    phoneEl.addEventListener('input', () => {
      const digits = phoneEl.value.replace(/\D+/g, '').slice(0, 11);
      phoneEl.value = digits;
    });
    phoneEl.addEventListener('blur', () => {
      const v = phoneEl.value;
      if (v && (!/^0\d{10}$/.test(v))) {
        alert('Contact number must be exactly 11 digits and start with 0 (e.g., 09XXXXXXXXX).');
        phoneEl.focus();
      }
    });
  }

  /* API helper */
  const api = async (action, data=null, method='POST') => {
    const opts = { method };
    let url = `./php/officials_api.php?action=${encodeURIComponent(action)}`;
    if (method === 'GET') {
      if (data) url += `&${new URLSearchParams(data).toString()}`;
    } else if (data) {
      opts.body = data;
    }
    const res = await fetch(url, opts);
    if (!res.ok) {
      const t = await res.text();
      console.error(`API ${action} error`, t);
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  };

  /* Build current filter params */
  function currentListParams() {
    const q = (filterSearch?.value || '').trim();
    let status = (filterStatus?.value || 'all');
    const hideSusp = !!(toggleHideSuspended && toggleHideSuspended.checked);

    if (hideSusp && status === 'all') status = 'active';

    const params = {};
    if (q) params.q = q;
    if (status && status !== 'all') params.status = status; // 'active' | 'suspended'
    if (hideSusp) params.hide_suspended = '1';
    return params;
  }

  /* Load list */
  async function loadList() {
    const params = currentListParams();
    const { data } = await api('list', params, 'GET');
    const items = data.items || [];

    tableBody.innerHTML = '';
    if (!items.length) {
      if (emptyState) emptyState.style.display = '';
      return;
    }
    if (emptyState) emptyState.style.display = 'none';

    items.forEach(o => {
      const tr = document.createElement('tr');
      tr.dataset.id = o.admin_id;
      tr.innerHTML = `
        <td>${(o.first_name||'') + ' ' + (o.last_name||'')}</td>
        <td>@${o.username}</td>
        <td>${o.last_login || '—'}</td>
        <td><span class="badge ${o.is_active ? 'active':'suspended'}">${o.is_active ? 'Active' : 'Suspended'}</span></td>
      `;
      tableBody.appendChild(tr);
    });
  }

  /* Create modal open/close */
  const openCreate = () => { createModal.classList.add('open'); };
  const closeCreateModal = () => {
    createModal.classList.remove('open');
    if (formCreate) formCreate.reset();
    if (createPhotoPreview) createPhotoPreview.removeAttribute('src');
    if (religionOtherWrap) religionOtherWrap.style.display = 'none';
  };
  btnAdd.onclick = openCreate;
  closeCreate.onclick = closeCreateModal;
  if (closeCreate2) closeCreate2.onclick = closeCreateModal;
  createModal.addEventListener('click', (e) => { if (e.target === createModal) closeCreateModal(); });

  /* Create submit */
  formCreate.onsubmit = async (e) => {
    e.preventDefault();
    if (birthdateEl && ageEl && birthdateEl.value && !ageEl.value) {
      ageEl.value = computeAgeFromBirthdate(birthdateEl.value);
    }
    if (phoneEl && phoneEl.value && !/^0\d{10}$/.test(phoneEl.value)) {
      alert('Contact number must be exactly 11 digits and start with 0 (e.g., 09XXXXXXXXX).');
      phoneEl.focus();
      return;
    }

    const fd = new FormData(formCreate); // includes photo if chosen
    try {
      const resp = await api('create', fd);
      const d = resp.data || {};

      // Success modal
      if (createdBody) {
        createdBody.innerHTML = `
          <div style="font-size:14px;color:#0f172a;line-height:1.6">
            <p><strong>Name:</strong> ${(fd.get('first_name')||'')} ${(fd.get('last_name')||'')}</p>
            <p><strong>Username:</strong> ${d.username || fd.get('username') || ''}</p>
            ${ (d.email || fd.get('email')) ? `<p><strong>Email:</strong> ${d.email || fd.get('email') || ''}</p>` : '' }
            <p style="margin-top:8px"><strong>Default Password:</strong>
              <span id="pwText" style="font-family:ui-monospace, SFMono-Regular, Menlo, monospace; background:#f8fafc; border:1px solid #e5e7eb; padding:4px 8px; border-radius:8px;">
                ${d.default_password || 'Bula@2025'}
              </span>
              <button id="copyPw" class="btn" style="margin-left:8px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer">Copy</button>
            </p>
            ${ (d.email || fd.get('email')) ? `<p class="muted" style="margin-top:8px">The account details have been emailed to the user.</p>` : `<p class="muted" style="margin-top:8px">No email was provided; please share credentials manually.</p>` }
          </div>
          <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
            <button class="btn" id="createdCloseBtn">Close</button>
          </div>
        `;
        openCreated();
        const copyBtn = document.getElementById('copyPw');
        const pwText  = document.getElementById('pwText');
        const createdCloseBtn = document.getElementById('createdCloseBtn');
        if (copyBtn && pwText) {
          copyBtn.onclick = async () => {
            try { await navigator.clipboard.writeText(pwText.textContent.trim()); copyBtn.textContent = 'Copied'; setTimeout(()=> copyBtn.textContent='Copy', 1200); }
            catch {}
          };
        }
        if (createdCloseBtn) createdCloseBtn.onclick = closeCreatedModal;
      }

      closeCreateModal();
      await loadList();
    } catch (err) {
      alert('Failed to create official. See console.');
      console.error(err);
    }
  };

  /* Row click -> details modal (photo replace/remove removed; label is "Role") */
  const openDetails = () => { detailsModal.classList.add('open'); };
  const closeDetailsModal = () => { detailsModal.classList.remove('open'); };
  closeDetails.onclick = closeDetailsModal;
  detailsModal.addEventListener('click', (e) => { if (e.target === detailsModal) closeDetailsModal(); });

  tableBody.addEventListener('click', async (e) => {
    const row = e.target.closest('tr'); if (!row) return;
    const id = row.dataset.id;
    const fd = new FormData(); fd.append('id', id);
    const { data } = await api('get_official', fd);
    const a = data.admin, p = data.profile;

    const photoUrl = p.photo_url ? p.photo_url : '';
    const isActive = (a.status === 'active');

    detailsBody.innerHTML = `
      <div class="grid g-2" style="align-items:center; margin-bottom:10px">
        <div class="row">
          <img class="photo photo-lg" id="detailPhoto" src="${photoUrl}" alt="" onerror="this.src=''">
        </div>

        <div>
          <div style="font-size:18px; font-weight:650">${a.first_name} ${a.last_name}</div>
          <div style="color:#64748b;margin-top:2px">@${a.username}</div>
          <div class="row" style="margin-top:6px; flex-wrap:wrap">
            <span class="badge">${a.role}</span>
            <span id="statusBadge" class="badge ${isActive?'active':'suspended'}">${a.status}</span>
          </div>
          <div style="color:#64748b;margin-top:8px">
            <span><strong>Last login:</strong> ${a.last_login || '—'}</span>
            <span style="margin:0 8px">·</span>
            <span><strong>Created:</strong> ${a.created_at || '—'}</span>
          </div>
        </div>
      </div>

      <div class="hr"></div>

      <div class="section">
        <h3>Personal</h3>
        <div class="grid g-2">
          <div><div class="lbl">Birthdate</div><input class="inp" value="${p.birthdate || ''}" disabled></div>
          <div><div class="lbl">Age</div><input class="inp" value="${p.age || ''}" disabled></div>
          <div><div class="lbl">Sex</div><input class="inp" value="${p.sex || ''}" disabled></div>
          <div><div class="lbl">Religion</div><input class="inp" value="${p.religion || ''}" disabled></div>
          <div><div class="lbl">Contact</div><input class="inp" value="${p.contact_number || ''}" disabled></div>
          <div><div class="lbl">Role</div><input class="inp" value="${a.role}" disabled></div>
        </div>
        <div class="mt10">
          <div class="lbl">Address</div>
          <textarea class="txt" rows="2" disabled>${p.address || ''}</textarea>
        </div>
      </div>

      <div class="hr"></div>

      <div class="section">
        <h3>Account controls</h3>
        <div class="row" style="flex-wrap:wrap">
          <button class="btn ${isActive ? 'btn-danger' : 'btn-solid'}" id="toggleBtn">
            ${isActive ? 'Deactivate' : 'Activate'}
          </button>
        </div>
      </div>
    `;
    openDetails();

    // Activate/Deactivate with pretty confirm
    const toggleBtn = document.getElementById('toggleBtn');
    const statusBadge = document.getElementById('statusBadge');
    toggleBtn.onclick = async () => {
      const goingActive = (toggleBtn.textContent.trim().toLowerCase() === 'activate');

      const ok = await niceConfirm({
        title: goingActive ? 'Activate account?' : 'Deactivate account?',
        text: goingActive
          ? 'The user will be able to sign in again.'
          : 'The user will be signed out and will not be able to log in until reactivated.',
        confirmText: goingActive ? 'Activate' : 'Deactivate',
        danger: !goingActive
      });
      if (!ok) return;

      const previousText = toggleBtn.textContent;
      toggleBtn.disabled = true;
      toggleBtn.textContent = 'Working…';

      try {
        const fd = new FormData(); fd.append('id', a.id);
        await api('toggle', fd);

        // flip UI state
        const nowActive = goingActive;
        const text = nowActive ? 'Deactivate' : 'Activate';
        toggleBtn.classList.toggle('btn-danger', nowActive);
        toggleBtn.classList.toggle('btn-solid', !nowActive);
        toggleBtn.textContent = text;

        if (statusBadge) {
          statusBadge.textContent = nowActive ? 'active' : 'suspended';
          statusBadge.classList.toggle('active', nowActive);
          statusBadge.classList.toggle('suspended', !nowActive);
        }

        // refresh table with current filters applied
        await loadList();
      } catch (err) {
        alert('Failed to update account status.');
        console.error(err);
        toggleBtn.textContent = previousText;
      } finally {
        toggleBtn.disabled = false;
      }
    };
  });

  // Debounced search
  let searchTimer = null;
  const onFilterChanged = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 220);
  };
  filterSearch.addEventListener('input', onFilterChanged);
  filterStatus.addEventListener('change', onFilterChanged);
  toggleHideSuspended.addEventListener('change', onFilterChanged);

  // Init
  loadList();
});
