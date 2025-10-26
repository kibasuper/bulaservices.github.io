let currentUser = null;
let pendingSave = null;  // { kind: 'field'|'all', field?:string, payloadBuilder:Function }

document.addEventListener('DOMContentLoaded', () => {
  initProfile();

  // Pic upload triggers
  document.getElementById('editPicBtn').addEventListener('click', () => {
    document.getElementById('profilePicInput').click();
  });
  document.getElementById('profilePicInput').addEventListener('change', onPickProfilePic);

  // Save buttons
  document.getElementById('saveAllBtn').addEventListener('click', onSaveAllClicked);
  document.getElementById('pwConfirmBtn').addEventListener('click', onPwConfirm);
  document.getElementById('changePwBtn').addEventListener('click', onChangePassword);
  document.getElementById('changePwClearBtn').addEventListener('click', () => {
    const ids = ['pwOld','pwNew','pwNew2'];
    ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  });

  ['pwOld','pwNew','pwNew2','pwConfirmInput'].forEach(id => {
    const el = document.getElementById(id);
    if (el) addPasswordToggle(el);
  });

  // Enter key submits password confirm
  const pwInput = document.getElementById('pwConfirmInput');
  if (pwInput) {
    pwInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') onPwConfirm();
    });
  }
  
});

/* -------------------- helpers -------------------- */
function cap(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function setText(id, val){ const el=document.getElementById(id); if (el) el.textContent = (val ?? '—'); }
function setSelect(id, val){ const el=document.getElementById(id); if (el) el.value = val; }
function setInputValue(id, val){ const el=document.getElementById(id); if(el) el.value = val; }

async function api(url, opts={}) {
  const res = await fetch(url, { credentials:'include', ...opts });
  const json = await res.json().catch(()=>({ok:false,error:`HTTP ${res.status}`})); 
  if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
  return json;
}

//Change pass
async function onChangePassword(e){
  e.preventDefault();

  const oldPw = (document.getElementById('pwOld')?.value || '').trim();
  const newPw = (document.getElementById('pwNew')?.value || '').trim();
  const newPw2 = (document.getElementById('pwNew2')?.value || '').trim();

  // Client-side checks
  if (!oldPw)  { showToast('warn','Required','Please enter your current password.'); return; }
  if (!newPw)  { showToast('warn','Required','Please enter a new password.'); return; }
  if (newPw.length < 8) { showToast('warn','Too short','New password must be at least 8 characters.'); return; }
  if (newPw !== newPw2) { showToast('warn','Mismatch','New password entries do not match.'); return; }
  if (newPw === oldPw)  { showToast('warn','No change','New password must be different from current password.'); return; }


  if (!/[A-Za-z]/.test(newPw) || !/\d/.test(newPw)) {
    showToast('warn','Weak password','Consider using letters and numbers for a stronger password.');
  }

  const fd = new FormData();
  fd.append('action','change_password');
  fd.append('csrf_token', window.CSRF_TOKEN);
  fd.append('old_password', oldPw);
  fd.append('new_password', newPw);
  fd.append('new_password2', newPw2);

  try {
    await postForm(window.PROFILE_API, fd);
    showToast('success','Password updated','Your password has been changed.');
    // Clear fields
    ['pwOld','pwNew','pwNew2'].forEach(id => { const el=document.getElementById(id); if (el) el.value=''; });
    // (Optional) Refresh CSRF and user info
    await initProfile();
  } catch(err) {
    showToast('error','Update failed', err.message || 'Please check your current password.');
    console.error(err);
  }
}


// --- Purok name map (same list you used on index.php) ---
const PUROK_NAMES = {
  "1":"Pearly Shell","2":"Fishermans Village","3":"Rajah Muda","4":"Rajah Muda 4A",
  "5":"Rajah Muda 4B","6":"Rajah Muda 5","7":"Lagang-Lagang","8":"Zone 1A",
  "9":"Zone 2B","10":"Zone 2A","11":"Zone 2B","12":"Zone 2C","13":"Zone 3,4,5",
  "14":"Zone 6","15":"Zone 7","16":"Zone 8","17":"Zone 9","18":"Calsanter",
  "19":"Sagrada Corazon","20":"Gonzales Subd.","21":"Gensanville Phase 1",
  "22":"Gensanville Phase 2","23":"Sitio Rapoa","24":"San Pedro","25":"Asai Village"
};

function getPurokName(n) {
  if (n == null) return '';
  const k = String(parseInt(n, 10));
  return PUROK_NAMES[k] || '';
}


/* ---- Toast / Snackbar helpers ---- */
function showToast(type, title, message, timeout=4000){
  const host = document.getElementById('toastHost');
  if (!host) return;
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.setAttribute('role','status');
  el.innerHTML = `
    <div class="t-icon">${type === 'success' ? '✅' :
                           type === 'error'   ? '⛔' :
                           type === 'warn'    ? '⚠️' : 'ℹ️'}</div>
    <div class="t-body">
      ${title ? `<div class="t-title">${title}</div>` : ''}
      <p class="t-msg">${message || ''}</p>
    </div>
    <button class="t-close" aria-label="Close" onclick="this.parentElement.remove()">✖</button>
  `;
  host.appendChild(el);
  const t = setTimeout(() => fadeOutToast(el), timeout);
  el.addEventListener('mouseenter', () => clearTimeout(t), { once:true });
}
function fadeOutToast(el){
  el.style.animation = 'toast-out .2s ease-in forwards';
  setTimeout(() => el.remove(), 180);
}
function showSavedSnack(text='Changes saved!'){
  const el = document.createElement('div');
  el.className = 'snackbar';
  el.textContent = text;
  document.body.appendChild(el);
  setTimeout(() => { el.classList.add('hide'); setTimeout(()=>el.remove(), 160); }, 1500);
}


/* -------------------- load & hydrate -------------------- */
async function initProfile() {
  try {
    const json = await api(`${window.PROFILE_API}?action=me`);
    if (json.csrf) window.CSRF_TOKEN = json.csrf;
    currentUser = json.data || {};
    hydrateUI(currentUser);
  } catch (e) {
    showToast('error', 'Load failed', 'Could not fetch your profile. Please try again.');
    console.error(e);
  }
}

function hydrateUI(u) {
  const name = `${u.first_name||''} ${u.middle_name? (u.middle_name[0]+'. '): ''}${u.last_name||''}`.trim();
  document.getElementById('profileName').textContent = name || 'My Profile';
  document.getElementById('profileImage').src = u.profile_picture_url || './pics/profile-placeholder.jpg';

  // Personal
  setText('firstNameValue', u.first_name);
  setText('middleNameValue', u.middle_name);
  setText('lastNameValue', u.last_name);
  setText('suffixValue', u.suffix);
  setText('birthPlaceValue', u.birth_place);
  setText('birthDateValue', u.birth_date ? formatDate(u.birth_date) : '—');
  setText('ageValue', (u.computed_age != null ? `${u.computed_age} years old` : '—'));
  setText('sexValue', u.gender ? cap(u.gender) : '—');
  setText('civilStatusValue', u.civil_status ? cap(u.civil_status) : '—');

  // Residence
  setText('purokValue', u.purok);
    (function attachPurokNameTag(){
      const holder = document.getElementById('purokValue');
      if (!holder) return;

      // Create the tag once if not present
      let tag = holder.parentElement.querySelector('.purok-name-tag');
      if (!tag) {
        tag = document.createElement('span');
        tag.className = 'purok-name-tag';
        holder.after(tag);
      }

      const name = getPurokName(u.purok);
      tag.textContent = name ? name : '';
      tag.style.display = name ? 'inline-block' : 'none';
    })();
  setText('yearStartedValue', u.year_started_staying);
  setText('contactNumberValue', u.contact_number);
  setText('occupationValue', u.occupation);
  setText('addressValue', u.address);

  // Account (read-only)
  setText('emailValue', u.email);
  setText('usernameValue', u.username);
  setText('userTypeValue', cap(u.user_type));
  setText('statusValue', cap(u.status));
  setText('activeValue', u.is_active ? 'Yes' : 'No');
  setText('emailVerifiedValue', u.email_verified ? 'Yes' : 'No');
  setText('lastLoginValue', u.last_login ? formatDateTime(u.last_login) : '—');
  setText('createdAtValue', u.created_at ? formatDateTime(u.created_at) : '—');
  setText('updatedAtValue', u.updated_at ? formatDateTime(u.updated_at) : '—');

  // Defaults in forms
  setInputValue('firstNameInput', u.first_name || '');
  setInputValue('middleNameInput', u.middle_name || '');
  setInputValue('lastNameInput', u.last_name || '');
  setInputValue('suffixInput', u.suffix || '');
  setInputValue('birthPlaceInput', u.birth_place || '');
  setInputValue('birthDateInput', u.birth_date || '');
  setSelect('sexInput', u.gender ? cap(u.gender) : 'Male');
  setSelect('civilStatusInput', u.civil_status ? cap(u.civil_status) : 'Single');
  setInputValue('purokInput', u.purok || '');
  setInputValue('yearStartedInput', u.year_started_staying ?? '');
  setInputValue('contactNumberInput', u.contact_number || '');
  setInputValue('occupationInput', u.occupation || '');
  setInputValue('addressInput', u.address || '');
}

/* -------------------- formatting -------------------- */
function formatDate(ymd) {
  const d = new Date(ymd);
  return d.toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'});
}
function formatDateTime(s) {
  const ds = s.replace(' ', 'T'); // "YYYY-MM-DD HH:MM:SS" → ISO-ish
  const d = new Date(ds);
  if (isNaN(d)) return s;
  return d.toLocaleString('en-US', {year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit'});
}

/* -------------------- edit UI -------------------- */
function toggleEdit(field) {
  const formEl = document.getElementById(`${field}Form`);
  if (!formEl) return;
  document.querySelectorAll('.edit-form').forEach(f => { if (f!==formEl) f.style.display='none'; });
  formEl.style.display = (formEl.style.display === 'block') ? 'none' : 'block';
}
function cancelEdit(field) {
  const el = document.getElementById(`${field}Form`);
  if (el) el.style.display = 'none';
}

/* -------------------- password modal flow -------------------- */
function onSaveAllClicked() {
  pendingSave = { kind: 'all', payloadBuilder: buildSaveAllPayload };
  openPwModal();
}
function openPwModal(){
  const input = document.getElementById('pwConfirmInput');
  document.getElementById('pwModalBackdrop').style.display = 'flex';
  if (input) { input.value = ''; input.focus(); }
}
function closePwModal(){
  document.getElementById('pwModalBackdrop').style.display = 'none';
  pendingSave = null;
}
async function onPwConfirm() {
  const pw = document.getElementById('pwConfirmInput').value;
  if (!pendingSave) return closePwModal();
  if (!pw) { showToast('warn','Password required','Please enter your password.'); return; }

  try {
    const fd = pendingSave.payloadBuilder();
    fd.append('confirm_password', pw);
    await postForm(window.PROFILE_API, fd);
    await initProfile();
    if (pendingSave.kind === 'field') {
      const field = pendingSave.field;
      const formEl = document.getElementById(`${field}Form`);
      if (formEl) formEl.style.display = 'none';
    }
    closePwModal();
    showToast('success', 'Saved', 'Your profile was updated.');
    showSavedSnack('Saved!');
  } catch (e) {
    showToast('error', 'Save failed', e.message || 'Please check your password and inputs.');
    console.error(e);
  }
}

/* -------------------- save: single field -------------------- */
async function saveField(field) {
  pendingSave = {
    kind: 'field',
    field,
    payloadBuilder: () => buildSingleFieldPayload(field)
  };
  openPwModal();
}

function buildSingleFieldPayload(field) {
  const map = {
    firstName:        ['first_name','firstNameInput'],
    middleName:       ['middle_name','middleNameInput'],
    lastName:         ['last_name','lastNameInput'],
    suffix:           ['suffix','suffixInput'],
    birthPlace:       ['birth_place','birthPlaceInput'],
    birthDate:        ['birth_date','birthDateInput'],
    sex:              ['gender','sexInput'],                
    civilStatus:      ['civil_status','civilStatusInput'],   
    purok:            ['purok','purokInput'],
    yearStarted:      ['year_started_staying','yearStartedInput'],
    contactNumber:    ['contact_number','contactNumberInput'],
    occupation:       ['occupation','occupationInput'],
    address:          ['address','addressInput'],
  };
  const pair = map[field];
  const fd = new FormData();
  fd.append('action','update');
  fd.append('csrf_token', window.CSRF_TOKEN);

  if (!pair) return fd;
  let value = getInputVal(pair[1]);
  if (field === 'sex') value = (value || 'Male').toLowerCase();
  if (field === 'civilStatus') value = (value || 'Single').toLowerCase();
  fd.append(pair[0], value);

  return fd;
}

/* -------------------- save: all fields -------------------- */
function buildSaveAllPayload() {
  const fd = new FormData();
  fd.append('action','update');
  fd.append('csrf_token', window.CSRF_TOKEN);

  fd.append('first_name', getInputVal('firstNameInput'));
  fd.append('middle_name', getInputVal('middleNameInput'));
  fd.append('last_name', getInputVal('lastNameInput'));
  fd.append('suffix', getInputVal('suffixInput'));
  fd.append('birth_place', getInputVal('birthPlaceInput'));
  fd.append('birth_date', getInputVal('birthDateInput'));
  fd.append('gender', (document.getElementById('sexInput').value || 'Male').toLowerCase());
  fd.append('civil_status', (document.getElementById('civilStatusInput').value || 'Single').toLowerCase());
  fd.append('purok', getInputVal('purokInput'));
  fd.append('year_started_staying', getInputVal('yearStartedInput'));
  fd.append('contact_number', getInputVal('contactNumberInput'));
  fd.append('occupation', getInputVal('occupationInput'));
  fd.append('address', getInputVal('addressInput'));

  return fd;
}

function getInputVal(id){ const el=document.getElementById(id); return el ? el.value.trim() : ''; }

/* -------------------- uploads -------------------- */
async function onPickProfilePic(e) {
  const f = e.target.files && e.target.files[0];
  if (!f) return;
  const fd = new FormData();
  fd.append('action','upload_pic');
  fd.append('csrf_token', window.CSRF_TOKEN);
  fd.append('profile_picture', f);
  try {
    const json = await postForm(window.PROFILE_API, fd);
    if (json.url) document.getElementById('profileImage').src = json.url;
    showToast('success','Profile picture updated','Your picture has been changed.');
  } catch(err) {
    showToast('error', 'Upload failed', 'Use JPG/PNG/GIF up to 2MB.');
    console.error(err);
  } finally {
    e.target.value = '';
  }
}

/* -------------------- post helper -------------------- */
async function postForm(url, formData) {
  const res = await fetch(url, { method:'POST', body: formData, credentials:'include' });
  const json = await res.json().catch(()=>({ok:false,error:`HTTP ${res.status}`})); // friendlier fallback
  if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
  return json;
}

// Show hide pass
function addPasswordToggle(input) {
  if (!input || input.dataset.hasToggle === '1') return;

  const wrap = document.createElement('div');
  wrap.className = 'pw-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'pw-toggle';
  btn.setAttribute('aria-label', 'Show password');
  btn.setAttribute('title', 'Show/Hide');
  btn.innerHTML = '<i class="fa-regular fa-eye"></i>';

  btn.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show
      ? '<i class="fa-regular fa-eye-slash"></i>'
      : '<i class="fa-regular fa-eye"></i>';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    input.focus({ preventScroll: true });
  });

  btn.addEventListener('mousedown', e => e.preventDefault());

  wrap.appendChild(btn);
  input.dataset.hasToggle = '1';
}
