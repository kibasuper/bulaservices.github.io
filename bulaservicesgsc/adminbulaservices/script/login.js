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
});

// Admin Forgot Password Modal Functionality
(function setupForgotModal() {
    const forgotLink = document.getElementById('forgotLink');
    const modal = document.getElementById('forgotModal');
    const form = document.getElementById('forgotForm');
    const cancelBtn = document.getElementById('forgotCancel');
    const statusDiv = document.getElementById('forgotStatus');

    if (!forgotLink || !modal || !form) return;

    function showModal() {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('forgotEmail')?.focus();
    }

    function hideModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        form.reset();
        if (statusDiv) statusDiv.textContent = '';
    }

    // Event listeners
    forgotLink.addEventListener('click', (e) => {
        e.preventDefault();
        showModal();
    });

    cancelBtn?.addEventListener('click', hideModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            hideModal();
        }
    });

    // Form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Send Reset Link';
        const email = document.getElementById('forgotEmail').value.trim();

        if (!email) {
            if (statusDiv) statusDiv.textContent = 'Please enter your email address.';
            return;
        }

        // Disable button and show loading
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }

        if (statusDiv) statusDiv.textContent = 'Sending reset link...';

        try {
            const formData = new FormData();
            formData.append('email', email);

            const response = await fetch('./php/forgot_api.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (statusDiv) {
                if (data.success) {
                    statusDiv.style.color = 'green';
                    statusDiv.textContent = data.message;
                    
                    // Auto-close after success
                    setTimeout(() => {
                        hideModal();
                    }, 3000);
                } else {
                    statusDiv.style.color = 'red';
                    statusDiv.textContent = data.message || 'An error occurred. Please try again.';
                }
            }

        } catch (error) {
            console.error('Forgot password error:', error);
            if (statusDiv) {
                statusDiv.style.color = 'red';
                statusDiv.textContent = 'Network error. Please try again.';
            }
        } finally {
            // Re-enable button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
})();
