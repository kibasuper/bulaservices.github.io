// /userbulaservices/script/bc.js (revised)

// Calculate clearance fee based on number of copies (dynamic price from server)
function calculateFee() {
    const qtyEl = document.getElementById('copyQuantity');
    const quantity = parseInt(qtyEl?.value, 10) || 1;
    const pricePerCopy = typeof window.BC_PRICE === 'number' ? window.BC_PRICE : 80;
    const fee = quantity * pricePerCopy;

    const feeEl = document.getElementById('calculatedFee');
    if (feeEl) feeEl.textContent = fee.toFixed(2);
}

// confirm
function prettyConfirm({ title, text, okText = 'Yes', cancelText = 'Cancel' } = {}) {
  return new Promise((resolve) => {
    const root = document.getElementById('uiConfirm');
    const titleEl = document.getElementById('uiConfirmTitle');
    const okBtn = document.getElementById('uiConfirmOk');
    const cancelBtn = document.getElementById('uiConfirmCancel');

    if (!root || !titleEl || !okBtn || !cancelBtn) return resolve(false);

    // Update copy
    if (title) titleEl.textContent = title;
    const txtEl = root.querySelector('.ui-confirm__text');
    if (text && txtEl) txtEl.textContent = text;
    okBtn.textContent = okText;
    cancelBtn.textContent = cancelText;

    // Open
    root.classList.add('is-open');

    // Handlers
    const close = (value) => {
      root.classList.remove('is-open');
      okBtn.removeEventListener('click', onOk);
      cancelBtn.removeEventListener('click', onCancel);
      root.removeEventListener('click', onBackdrop);
      document.removeEventListener('keydown', onEsc);
      resolve(value);
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


// Validate form section
function validateSection(sectionId) {
    let isValid = true;
    const section = document.getElementById(sectionId);
    if (!section) return false;

    // Validate required fields
    const requiredInputs = section.querySelectorAll('[required]');
    requiredInputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('has-error');
            const errorId = input.id + 'Error';
            if (document.getElementById(errorId)) {
                document.getElementById(errorId).style.display = 'block';
            }
            isValid = false;
        } else {
            input.classList.remove('has-error');
            const errorId = input.id + 'Error';
            if (document.getElementById(errorId)) {
                document.getElementById(errorId).style.display = 'none';
            }
        }
    });

    // Validate purpose "Other"
    if (sectionId === 'section2' && document.querySelector('input[name="purpose"]:checked')?.value === 'Other') {
        const spec = document.getElementById('specifyPurpose');
        if (!spec || !spec.value.trim()) {
            const err = document.getElementById('specifyPurposeError');
            if (err) err.style.display = 'block';
            if (spec) spec.classList.add('has-error');
            isValid = false;
        }
    }

    // Validate document method
    if (sectionId === 'section3') {
        const documentMethod = document.querySelector('input[name="document_method"]:checked');
        if (!documentMethod) {
            const err = document.getElementById('documentMethodError');
            if (err) err.style.display = 'block';
            isValid = false;
        } else if (documentMethod.value === 'upload') {
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

// Handle document option selection
function selectDocumentOption(method) {
    const optionInput = document.querySelector(`.document-option input[value="${method}"]`);
    if (optionInput) optionInput.checked = true;

    document.querySelectorAll('.document-option').forEach(opt => {
        opt.classList.remove('active');
        opt.setAttribute('aria-pressed', 'false');
    });

    const currentOption = optionInput?.closest('.document-option');
    if (currentOption) {
        currentOption.classList.add('active');
        currentOption.setAttribute('aria-pressed', 'true');
    }

    const uploadContainer = document.getElementById('uploadContainer');
    const hallInfo = document.getElementById('hallInfo');
    const fileInput = document.getElementById('purokClearance');

    if (uploadContainer) uploadContainer.style.display = method === 'upload' ? 'block' : 'none';
    if (hallInfo) hallInfo.style.display = method === 'hall' ? 'block' : 'none';
    if (fileInput) fileInput.required = method === 'upload';
}

// Handle purpose selection
function selectPurpose(purpose) {
    const radio = document.getElementById((purpose.toLowerCase() + 'Purpose'));
    if (radio) radio.checked = true;

    document.querySelectorAll('.purpose-option').forEach(option => {
        option.classList.remove('active');
        option.setAttribute('aria-pressed', 'false');
    });

    const currentOption = radio?.closest('.purpose-option');
    if (currentOption) {
        currentOption.classList.add('active');
        currentOption.setAttribute('aria-pressed', 'true');
    }

    const showOther = purpose === 'Other';
    const otherContainer = document.getElementById('otherPurposeContainer');
    const specifyInput = document.getElementById('specifyPurpose');
    if (otherContainer) otherContainer.style.display = showOther ? 'block' : 'none';
    if (specifyInput) specifyInput.required = showOther;
}

// Submit form via AJAX
async function submitForm(formData) {
    try {
        const response = await fetch('server/certificate_functions.php?action=submit_request', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Raw response:', text);

        try {
            const result = JSON.parse(text);
            return result;
        } catch (parseError) {
            console.error('JSON parse error:', parseError, 'Response was:', text);
            return { success: false, message: 'Server returned invalid response. Check server logs.' };
        }
    } catch (error) {
        console.error('Submission error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// Optional: fetch logged-in user info to auto-fill form fields
async function fetchAndFillUserInfo() {
    try {
        const resp = await fetch('server/certificate_functions.php?action=get_user_info', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        const data = await resp.json();
        if (data.success !== false && data.data) {
            const d = data.data;
            if (d.fullName) {
                const el = document.getElementById('fullName');
                if (el && !el.value) el.value = d.fullName;
            }
            if (d.contactNumber) {
                const el = document.getElementById('contactNumber');
                if (el && !el.value) el.value = d.contactNumber;
            }
            if (d.address) {
                const el = document.getElementById('address');
                if (el && !el.value) el.value = d.address;
            }
            if (d.yearOfStay) {
                const el = document.getElementById('yearOfStay');
                if (el && !el.value) el.value = d.yearOfStay;
            }
        }
    } catch (err) {
        console.warn('Unable to fetch user info:', err);
    }
}

// Initialize form when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // set per-copy label and initial total from dynamic price
    const perCopyText = document.getElementById('perCopyText');
    if (perCopyText) {
        const p = typeof window.BC_PRICE === 'number' ? window.BC_PRICE : 80;
        perCopyText.textContent = `× ₱${p.toFixed(2)} per copy`;
    }
    calculateFee();

    // File upload input id in your HTML is "purokClearance"
    const fileInput = document.getElementById('purokClearance');
    fileInput?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const fileNameEl = document.getElementById('fileName');
        const fileUploadErrorEl = document.getElementById('fileUploadError');

        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                if (fileUploadErrorEl) fileUploadErrorEl.textContent = 'File size exceeds 5MB limit';
                if (fileUploadErrorEl) fileUploadErrorEl.style.display = 'block';
                e.target.value = '';
                if (fileNameEl) fileNameEl.textContent = 'No file chosen';
                return;
            }
            const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                if (fileUploadErrorEl) fileUploadErrorEl.textContent = 'Invalid file type. Please upload JPG, PNG, or PDF';
                if (fileUploadErrorEl) fileUploadErrorEl.style.display = 'block';
                e.target.value = '';
                if (fileNameEl) fileNameEl.textContent = 'No file chosen';
                return;
            }
            if (fileNameEl) fileNameEl.textContent = file.name;
            if (fileUploadErrorEl) fileUploadErrorEl.style.display = 'none';
        } else {
            if (fileNameEl) fileNameEl.textContent = 'No file chosen';
        }
    });

    // copy quantity -> fee calculator
    const qtyEl = document.getElementById('copyQuantity');
    qtyEl?.addEventListener('input', calculateFee);

    // navigation buttons
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

    // Cancel button
    document.getElementById('cancelBtn')?.addEventListener('click', async function () {
        const confirmed = await prettyConfirm({
            title: 'Cancel application?',
            text: 'Are you sure you want to cancel? Any unsaved changes will be lost.',
            okText: 'Yes, cancel',
            cancelText: 'Stay'
        });
        if (confirmed) window.location.href = 'home.php';
    });

    // Form submission
    const formEl = document.getElementById('clearanceForm');
    formEl?.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!validateSection('section3')) return;

        const submitBtn = document.getElementById('submitApplication');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        }

        try {
            const formData = new FormData(this);
            const result = await submitForm(formData);

            if (result.success) {
                const refEl = document.getElementById('referenceNumber');
                if (refEl) refEl.textContent = 'Reference Number: ' + result.reference_number;
                const amtEl = document.getElementById('amountDue');
                if (amtEl) amtEl.textContent = 'Amount Due: ₱' + Number(result.amount || 0).toFixed(2);
                const modal = document.getElementById('successModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                alert('Error: ' + (result.message || 'Unknown error'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
                }
            }
        } catch (error) {
            console.error('Submission error:', error);
            alert('An error occurred. Please try again.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
            }
        }
    });

    // Modal close
    const closeModalEls = document.querySelectorAll('.close-modal, #closeModalBtn');
    closeModalEls.forEach(el => el?.addEventListener('click', function() {
        const modal = document.getElementById('successModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            window.location.href = 'home.php';
        }
    }));

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('successModal');
        if (modal && event.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            window.location.href = 'home.php';
        }
    });

    // Initialize UI states
    const uploadContainer = document.getElementById('uploadContainer');
    if (uploadContainer) uploadContainer.style.display = 'none';
    const hallInfo = document.getElementById('hallInfo');
    if (hallInfo) hallInfo.style.display = 'none';
    const otherPurposeContainer = document.getElementById('otherPurposeContainer');
    if (otherPurposeContainer) otherPurposeContainer.style.display = 'none';

    // Attempt to fetch user info to autofill (non-blocking)
    fetchAndFillUserInfo();
});
