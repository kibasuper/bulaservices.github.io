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
// Logout modal functionality
const logoutBtn = document.getElementById('logout-btn');
const logoutModal = document.getElementById('logout-modal');

if (logoutBtn && logoutModal) {
  logoutBtn.addEventListener('click', async function (e) {
    e.preventDefault();

    // Show “logging out” modal
    logoutModal.classList.add('active');

    try {
      // Call server to destroy session
      const res = await fetch('./php/logout.php', { method: 'POST', credentials: 'same-origin' });
      // Ignore body; just ensure it succeeded
    } catch (err) {
      console.error('Logout request failed:', err);
      // Even if request fails, still try to go to login page;
      // but note: if the session wasn’t destroyed server-side, index.php may redirect back.
    }

    // Redirect to login
    window.location.href = 'index.php';
  });
}


    // Function to update Philippine time
    function updatePhilippineTime() {
        const options = {
            timeZone: 'Asia/Manila',
            hour12: true,
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric'
        };
        
        const formatter = new Intl.DateTimeFormat('en-PH', options);
        const timeString = formatter.format(new Date());
        
        document.getElementById('philippine-time').textContent = timeString;
    }
    
    // Update time immediately and then every second
    updatePhilippineTime();
    setInterval(updatePhilippineTime, 1000);
    
    // Make action cards actually redirect
    const actionCards = document.querySelectorAll('.action-card[href]');
    actionCards.forEach(card => {
        // Remove any existing click handlers to prevent conflicts
        card.onclick = null;
        
        // Add proper click handler that allows normal link behavior
        card.addEventListener('click', function(e) {
            // Allow middle-click, ctrl+click, etc. to work normally
            if (e.ctrlKey || e.metaKey || e.button === 1) {
                return; // let the browser handle these cases
            }
            
            // For normal clicks, proceed with navigation
            window.location.href = this.getAttribute('href');
        });
    });
});