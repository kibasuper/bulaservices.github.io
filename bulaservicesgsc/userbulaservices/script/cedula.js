// /script/cedula.js

let CED_PRICE_CACHE = null;

/* ===================== PRICING ===================== */
async function fetchCedulaPrice() {
  if (typeof CED_PRICE_CACHE === 'number' && !Number.isNaN(CED_PRICE_CACHE)) return CED_PRICE_CACHE;

  if (typeof window.CED_PRICE !== 'undefined') {
    const p = parseFloat(window.CED_PRICE);
    if (!Number.isNaN(p) && p > 0) { CED_PRICE_CACHE = p; return p; }
  }

  // fallback to server get_price endpoint (same as other certs)
  try {
    const res = await fetch('server/certificate_functions.php?action=get_price&type=' + (window.PRICE_TYPE || 'cedula'), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const data = await res.json();
    const p = parseFloat(data?.price);
    if (!Number.isNaN(p) && p > 0) { CED_PRICE_CACHE = p; window.CED_PRICE = p; return p; }
  } catch {}

  CED_PRICE_CACHE = 5; window.CED_PRICE = 5; return 5;
}

async function calculateFee() {
  const qtyEl = document.getElementById('copyQuantity');
  let quantity = Math.max(1, parseInt(qtyEl?.value, 10) || 1);
  if (quantity > 10) quantity = 10;
  if (qtyEl && String(qtyEl.value) !== String(quantity)) qtyEl.value = quantity;

  const pricePerCopy = await fetchCedulaPrice();
  const fee = quantity * pricePerCopy;
  const feeEl = document.getElementById('calculatedFee');
  if (feeEl) feeEl.textContent = fee.toFixed(2);
}

/* ===================== PRETTY CONFIRM ===================== */
function prettyConfirm({ title, text, okText = 'Yes', cancelText = 'Cancel' } = {}) {
  return new Promise((resolve) => {
    const root = document.getElementById('uiConfirm');
    const titleEl = document.getElementById('uiConfirmTitle');
    const okBtn = document.getElementById('uiConfirmOk');
    const cancelBtn = document.getElementById('uiConfirmCancel');
    const txtEl = root?.querySelector('.ui-confirm__text');
    if (!root || !titleEl || !okBtn || !cancelBtn) return resolve(false);

    if (title) titleEl.textContent = title;
    if (text && txtEl) txtEl.textContent = text;
    okBtn.textContent = okText;
    cancelBtn.textContent = cancelText;

    root.classList.add('is-open');

    const close = (v) => {
      root.classList.remove('is-open');
      okBtn.removeEventListener('click', onOk);
      cancelBtn.removeEventListener('click', onCancel);
      root.removeEventListener('click', onBackdrop);
      document.removeEventListener('keydown', onEsc);
      resolve(v);
    };
    const onOk = () => close(true);
    const onCancel = () => close(false);
    const onBackdrop = (e) => { if (e.target === root) close(false); };
    const onEsc = (e) => { if (e.key === 'Escape') close(false); };

    okBtn.addEventListener('click', onOk);
    cancelBtn.addEventListener('click', onCancel);
    root.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onEsc);
  });
}

/* ===================== REQUIREMENTS CONFIG ===================== */
const REQS = Array.isArray(window.REQUIRED_REQS) ? window.REQUIRED_REQS : [
  { key: 'purok_clearance', label: 'Purok Clearance' },
  { key: 'valid_id',        label: 'Valid ID (Government-issued)' }
];

function setupRequirementUI() {
  const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];

  REQS.forEach(({ key }) => {
    const radios = document.querySelectorAll(`input[name="req_method[${key}]"]`);
    const ui = document.getElementById(`req_ui_${key}`);
    const fileInput = document.getElementById(`req_${key}`);
    const errEl = document.getElementById(`err_req_${key}`);
    const nameEl = document.getElementById(`name_req_${key}`);

    // initial state if browser restored values
    const chosen = document.querySelector(`input[name="req_method[${key}]"]:checked`);
    if (chosen) {
      if (chosen.value === 'upload') {
        if (ui) ui.style.display = 'block';
        if (fileInput) fileInput.required = true;
      } else {
        if (ui) ui.style.display = 'none';
        if (fileInput) fileInput.required = false;
      }
    }

    // toggle UI
    radios.forEach(r => {
      r.addEventListener('change', () => {
        if (!r.checked) return;
        if (r.value === 'upload') {
          if (ui) ui.style.display = 'block';
          if (fileInput) fileInput.required = true;
        } else {
          if (ui) ui.style.display = 'none';
          if (fileInput) { fileInput.required = false; fileInput.value = ''; }
          if (nameEl) nameEl.textContent = 'No file chosen';
          if (errEl) errEl.style.display = 'none';
        }
      });
    });

    // file validation + filename
    fileInput?.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) { if (nameEl) nameEl.textContent = 'No file chosen'; return; }

      if (file.size > 5 * 1024 * 1024) {
        if (errEl) { errEl.textContent = 'File size exceeds 5MB limit'; errEl.style.display = 'block'; }
        e.target.value = ''; if (nameEl) nameEl.textContent = 'No file chosen'; return;
      }
      if (!validTypes.includes(file.type)) {
        if (errEl) { errEl.textContent = 'Invalid file type. Upload JPG, PNG, or PDF'; errEl.style.display = 'block'; }
        e.target.value = ''; if (nameEl) nameEl.textContent = 'No file chosen'; return;
      }
      if (nameEl) nameEl.textContent = file.name;
      if (errEl) errEl.style.display = 'none';
    });
  });
}

/* ===================== VALIDATION ===================== */
function validateSection(sectionId) {
  let isValid = true;
  const section = document.getElementById(sectionId);
  if (!section) return false;

  // generic required inputs
  section.querySelectorAll('[required]').forEach(input => {
    const ok = String(input.value || '').trim().length > 0;
    input.classList.toggle('has-error', !ok);
    const err = document.getElementById(input.id + 'Error');
    if (err) err.style.display = ok ? 'none' : 'block';
    if (!ok) isValid = false;
  });

  // section2: check purpose + "Other"
  if (sectionId === 'section2') {
    const selected = document.querySelector('input[name="purpose"]:checked');
    if (!selected) {
      const err = document.getElementById('purposeError');
      if (err) err.style.display = 'block';
      isValid = false;
    } else if (selected.value === 'Other') {
      const spec = document.getElementById('specifyPurpose');
      if (!spec || !spec.value.trim()) {
        const err = document.getElementById('specifyPurposeError');
        if (err) err.style.display = 'block';
        if (spec) spec.classList.add('has-error');
        isValid = false;
      }
    }
  }

  // section3: each requirement needs method; file required if "upload"
  if (sectionId === 'section3') {
    let perReqOk = true;

    REQS.forEach(({ key, label }) => {
      const chosen = document.querySelector(`input[name="req_method[${key}]"]:checked`);
      const errEl = document.getElementById(`err_req_${key}`);
      if (errEl) errEl.style.display = 'none';

      if (!chosen) {
        perReqOk = false;
        return;
      }
      if (chosen.value === 'upload') {
        const fileInput = document.getElementById(`req_${key}`);
        if (!fileInput?.files?.[0]) {
          if (errEl) { errEl.textContent = `Please upload ${label}`; errEl.style.display = 'block'; }
          perReqOk = false;
        }
      }
    });

    const groupErr = document.getElementById('documentMethodError');
    if (groupErr) groupErr.style.display = perReqOk ? 'none' : 'block';
    isValid = isValid && perReqOk;
  }

  return isValid;
}

/* ===================== PURPOSE UI ===================== */
function selectPurpose(purpose) {
  const radio = document.getElementById((purpose.toLowerCase() + 'Purpose'));
  if (radio) radio.checked = true;

  document.querySelectorAll('.purpose-option').forEach(option => {
    option.classList.remove('active');
    option.setAttribute('aria-pressed', 'false');
  });
  const current = radio?.closest('.purpose-option');
  if (current) {
    current.classList.add('active');
    current.setAttribute('aria-pressed', 'true');
  }

  const showOther = purpose === 'Other';
  const cont = document.getElementById('otherPurposeContainer');
  const input = document.getElementById('specifyPurpose');
  if (cont) cont.style.display = showOther ? 'block' : 'none';
  if (input) input.required = showOther;
}

/* ===================== REVIEW BUILDERS ===================== */
function buildRequirementsState() {
  const list = [];
  document.querySelectorAll('.req-block').forEach(block => {
    const key   = block.getAttribute('data-key');
    const title = block.querySelector('.req-title')?.innerText.replace('*','').trim() || key;
    const chosen = document.querySelector(`input[name="req_method[${key}]"]:checked`);
    const method = chosen ? chosen.value : '';
    const file = document.getElementById('req_' + key)?.files?.[0] || null;
    list.push({ key, title, method, fileName: file ? file.name : null });
  });
  return list;
}

function renderReview() {
  const personal = document.getElementById('revPersonal');
  const purpose  = document.getElementById('revPurpose');
  const reqs     = document.getElementById('revReqs');
  const totalEl  = document.getElementById('revTotal');

  // personal
  if (personal) {
    personal.innerHTML = `
      <li><strong>Name:</strong> ${document.getElementById('fullName')?.value || ''}</li>
      <li><strong>Contact:</strong> ${document.getElementById('contactNumber')?.value || ''}</li>
      <li><strong>Address:</strong> ${document.getElementById('address')?.value || ''}</li>
      <li><strong>Year of Stay:</strong> ${document.getElementById('yearOfStay')?.value || ''}</li>
    `;
  }

  // purpose & copies
  const p = (document.querySelector('input[name="purpose"]:checked')?.value) || '';
  const pOther = (p === 'Other') ? (document.getElementById('specifyPurpose')?.value || '') : '';
  const copies = parseInt(document.getElementById('copyQuantity')?.value || '1', 10);
  if (purpose) {
    purpose.innerHTML = `
      <li><strong>Purpose:</strong> ${p}${p === 'Other' && pOther ? ' — ' + pOther : ''}</li>
      <li><strong>Copies:</strong> ${copies}</li>
    `;
  }

  // requirements summary
  const items = buildRequirementsState();
  if (reqs) {
    reqs.innerHTML = items.map(it => `
      <div class="req-line">
        <div class="req-line-title">${it.title}</div>
        <div class="req-line-detail">
          ${it.method === 'upload'
            ? `<span class="badge badge-upload"><i class="fas fa-upload"></i> Upload</span>
               <span class="file-name">${it.fileName || '(no file selected)'}</span>`
            : it.method === 'hall'
              ? `<span class="badge badge-hall"><i class="fas fa-building"></i> Bring to Hall</span>`
              : `<span class="badge"><i class="fas fa-question-circle"></i> Not chosen</span>`
          }
        </div>
      </div>
    `).join('');
  }

  // total
  fetchCedulaPrice().then(price => {
    const total = (isFinite(price) ? price : 5) * (isFinite(copies) ? copies : 1);
    if (totalEl) totalEl.textContent = total.toFixed(2);
  });
}

/* ===================== SUBMIT ===================== */
async function submitForm(formData) {
  try {
    const response = await fetch('server/certificate_functions.php?action=submit_request', {
      method: 'POST',
      body: formData
    });
    const text = await response.text();
    try { return JSON.parse(text); }
    catch {
      console.error('Invalid JSON from server:', text);
      return { success: false, message: 'Server returned invalid response. Check server logs.' };
    }
  } catch (error) {
    console.error('Submission error:', error);
    return { success: false, message: 'Network error. Please try again.' };
  }
}

async function fetchAndFillUserInfo() {
  try {
    const resp = await fetch('server/certificate_functions.php?action=get_user_info', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    });
    const data = await resp.json();
    const d = data?.data || {};
    if (d.fullName && document.getElementById('fullName')) document.getElementById('fullName').value = d.fullName;
    if (d.contactNumber && document.getElementById('contactNumber')) document.getElementById('contactNumber').value = d.contactNumber;
    if (d.address && document.getElementById('address')) document.getElementById('address').value = d.address;
    if (d.yearOfStay && document.getElementById('yearOfStay')) document.getElementById('yearOfStay').value = d.yearOfStay;
  } catch {}
}

/* ===================== INIT ===================== */
document.addEventListener('DOMContentLoaded', async function() {
  // ensure confirm is closed
  document.getElementById('uiConfirm')?.classList.remove('is-open');

  // price labels
  const price = await fetchCedulaPrice();
  const perCopyText = document.getElementById('perCopyText');
  if (perCopyText) perCopyText.textContent = `× ₱${price.toFixed(2)} per copy`;
  await calculateFee();

  // qty -> fee
  document.getElementById('copyQuantity')?.addEventListener('input', calculateFee);

  // nav: 1 -> 2
  document.getElementById('nextBtn1')?.addEventListener('click', function() {
    if (!validateSection('section1')) return;
    document.getElementById('section1')?.classList.remove('active');
    document.getElementById('section2')?.classList.add('active');
    document.getElementById('step1')?.classList.remove('active'); document.getElementById('step1')?.classList.add('completed');
    document.getElementById('step2')?.classList.add('active');
  });

  // 2 -> 3
  document.getElementById('nextBtn2')?.addEventListener('click', function() {
    if (!validateSection('section2')) return;
    document.getElementById('section2')?.classList.remove('active');
    document.getElementById('section3')?.classList.add('active');
    document.getElementById('step2')?.classList.remove('active'); document.getElementById('step2')?.classList.add('completed');
    document.getElementById('step3')?.classList.add('active');
  });

  // 3 -> 4 (build review)
  document.getElementById('nextBtn3')?.addEventListener('click', function() {
    if (!validateSection('section3')) return;
    document.getElementById('section3')?.classList.remove('active');
    document.getElementById('section4')?.classList.add('active');
    document.getElementById('step3')?.classList.remove('active'); document.getElementById('step3')?.classList.add('completed');
    document.getElementById('step4')?.classList.add('active');
    renderReview();
  });

  // prev buttons
  document.getElementById('prevBtn1')?.addEventListener('click', function() {
    document.getElementById('section2')?.classList.remove('active');
    document.getElementById('section1')?.classList.add('active');
    document.getElementById('step1')?.classList.add('active');
    document.getElementById('step2')?.classList.remove('active'); document.getElementById('step2')?.classList.remove('completed');
  });
  document.getElementById('prevBtn2')?.addEventListener('click', function() {
    document.getElementById('section3')?.classList.remove('active');
    document.getElementById('section2')?.classList.add('active');
    document.getElementById('step2')?.classList.add('active');
    document.getElementById('step3')?.classList.remove('active'); document.getElementById('step3')?.classList.remove('completed');
  });
  document.getElementById('prevBtn3')?.addEventListener('click', function() {
    document.getElementById('section4')?.classList.remove('active');
    document.getElementById('section3')?.classList.add('active');
    document.getElementById('step3')?.classList.add('active');
    document.getElementById('step4')?.classList.remove('active'); document.getElementById('step4')?.classList.remove('completed');
  });

  // cancel
  document.getElementById('cancelBtn')?.addEventListener('click', async function () {
    const confirmed = await prettyConfirm({
      title: 'Cancel application?',
      text: 'Are you sure you want to cancel? Any unsaved changes will be lost.',
      okText: 'Yes, cancel',
      cancelText: 'Stay'
    });
    if (confirmed) window.location.href = 'home.php';
  });

  // requirements toggles
  setupRequirementUI();

  // submit
  const formEl = document.getElementById('cedulaForm');
  const svcTypeEl = document.getElementById('serviceType');
  formEl?.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Ensure requirements valid even if user is on section4
    if (!validateSection('section3')) {
      document.getElementById('section4')?.classList.remove('active');
      document.getElementById('section3')?.classList.add('active');
      document.getElementById('step4')?.classList.remove('active');
      document.getElementById('step3')?.classList.add('active');
      return;
    }
    if (svcTypeEl) svcTypeEl.value = 'cedula';

    const submitBtn = document.getElementById('submitApplication');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner"></span> Submitting...'; }

    try {
      const formData = new FormData(this); // includes requirements[...] & req_method[...]
      const result = await submitForm(formData);

      if (result.success) {
        const refEl = document.getElementById('referenceNumber'); if (refEl) refEl.textContent = 'Reference Number: ' + result.reference_number;
        const amtEl = document.getElementById('amountDue'); if (amtEl) amtEl.textContent = 'Amount Due: ₱' + Number(result.amount || 0).toFixed(2);
        const modal = document.getElementById('successModal'); if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
      } else {
        alert('Error: ' + (result.message || 'Unknown error'));
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
      }
    } catch {
      alert('An error occurred. Please try again.');
      const submitBtn = document.getElementById('submitApplication');
      if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
    }
  });

  // success modal close
  const closeModalEls = document.querySelectorAll('.close-modal, #closeModalBtn');
  closeModalEls.forEach(el => el?.addEventListener('click', function() {
    const modal = document.getElementById('successModal');
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = 'auto'; window.location.href = 'home.php'; }
  }));
  window.addEventListener('click', function(event) {
    const modal = document.getElementById('successModal');
    if (modal && event.target === modal) { modal.style.display = 'none'; document.body.style.overflow = 'auto'; window.location.href = 'home.php'; }
  });

  // autofill user (non-blocking)
  fetchAndFillUserInfo();
});
