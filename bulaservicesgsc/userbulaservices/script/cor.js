// dynamic fee calc
function calculateFee() {
  const qtyEl = document.getElementById('copyQuantity');
  const quantity = parseInt(qtyEl?.value, 10) || 1;
  const pricePerCopy = typeof window.RESIDENCY_PRICE === 'number' ? window.RESIDENCY_PRICE : 75;
  const fee = quantity * pricePerCopy;
  const feeEl = document.getElementById('calculatedFee');
  if (feeEl) feeEl.textContent = fee.toFixed(2);
}

// pretty confirm
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

// purpose radio UI like bc.php
function selectPurpose(kind) {
  // map 'kind' to radio id suffix we used in HTML
  const map = {
    School: 'schoolPurpose',
    Government: 'governmentPurpose',
    Employment: 'employmentPurpose',
    Bank: 'bankPurpose',
    Voter: 'voterPurpose',
    Other: 'otherPurposeRadio'
  };
  const id = map[kind];
  const radio = document.getElementById(id);
  if (radio) radio.checked = true;

  document.querySelectorAll('.purpose-option').forEach(o => {
    o.classList.remove('active');
    o.setAttribute('aria-pressed', 'false');
  });
  const current = radio?.closest('.purpose-option');
  if (current) {
    current.classList.add('active');
    current.setAttribute('aria-pressed', 'true');
  }

  const showOther = kind === 'Other';
  const cont = document.getElementById('otherPurposeContainer');
  const input = document.getElementById('specifyPurpose');
  if (cont) cont.style.display = showOther ? 'block' : 'none';
  if (input) input.required = showOther;
}

function validateSection(sectionId) {
  let isValid = true;
  const section = document.getElementById(sectionId);
  if (!section) return false;

  // generic requireds (excluding read-only)
  const requiredInputs = section.querySelectorAll('[required]:not([readonly])');
  requiredInputs.forEach(input => {
    if (!String(input.value || '').trim()) {
      input.classList.add('has-error');
      const err = document.getElementById(input.id + 'Error');
      if (err) err.style.display = 'block';
      isValid = false;
    } else {
      input.classList.remove('has-error');
      const err = document.getElementById(input.id + 'Error');
      if (err) err.style.display = 'none';
    }
  });

  if (sectionId === 'section2') {
    const selected = document.querySelector('input[name="purpose"]:checked');
    if (!selected) {
      const err = document.getElementById('purposeError');
      if (err) err.style.display = 'block';
      isValid = false;
    } else if (selected.value === 'Other') {
      const spec = document.getElementById('specifyPurpose');
      if (!spec || !String(spec.value).trim()) {
        const err = document.getElementById('specifyPurposeError');
        if (err) err.style.display = 'block';
        if (spec) spec.classList.add('has-error');
        isValid = false;
      }
    }
  }

  if (sectionId === 'section3') {
    const method = document.querySelector('input[name="document_method"]:checked');
    if (!method) {
      const err = document.getElementById('documentMethodError');
      if (err) err.style.display = 'block';
      isValid = false;
    } else if (method.value === 'upload') {
      const fileInput = document.getElementById('purokClearance');
      if (!fileInput || !fileInput.files[0]) {
        const fileErr = document.getElementById('fileUploadError');
        if (fileErr) fileErr.style.display = 'block';
        isValid = false;
      }
    }
  }

  return isValid;
}

function selectDocumentOption(method) {
  const optionInput = document.querySelector(`.document-option input[value="${method}"]`);
  if (optionInput) optionInput.checked = true;

  document.querySelectorAll('.document-option').forEach(opt => {
    opt.classList.remove('active');
    opt.setAttribute('aria-pressed', 'false');
  });

  const current = optionInput?.closest('.document-option');
  if (current) {
    current.classList.add('active');
    current.setAttribute('aria-pressed', 'true');
  }

  const uploadContainer = document.getElementById('uploadContainer');
  const hallInfo = document.getElementById('hallInfo');
  const fileInput = document.getElementById('purokClearance');

  if (uploadContainer) uploadContainer.style.display = method === 'upload' ? 'block' : 'none';
  if (hallInfo) hallInfo.style.display = method === 'hall' ? 'block' : 'none';
  if (fileInput) fileInput.required = method === 'upload';
}

async function submitForm(formData) {
  try {
    const response = await fetch('server/certificate_functions.php?action=submit_request', {
      method: 'POST',
      body: formData
    });
    const text = await response.text();
    try { return JSON.parse(text); }
    catch (e) {
      console.error('JSON parse error:', e, text);
      return { success: false, message: 'Server returned invalid response. Check server logs.' };
    }
  } catch (e) {
    console.error('Submission error:', e);
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

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('uiConfirm')?.classList.remove('is-open');

  // dynamic price label + total
  const perCopyText = document.getElementById('perCopyText');
  if (perCopyText) {
    const p = typeof window.RESIDENCY_PRICE === 'number' ? window.RESIDENCY_PRICE : 75;
    perCopyText.textContent = `× ₱${p.toFixed(2)} per copy`;
  }
  calculateFee();

  // file validation
  document.getElementById('purokClearance')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const nameEl = document.getElementById('fileName');
    const errEl = document.getElementById('fileUploadError');

    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        if (errEl) errEl.textContent = 'File size exceeds 5MB limit';
        if (errEl) errEl.style.display = 'block';
        e.target.value = ''; if (nameEl) nameEl.textContent = 'No file chosen'; return;
      }
      const valid = ['image/jpeg','image/png','application/pdf'];
      if (!valid.includes(file.type)) {
        if (errEl) errEl.textContent = 'Invalid file type. Please upload JPG, PNG, or PDF';
        if (errEl) errEl.style.display = 'block';
        e.target.value = ''; if (nameEl) nameEl.textContent = 'No file chosen'; return;
      }
      if (nameEl) nameEl.textContent = file.name;
      if (errEl) errEl.style.display = 'none';
    } else {
      if (nameEl) nameEl.textContent = 'No file chosen';
    }
  });

  document.getElementById('copyQuantity')?.addEventListener('input', calculateFee);

  // nav
  document.getElementById('nextBtn1')?.addEventListener('click', function() {
    if (validateSection('section1')) {
      document.getElementById('section1')?.classList.remove('active');
      document.getElementById('section2')?.classList.add('active');
      document.getElementById('step1')?.classList.remove('active');
      document.getElementById('step1')?.classList.add('completed');
      document.getElementById('step2')?.classList.add('active');
      document.getElementById('section2')?.focus();
    }
  });

  document.getElementById('nextBtn2')?.addEventListener('click', function() {
    if (validateSection('section2')) {
      document.getElementById('section2')?.classList.remove('active');
      document.getElementById('section3')?.classList.add('active');
      document.getElementById('step2')?.classList.remove('active');
      document.getElementById('step2')?.classList.add('completed');
      document.getElementById('step3')?.classList.add('active');
      document.getElementById('section3')?.focus();
    }
  });

  document.getElementById('prevBtn1')?.addEventListener('click', function() {
    document.getElementById('section2')?.classList.remove('active');
    document.getElementById('section1')?.classList.add('active');
    document.getElementById('step1')?.classList.add('active');
    document.getElementById('step2')?.classList.remove('active');
    document.getElementById('step2')?.classList.remove('completed');
    document.getElementById('section1')?.focus();
  });

  document.getElementById('prevBtn2')?.addEventListener('click', function() {
    document.getElementById('section3')?.classList.remove('active');
    document.getElementById('section2')?.classList.add('active');
    document.getElementById('step2')?.classList.add('active');
    document.getElementById('step3')?.classList.remove('active');
    document.getElementById('step3')?.classList.remove('completed');
    document.getElementById('section2')?.focus();
  });

  // Cancel → pretty confirm
  document.getElementById('cancelBtn')?.addEventListener('click', async function () {
    const confirmed = await prettyConfirm({
      title: 'Cancel application?',
      text: 'Are you sure you want to cancel? Any unsaved changes will be lost.',
      okText: 'Yes, cancel',
      cancelText: 'Stay'
    });
    if (confirmed) window.location.href = 'home.php';
  });

  // submit → AJAX to certificate_functions.php
  const formEl = document.getElementById('residencyForm');
  formEl?.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!validateSection('section3')) return;

    const submitBtn = document.getElementById('submitApplication');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner"></span> Submitting...'; }

    try {
      const formData = new FormData(this);
      formData.set('service_type', 'residency'); // enforce
      formData.set('copies', document.getElementById('copyQuantity')?.value || '1');

      const result = await submitForm(formData);

      if (result.success) {
        const refEl = document.getElementById('referenceNumber');
        if (refEl) refEl.textContent = 'Reference Number: ' + result.reference_number;
        const amtEl = document.getElementById('amountDue');
        if (amtEl) amtEl.textContent = 'Amount Due: ₱' + Number(result.amount || 0).toFixed(2);
        const modal = document.getElementById('successModal');
        if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
      } else {
        alert('Error: ' + (result.message || 'Unknown error'));
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
      }
    } catch (err) {
      alert('An error occurred. Please try again.');
      if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
    }
  });

  // success modal close
  const closers = document.querySelectorAll('.close-modal, #closeModalBtn');
  closers.forEach(el => el?.addEventListener('click', function() {
    const modal = document.getElementById('successModal');
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = 'auto'; window.location.href = 'home.php'; }
  }));
  window.addEventListener('click', function(event) {
    const modal = document.getElementById('successModal');
    if (modal && event.target === modal) {
      modal.style.display = 'none'; document.body.style.overflow = 'auto'; window.location.href = 'home.php';
    }
  });

  // optional autofill
  fetchAndFillUserInfo();
});
