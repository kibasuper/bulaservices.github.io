document.addEventListener('DOMContentLoaded', function () {
  const loginForm = document.getElementById('login-form');
  const loginError = document.getElementById('login-error');
  const changeModal = document.getElementById('change-password-modal');
  const changeForm = document.getElementById('change-password-form');
  const changeError = document.getElementById('change-pass-error');
  const changeBtn = document.getElementById('change-pass-btn');

  if (!loginForm) return;

  // ---- Login submit ----
  loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    loginError.textContent = '';

    const formData = new FormData(loginForm);

    try {
      const res = await fetch('./php/login.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (!data.success) {
        loginError.textContent = data.message || 'Invalid credentials.';
        return;
      }

      if (data.mustChangePassword) {
        changeModal.classList.add('show');
      } else {
        window.location.href = 'admin.php';
      }
    } catch (err) {
      console.error('Login error:', err);
      loginError.textContent = 'Server error. Please try again.';
    }
  });

  // ---- Change password submit ----
  changeForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    changeError.textContent = '';
    changeBtn.disabled = true;
    changeBtn.textContent = 'Updating...';

    const formData = new FormData(changeForm);

    try {
      const res = await fetch('./php/change_password.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        changeBtn.textContent = 'Password Updated!';
        setTimeout(() => {
          alert('Password changed successfully!');
          changeModal.classList.remove('show');
          window.location.href = 'admin.php';
        }, 1000);
      } else {
        changeError.textContent = data.message || 'Password change failed.';
        changeBtn.disabled = false;
        changeBtn.textContent = 'Update Password';
      }
    } catch (err) {
      console.error('Change password error:', err);
      changeError.textContent = 'Server error. Please try again.';
      changeBtn.disabled = false;
      changeBtn.textContent = 'Update Password';
    }
    
  });
});
