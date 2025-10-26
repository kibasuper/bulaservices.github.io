document.addEventListener('DOMContentLoaded', () => {
  // Dropdown
  const userMenuBtn = document.getElementById('user-menu-btn');
  const dropdownMenu = document.getElementById('dropdown-menu');
  if (userMenuBtn && dropdownMenu) {
    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = userMenuBtn.getAttribute('aria-expanded') === 'true';
      userMenuBtn.setAttribute('aria-expanded', (!isExpanded).toString());
      dropdownMenu.classList.toggle('active', !isExpanded);
    });
    document.addEventListener('click', () => {
      userMenuBtn.setAttribute('aria-expanded','false');
      dropdownMenu.classList.remove('active');
    });
    dropdownMenu.addEventListener('click', e => e.stopPropagation());
  }

  // Elements
  const firstName = document.getElementById('first_name');
  const lastName = document.getElementById('last_name');
  const username = document.getElementById('username');
  const email    = document.getElementById('email');
  const contact  = document.getElementById('contact_number');

  const saveBtn  = document.getElementById('saveProfileBtn');
  const statusEl = document.getElementById('profileStatus');

  const confirmModal   = document.getElementById('confirmModal');
  const confirmForm    = document.getElementById('confirmForm');
  const confirmError   = document.getElementById('confirmError');

  const passwordModal  = document.getElementById('passwordModal');
  const passwordForm   = document.getElementById('passwordForm');
  const passwordError  = document.getElementById('passwordError');
  const passwordStatus = document.getElementById('passwordStatus');
  const openPwBtn      = document.getElementById('openChangePassword');

  // Modal helpers
  function openModal(m) { m.classList.add('active'); m.setAttribute('aria-hidden','false'); }
  function closeModal(m) { m.classList.remove('active'); m.setAttribute('aria-hidden','true'); }
  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      closeModal(confirmModal);
      closeModal(passwordModal);
      confirmError.textContent = '';
      passwordError.textContent = '';
      confirmForm?.reset();
      passwordForm?.reset();
    });
  });
  [confirmModal, passwordModal].forEach(m => {
    m.addEventListener('click', (e) => {
      if (e.target === m) { closeModal(m); }
    });
  });

  // 1) Save profile: open confirm password modal
  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      confirmError.textContent = '';
      openModal(confirmModal);
      setTimeout(() => document.getElementById('confirm_current_password')?.focus(), 50);
    });
  }

  // On confirm → POST update
  if (confirmForm) {
    confirmForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      confirmError.textContent = '';
      statusEl.textContent = '';
      const payload = {
        first_name: firstName.value.trim(),
        last_name:  lastName.value.trim(),
        username:   username.value.trim(),
        email:      email.value.trim(),
        contact_number: contact.value.trim(),
        current_password: document.getElementById('confirm_current_password').value
      };
      try {
        const res = await fetch(window.__ADMIN__.endpoints.updateProfile, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || 'Update failed');

        closeModal(confirmModal);
        statusEl.textContent = 'Profile updated successfully.';
        statusEl.classList.add('ok');
        // Optionally refresh avatar/name in header without full reload
        setTimeout(() => { statusEl.textContent = ''; }, 3000);
      } catch (err) {
        confirmError.textContent = err.message;
      }
    });
  }

  // 2) Change password workflow
  if (openPwBtn) {
    openPwBtn.addEventListener('click', () => {
      passwordError.textContent = '';
      openModal(passwordModal);
      setTimeout(() => document.getElementById('pw_current')?.focus(), 50);
    });
  }

  if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      passwordError.textContent = '';
      passwordStatus.textContent = '';

      const current = document.getElementById('pw_current').value;
      const pw1 = document.getElementById('pw_new').value;
      const pw2 = document.getElementById('pw_new_confirm').value;

      if (pw1.length < 8) { passwordError.textContent = 'New password must be at least 8 characters.'; return; }
      if (pw1 !== pw2) { passwordError.textContent = 'New passwords do not match.'; return; }

      try {
        const res = await fetch(window.__ADMIN__.endpoints.changePassword, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ current_password: current, new_password: pw1 })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || 'Password update failed');

        closeModal(passwordModal);
        passwordStatus.textContent = 'Password updated successfully.';
        passwordStatus.classList.add('ok');
        setTimeout(() => { passwordStatus.textContent = ''; }, 3000);
        passwordForm.reset();
      } catch (err) {
        passwordError.textContent = err.message;
      }
    });
  }
});
