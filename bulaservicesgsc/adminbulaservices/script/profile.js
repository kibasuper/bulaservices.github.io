document.addEventListener('DOMContentLoaded', function() {
    // Profile dropdown functionality
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
    
    // Logout modal functionality
    const logoutBtn = document.getElementById('logout-btn');
    const logoutModal = document.getElementById('logout-modal');
    
    if (logoutBtn && logoutModal) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutModal.classList.add('active');
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 1000);
        });
    }
    
    // Change password button
    const changePasswordBtn = document.querySelector('.btn-primary');
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Password change functionality would be implemented here');
        });
    }
});
