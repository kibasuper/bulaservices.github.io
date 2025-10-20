document.addEventListener('DOMContentLoaded', function() {
    const userMenuBtn = document.getElementById('user-menu-btn');
    const dropdownMenu = document.getElementById('dropdown-menu');

    if (userMenuBtn && dropdownMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            dropdownMenu.classList.toggle('active', !isExpanded);
        });

        document.addEventListener('click', function() {
            userMenuBtn.setAttribute('aria-expanded', 'false');
            dropdownMenu.classList.remove('active');
        });

        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    const logoutBtn = document.getElementById('logout-btn');
    const logoutModal = document.getElementById('logout-modal');

    if (logoutBtn && logoutModal) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutModal.classList.add('active');
            setTimeout(() => { window.location.href = 'index.php'; }, 1000);
        });
    }

    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Profile changes saved successfully!');
        });
    }

    const changePasswordBtn = document.getElementById('change-password-btn');
    const passwordModal = document.getElementById('password-modal');

    if (changePasswordBtn && passwordModal) {
        changePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            passwordModal.classList.add('active');
        });
    }

    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Password changed successfully!');
            passwordModal.classList.remove('active');
        });
    }

    const toggle2faBtn = document.getElementById('toggle-2fa-btn');
    if (toggle2faBtn) {
        toggle2faBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const icon = this.querySelector('i');
            const isEnabled = icon.classList.contains('fa-toggle-on');
            icon.classList.toggle('fa-toggle-on', !isEnabled);
            icon.classList.toggle('fa-toggle-off', isEnabled);
            this.innerHTML = isEnabled ?
                '<i class="fas fa-toggle-off"></i> Enable' :
                '<i class="fas fa-toggle-on"></i> Disable';
            alert(`Two-factor authentication ${isEnabled ? 'disabled' : 'enabled'}`);
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('active');
        }
    });
});
