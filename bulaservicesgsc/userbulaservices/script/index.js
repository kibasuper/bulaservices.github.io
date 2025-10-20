document.addEventListener('DOMContentLoaded', function () {
  // --------------------- CONFIG ---------------------
  const REQUIRED_BASE = [
    'resident_status',
    'first_name',
    'last_name',
    'email',
    'username',
    'password',
    'confirm_password',
    'contact_number',
    'address'
  ];
  const REQUIRED_RESIDENT = [
    // add resident-only requireds here (client-side) if you want them enforced
    // 'birth_place', 'birth_date', 'age', 'civil_status', 'gender', 'purok', 'year_started_staying', 'occupation'
  ];

  // --------------------- GLOBAL HELPERS ---------------------
  function clearAlerts(){ document.querySelectorAll('.alert').forEach(a=>a.remove()); }
  window.showAlert = function (type, msg) {
    clearAlerts();
    const safe = type==='error'?'error':(type==='success'?'success':'info');
    const div = document.createElement('div');
    div.className = `alert alert-${safe}`;
    div.innerHTML = `
      <i class="fas ${safe==='success'?'fa-check-circle':'fa-triangle-exclamation'}"></i>
      ${msg}
      <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
    (document.querySelector('.auth-body')||document.body).prepend(div);
    if (safe!=='error') setTimeout(()=>div.remove(), 5000);
  };
  function resetButton(btn, label){ if(!btn) return; btn.disabled=false; btn.innerHTML=label; }

  // Floating “Verification Sent” dialog (global)
  window.showVerificationSentDialog = function (email) {
    // remove any previous instance
    document.querySelectorAll('.mail-sent-dialog').forEach(n => n.remove());

    const wrap = document.createElement('div');
    wrap.className = 'mail-sent-dialog active';
    wrap.innerHTML = `
      <div class="mail-sent-backdrop"></div>
      <div class="mail-sent-card" role="dialog" aria-modal="true" aria-labelledby="mailSentTitle">
        <div class="mail-sent-icon"><i class="fas fa-paper-plane"></i></div>
        <h3 id="mailSentTitle">Verification Link Sent</h3>
        <p>We’ve emailed a verification link to <strong>${email || 'your email'}</strong>.<br>
          Please check your Inbox/Spam to complete your registration.</p>
        <button type="button" class="btn mail-sent-ok">OK</button>
      </div>`;
    document.body.appendChild(wrap);

    const close = () => {
      wrap.remove();
      // switch to Login tab & prefill email
      document.querySelector('.tab[data-tab="login"]')?.click();
      const loginEmail = document.getElementById('loginEmail');
      if (loginEmail && email) loginEmail.value = email;
    };

    wrap.querySelector('.mail-sent-backdrop')?.addEventListener('click', close);
    wrap.querySelector('.mail-sent-ok')?.addEventListener('click', close);
  };

  // --------------------- SCOPED HELPERS (per-form) ---------------------
  const getEl  = (form, name) => form.querySelector(`[name="${name}"]`);
  const getVal = (form, name) => {
    // radios support
    const checked = form.querySelector(`input[name="${name}"]:checked`);
    if (checked) return (checked.value || '').trim();
    const el = getEl(form, name);
    return el && typeof el.value === 'string' ? el.value.trim() : '';
  };

  const isValidEmail     = (s) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
  const isValidPHMobile  = (s) => /^(09\d{9}|639\d{9})$/.test(String(s||'').replace(/\D+/g,''));
  const isStrongPassword = (s) => s.length>=8 && /[A-Za-z]/.test(s) && /\d/.test(s);

  function clearFormErrors(form){
    form.querySelectorAll('.error-text').forEach(el=>el.remove());
    form.querySelectorAll('.form-group').forEach(el=>el.classList.remove('has-error'));
  }
  function showFormErrors(errors, form){
    clearFormErrors(form);
    let first = null;
    Object.entries(errors||{}).forEach(([field,msg])=>{
      // Try the field; if radio, attach to its container
      let input = getEl(form, field) || form.querySelector(`[name="${field}"]`);
      if (!input && field === 'resident_status') {
        // radio group container in your HTML
        input = form.querySelector('.resident-status-container') || form.querySelector('.resident-status-section');
      }
      if (!input) return;

      const err = document.createElement('div');
      err.className = 'error-text';
      err.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${msg}`;

      const fg = input.closest('.form-group') || input.parentElement || form;
      fg.appendChild(err);
      fg.classList.add('has-error');
      if (!first) first = (input.tagName ? input : form.querySelector('input[name="resident_status"]'));
    });
    if (first && first.focus) {
      first.scrollIntoView({ behavior:'smooth', block:'center' });
      first.focus({ preventScroll:true });
    }
  }

  // --------------------- RESIDENT FIELDS TOGGLE ---------------------
  (function setupResidentFieldsToggle() {
    const res = document.querySelector('input[name="resident_status"][value="resident"]');
    const out = document.querySelector('input[name="resident_status"][value="outsider"]');
    const blocks = document.querySelectorAll('.resident-only-field');
    const registerForm = document.getElementById('registerForm');

    const toggle = () => {
      const isResident = !!res?.checked;
      blocks.forEach(b=>{
        b.style.display = isResident ? 'block' : 'none';
        if (!isResident) b.querySelectorAll('input,select,textarea').forEach(i=> i.required = false);
      });
      if (registerForm) validateRegisterForm(registerForm);
    };

    if (res && out) {
      res.addEventListener('change', toggle);
      out.addEventListener('change', toggle);
      toggle();
    }
  })();

  // --------------------- TABS ---------------------
  (function tabSwitching() {
    document.querySelectorAll('.tab').forEach((tab) => {
      tab.addEventListener('click', function () {
        const tabId = this.getAttribute('data-tab');
        document.querySelectorAll('.tab').forEach((t)=>t.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.tab-content').forEach((c)=>c.classList.remove('active'));
        document.getElementById(tabId)?.classList.add('active');
        clearAlerts();
      });
    });
  })();

  // --------------------- VERIFY BANNER ---------------------
  (function handleVerifyBanner() {
    const params = new URLSearchParams(location.search);
    if (params.get('verify') === 'ok') {
      window.showAlert('success', 'Email verified successfully! You can now log in.');
      document.querySelector('.tab[data-tab="login"]')?.click();
      history.replaceState({}, '', location.pathname);
    } else if (params.get('verify') === 'invalid') {
      window.showAlert('error', 'Verification link is invalid or expired.');
      history.replaceState({}, '', location.pathname);
    }
  })();

  // --------------------- VALIDATION (SCOPED) ---------------------
  function computeErrorsFromForm(form){
    const errors = {};

    const userType = getVal(form, 'resident_status'); // 'resident' | 'outsider'
    const baseRequired = [...REQUIRED_BASE];
    const extraRequired = userType === 'resident' ? REQUIRED_RESIDENT : [];

    // Presence
    for (const k of [...new Set([...baseRequired, ...extraRequired])]) {
      const v = getVal(form, k);
      if (!v) errors[k] = `${k.replace(/_/g,' ')} is required`;
    }

    // Formats
    const email = getVal(form, 'email');
    if (email && !isValidEmail(email)) errors.email = 'Invalid email format';

    const phoneDigits = getVal(form, 'contact_number').replace(/\D+/g,'');
    if (phoneDigits && !isValidPHMobile(phoneDigits)) errors.contact_number = 'Invalid PH mobile number';

    const pwd = getEl(form, 'password')?.value || '';
    const confirm = getEl(form, 'confirm_password')?.value || '';
    if (pwd && !isStrongPassword(pwd)) errors.password = 'Min 8 chars, include letters and numbers';
    if (pwd && confirm && pwd !== confirm) errors.confirm_password = 'Passwords do not match';

    return errors;
  }

  function validateRegisterForm(form){
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    const errs = computeErrorsFromForm(form);
    btn.classList.toggle('btn-disabled', Object.keys(errs).length>0); // visual only
  }

  (function attachValidationListeners(){
    const form = document.getElementById('registerForm');
    if (!form) return;
    form.querySelectorAll('input,select,textarea').forEach(i=>{
      i.addEventListener('input', ()=>validateRegisterForm(form));
      i.addEventListener('change', ()=>validateRegisterForm(form));
      i.addEventListener('blur',  ()=>validateRegisterForm(form));
    });
    validateRegisterForm(form);
  })();

  // --------------------- BIRTHDATE UX ---------------------
  (function birthdateUX(){
    const bd = document.getElementById('birthDate');
    if (!bd) return;
    bd.addEventListener('focus', function(){
      this.type = 'date';
      this.max  = new Date().toISOString().split('T')[0];
    });
    bd.addEventListener('blur', function(){ if (!this.value) this.type='text'; });
  })();

  // Focus email when switching to login
  document.querySelector('.tab[data-tab="login"]')?.addEventListener('click', function () {
    setTimeout(()=> document.getElementById('loginEmail')?.focus(), 100);
  });

  // Enter key submits the visible form (avoid file/textarea or when ToS is open)
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    const tosOpen = document.getElementById('tosModal')?.getAttribute('aria-hidden') === 'false';
    if (tosOpen) return; // don't submit while ToS modal is open
    const a = document.activeElement;
    if (a && (a.tagName === 'TEXTAREA' || a.type === 'file')) return;
    const activeTab = document.querySelector('.tab-content.active');
    const form = activeTab?.querySelector('form');
    const btn = form?.querySelector('button[type="submit"]');
    if (form && btn) btn.click();
  });

  // --------------------- PROFILE PICTURE (preview + camera) ---------------------
  (function setupProfilePhoto() {
    const fileInput  = document.getElementById('profilePictureInput');
    const imgPreview = document.getElementById('profileImage');
    const form       = document.getElementById('registerForm');

    // hidden field for camera capture
    let hiddenData = form?.querySelector('input[name="profile_picture_data"]');
    if (!hiddenData && form) {
      hiddenData = document.createElement('input');
      hiddenData.type = 'hidden';
      hiddenData.name = 'profile_picture_data';
      form.appendChild(hiddenData);
    }

    // File picker preview
    fileInput?.addEventListener('change', (e)=>{
      const file = e.target.files && e.target.files[0];
      if (!file) return;
      if (hiddenData) hiddenData.value = ''; // clear camera data
      const reader = new FileReader();
      reader.onload = (ev)=>{
        if (imgPreview) { imgPreview.src = ev.target.result; imgPreview.style.display='block'; }
      };
      reader.readAsDataURL(file);
    });

    // Camera capture (simple modal)
    const takeBtn = document.getElementById('takePhotoBtn');
    takeBtn?.addEventListener('click', async ()=>{
      if (!navigator.mediaDevices?.getUserMedia) {
        window.showAlert('error','Camera not supported by this browser.');
        return;
      }
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;';
      const dialog = document.createElement('div');
      dialog.style.cssText = 'background:#fff;width:min(520px,95vw);border-radius:10px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.35);';
      const head = Object.assign(document.createElement('div'), { textContent: 'Take a Photo' });
      head.style.cssText = 'padding:12px 16px;border-bottom:1px solid #eee;font-weight:600;';
      const body = document.createElement('div'); body.style.cssText='padding:12px 16px;display:flex;flex-direction:column;gap:8px;';
      const video = document.createElement('video'); video.autoplay = true; video.playsInline = true; video.style.cssText='width:100%;max-height:60vh;background:#000;border-radius:6px;';
      const ctrls = document.createElement('div'); ctrls.style.cssText='display:flex;gap:8px;justify-content:flex-end;padding:12px 16px;border-top:1px solid #eee;';
      const btnClose = Object.assign(document.createElement('button'), { className:'btn btn-outline', textContent:'Cancel' });
      const btnSnap  = Object.assign(document.createElement('button'), { className:'btn btn-primary', textContent:'Capture' });
      ctrls.append(btnClose, btnSnap); body.appendChild(video); dialog.append(head, body, ctrls); overlay.appendChild(dialog); document.body.appendChild(overlay);

      let stream;
      const close = ()=>{ try{stream?.getTracks().forEach(t=>t.stop());}catch{} overlay.remove(); };
      btnClose.addEventListener('click', close);

      try {
        stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment' }, audio:false });
        video.srcObject = stream; await video.play();
      } catch (e) {
        console.error(e); window.showAlert('error','Unable to access camera. Use HTTPS and allow permission.'); close(); return;
      }

      btnSnap.addEventListener('click', ()=>{
        const canvas = document.createElement('canvas');
        canvas.width  = video.videoWidth || 640;
        canvas.height = video.videoHeight|| 480;
        canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
        const dataURL = canvas.toDataURL('image/png');

        if (imgPreview) { imgPreview.src = dataURL; imgPreview.style.display='block'; }
        if (fileInput) fileInput.value = '';
        if (hiddenData) hiddenData.value = dataURL;

        window.showAlert('success','Photo captured. Proceed with registration.');
        close();
      });
    });
  })();

  // --------------------- REGISTRATION SUBMIT ---------------------
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = registerForm.querySelector('button[type="submit"]');
      const txt = btn?.textContent || 'Register';

      const errs = computeErrorsFromForm(registerForm);
      if (Object.keys(errs).length > 0) {
        showFormErrors(errs, registerForm);
        window.showAlert('error', 'Please fix the highlighted fields.');
        return;
      }

      const formData = new FormData(registerForm);
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...'; }
      clearFormErrors(registerForm); clearAlerts();

      try {
        // Creates / refreshes user (NO email yet). ToS acceptance will trigger email + DB finalize (depending on your backend).
        const res  = await fetch('./php/register_api.php', { method:'POST', body:formData, credentials:'same-origin' });
        const text = await res.text();
        console.log('Registration Raw Response:', text);
        let data; try { data = JSON.parse(text); } catch { window.showAlert('error','Unexpected server response.'); return; }

        if (data.success) {
          registerForm.reset();
          const img = document.getElementById('profileImage'); if (img) img.src = './pics/profile-placeholder.jpg';
          // Open ToS modal; after Accept we send the verification and show the floating dialog.
          window.__openTosModal?.(data.email || '');
        } else {
          if (data.errors) showFormErrors(data.errors, registerForm);
          window.showAlert('error', data.message || 'Please fix the highlighted fields.');
        }
      } catch (err) {
        console.error('Registration Network Error:', err);
        window.showAlert('error','Network error. Please try again.');
      } finally {
        resetButton(btn, txt);
        validateRegisterForm(registerForm);
      }
    });
  }

  // --------------------- LOGIN SUBMIT ---------------------
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const formData = new FormData(loginForm);
      const btn = loginForm.querySelector('button[type="submit"]');
      const txt = btn?.textContent || 'Login';
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...'; }
      clearAlerts();

      try {
        const resp = await fetch('./php/login_api.php', { method:'POST', body:formData, credentials:'same-origin' });
        const text = await resp.text();
        console.log('Login Raw Response:', text);
        let data; try { data = JSON.parse(text); } catch { window.showAlert('error','Unexpected server response.'); return; }

        if (data.success) {
          window.showAlert('success','Login successful! Redirecting...');
          setTimeout(()=>{ window.location.href = data.redirect || '/home.php'; }, 900);
        } else {
          window.showAlert('error', data.message || 'Invalid credentials.');
        }
      } catch (err) {
        console.error('Login Network Error:', err);
        window.showAlert('error','Network error. Please try again.');
      } finally {
        resetButton(btn, txt);
      }
    });
  }

// --------------------- TOS / PRIVACY MODAL ---------------------
(function setupTosModal () {
  const modal      = document.getElementById('tosModal');
  const scrollBox  = document.getElementById('tosScroll');  // the ONLY scroller
  const agree      = document.getElementById('tosAgree');
  const btnAccept  = document.getElementById('tosAccept');
  const btnDecline = document.getElementById('tosDecline');
  let pendingEmail = '';

  function isVisible() { return modal && modal.getAttribute('aria-hidden') === 'false'; }

  function checkBottomEnable() {
    if (!scrollBox || !agree) return;
    const atBottom = (scrollBox.scrollTop + scrollBox.clientHeight) >= (scrollBox.scrollHeight - 10);
    if (atBottom) agree.disabled = false;
  }

  function openTos(email){
    if (!modal) return;
    pendingEmail = email || '';
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('modal-open');

    // reset state
    if (scrollBox) scrollBox.scrollTop = 0;
    if (agree) { agree.checked = false; agree.disabled = true; }
    if (btnAccept) { btnAccept.disabled = true; btnAccept.textContent = 'Accept & Continue'; }

    // measure after layout paints
    requestAnimationFrame(checkBottomEnable);
  }

  function closeTos(){
    if (!modal) return;
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('modal-open');
  }

  // Listeners
  scrollBox?.addEventListener('scroll', checkBottomEnable);
  window.addEventListener('resize', () => { if (isVisible()) checkBottomEnable(); });

  agree?.addEventListener('change', () => { if (btnAccept) btnAccept.disabled = !agree.checked; });
  btnDecline?.addEventListener('click', () => { window.showAlert?.('error','You must accept the Terms to continue.'); closeTos(); });
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && isVisible()) closeTos(); });
  modal?.querySelector('.tos-backdrop')?.addEventListener('click', closeTos);

  btnAccept?.addEventListener('click', async () => {
    if (!pendingEmail) { window.showAlert?.('error','Missing email reference.'); return; }
    btnAccept.disabled = true; btnAccept.textContent = 'Sending verification...';
    try {
      const resp = await fetch('./php/tos_accept_api.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ email: pendingEmail })
      });
      const data = await resp.json();
      if (data.success) { closeTos(); window.showVerificationSentDialog?.(pendingEmail); }
      else { window.showAlert?.('error', data.message || 'Could not send verification.'); btnAccept.disabled = false; btnAccept.textContent = 'Accept & Continue'; }
    } catch (e) {
      console.error(e); window.showAlert?.('error','Network error. Please try again.');
      btnAccept.disabled = false; btnAccept.textContent = 'Accept & Continue';
    }
  });

  // expose opener
  window.__openTosModal = openTos;

  // ensure hidden on load
  if (modal) modal.setAttribute('aria-hidden','true');
})();
});

// Purok List Dialog Functionality
(function setupPurokListDialog() {
  const dialog = document.getElementById('purokListDialog');
  const showBtn = document.getElementById('showPurokList');
  const closeBtn = document.getElementById('purokListClose');

  if (!dialog || !showBtn || !closeBtn) return;

  // Show dialog
  showBtn.addEventListener('click', () => {
    dialog.classList.add('active');
  });

  // Close dialog
  closeBtn.addEventListener('click', () => {
    dialog.classList.remove('active');
  });

  // Close when clicking outside
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) {
      dialog.classList.remove('active');
    }
  });

  // Close with Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && dialog.classList.contains('active')) {
      dialog.classList.remove('active');
    }
  });
})();
