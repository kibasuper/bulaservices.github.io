document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.querySelector('#officialsTable tbody');
  const btnAdd = document.getElementById('btnAdd');
  const createModal = document.getElementById('createModal');
  const closeCreate = document.getElementById('closeCreate');
  const closeCreate2 = document.getElementById('closeCreate2');
  const detailsModal = document.getElementById('detailsModal');
  const closeDetails = document.getElementById('closeDetails');
  const formCreate = document.getElementById('formCreate');
  const detailsBody = document.getElementById('detailsBody');

  // Success modal
  const createdModal = document.getElementById('createdModal');
  const createdBody  = document.getElementById('createdBody');
  const closeCreated = document.getElementById('closeCreated');
  const openCreated = () => { if (createdModal) createdModal.style.display = 'grid'; };
  const closeCreatedModal = () => { if (createdModal) createdModal.style.display = 'none'; };
  if (closeCreated) closeCreated.onclick = closeCreatedModal;
  if (createdModal) createdModal.addEventListener('click', (e)=>{ if (e.target === createdModal) closeCreatedModal(); });

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

  /* API helper */
  const api = async (action, data=null, method='POST') => {
    const opts = { method };
    if (data) opts.body = data;
    const url = `./php/officials_api.php?action=${encodeURIComponent(action)}` + (method==='GET' && data ? `&${new URLSearchParams(data).toString()}` : '');
    const res = await fetch(url, opts);
    if (!res.ok) {
      const t = await res.text();
      console.error(`API ${action} error`, t);
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  };

  /* Load list */
  async function loadList() {
    const { data } = await api('list', null, 'GET');
    tableBody.innerHTML = '';
    (data.items || []).forEach(o => {
      const tr = document.createElement('tr');
      tr.dataset.id = o.admin_id;
      tr.innerHTML = `
        <td>${(o.first_name||'') + ' ' + (o.last_name||'')}</td>
        <td>${o.role || ''}</td>
        <td>${o.position || ''}</td>
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
  };
  btnAdd.onclick = openCreate;
  closeCreate.onclick = closeCreateModal;
  if (closeCreate2) closeCreate2.onclick = closeCreateModal;
  createModal.addEventListener('click', (e) => { if (e.target === createModal) closeCreateModal(); });

  /* Create submit */
  formCreate.onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(formCreate); // includes photo if chosen
    try {
      const resp = await api('create', fd);
      const d = resp.data || {};

      // Success modal
      if (createdBody) {
        createdBody.innerHTML = `
          <div style="font-size:14px;color:#0f172a;line-height:1.6">
            <p><strong>Name:</strong> ${fd.get('first_name') || ''} ${fd.get('last_name') || ''}</p>
            <p><strong>Username:</strong> ${d.username || fd.get('username') || ''}</p>
            <p><strong>Email:</strong> ${d.email || fd.get('email') || ''}</p>
            <p style="margin-top:8px"><strong>Default Password:</strong>
              <span id="pwText" style="font-family:ui-monospace, SFMono-Regular, Menlo, monospace; background:#f8fafc; border:1px solid #e5e7eb; padding:4px 8px; border-radius:8px;">
                ${d.default_password || 'Bula@2025'}
              </span>
              <button id="copyPw" class="btn" style="margin-left:8px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer">Copy</button>
            </p>
            <p style="color:#64748b;margin-top:8px">The account details have been emailed to the user.</p>
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

      // Close create, reset, reload list
      closeCreateModal();
      await loadList();
    } catch (err) {
      alert('Failed to create official. See console.');
      console.error(err);
    }
  };

  /* Row click -> details modal */
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
    detailsBody.innerHTML = `
      <div class="grid g-2" style="align-items:center; margin-bottom:10px">
        <div class="row">
          <img class="photo photo-lg" id="detailPhoto" src="${photoUrl}" alt="" onerror="this.src=''">
          <div class="grid">
            <form id="photoForm" enctype="multipart/form-data">
              <input type="hidden" name="id" value="${a.id}">
              <input class="inp" type="file" name="photo" id="detailsPhotoInput" accept="image/*">
              <div class="row">
                <button type="submit" class="btn">Replace photo</button>
                <button id="removePhotoBtn" type="button" class="btn">Remove</button>
              </div>
            </form>
            <small style="color:#64748b">JPEG/PNG/GIF up to 2MB</small>
          </div>
        </div>

        <div>
          <div style="font-size:18px; font-weight:650">${a.first_name} ${a.last_name}</div>
          <div style="color:#64748b;margin-top:2px">@${a.username}</div>
          <div class="row" style="margin-top:6px; flex-wrap:wrap">
            <span class="badge">${a.role}</span>
            <span class="badge">${p.position || '—'}</span>
            <span class="badge ${a.status==='active'?'active':'suspended'}">${a.status}</span>
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
          <div><div class="lbl">Age</div><input class="inp" value="${p.age || ''}" disabled></div>
          <div><div class="lbl">Sex</div><input class="inp" value="${p.sex || ''}" disabled></div>
          <div><div class="lbl">Religion</div><input class="inp" value="${p.religion || ''}" disabled></div>
          <div><div class="lbl">Contact</div><input class="inp" value="${p.contact_number || ''}" disabled></div>
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
          <button class="btn ${a.status==='active' ? 'btn-danger' : 'btn-solid'}" id="toggleBtn">
            ${a.status==='active' ? 'Deactivate' : 'Activate'}
          </button>
          <button class="btn" id="changeEmailBtn">Change email</button>
          <button class="btn" id="resetBtn">Reset password</button>
        </div>
      </div>
    `;
    openDetails();

    /* Photo replace/remove */
    const photoForm = document.getElementById('photoForm');
    const detailsPhotoInput = document.getElementById('detailsPhotoInput');
    const detailPhoto = document.getElementById('detailPhoto');

    photoForm.onsubmit = async (ev) => {
      ev.preventDefault();
      if (!detailsPhotoInput.files || !detailsPhotoInput.files[0]) {
        alert('Choose a photo first.'); return;
      }
      const fd2 = new FormData(photoForm);
      try {
        const { data } = await api('update_photo', fd2);
        if (detailPhoto) detailPhoto.src = data.photo_url;
        alert('Photo updated.');
      } catch (err) {
        alert('Failed to update photo.'); console.error(err);
      }
    };
    document.getElementById('removePhotoBtn').onclick = async () => {
      if (!confirm('Remove profile photo?')) return;
      const fd3 = new FormData(); fd3.append('id', a.id);
      await api('remove_photo', fd3);
      if (detailPhoto) detailPhoto.src = '';
      alert('Photo removed.');
    };

    /* Toggle status */
    document.getElementById('toggleBtn').onclick = async () => {
      const fd = new FormData(); fd.append('id', a.id);
      await api('toggle', fd);
      alert('Account status updated.');
      closeDetailsModal();
      loadList();
    };

    /* Reset password */
    document.getElementById('resetBtn').onclick = async () => {
      const fd = new FormData(); fd.append('id', a.id);
      await api('reset', fd);
      alert('Temporary password emailed (Bula@2025).');
    };

    /* Change email */
    document.getElementById('changeEmailBtn').onclick = async () => {
      const newEmail = prompt('Enter new email:');
      if (!newEmail) return;
      const fd = new FormData(); fd.append('id', a.id); fd.append('email', newEmail);
      await api('update_email', fd);
      alert('Email updated.');
      closeDetailsModal();
      loadList();
    };
  });

  // Init
  loadList();
});
