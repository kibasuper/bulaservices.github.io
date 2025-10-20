document.addEventListener('DOMContentLoaded', function() {
    // Profile dropdown functionality
    const userMenuBtn = document.getElementById('user-menu-btn');
    const dropdownMenu = document.getElementById('dropdown-menu');
    
    if (userMenuBtn && dropdownMenu) {
        // Toggle dropdown menu
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            dropdownMenu.classList.toggle('active', !isExpanded);
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            userMenuBtn.setAttribute('aria-expanded', 'false');
            dropdownMenu.classList.remove('active');
        });
        
        // Prevent dropdown from closing when clicking inside it
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
            // Show modal
            logoutModal.classList.add('active');
            
            // Redirect after 1 second
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 1000);
        });
    }
    
    // Mark all as read functionality
    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            document.querySelectorAll('.notification-unread').forEach(item => {
                item.classList.remove('notification-unread');
            });
            alert('All notifications marked as read');
        });
    }
    
    // Notification click handler
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            this.classList.remove('notification-unread');
            console.log('Viewing notification:', this.getAttribute('data-id'));
        });
    });
});
