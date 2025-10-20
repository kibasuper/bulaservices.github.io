document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const userMenuBtn = document.getElementById('user-menu-btn');
    const dropdownMenu = document.getElementById('dropdown-menu');
    const logoutBtn = document.getElementById('logout-btn');
    const addOfficialBtn = document.getElementById('add-official-btn');
    const addOfficialModal = document.getElementById('add-official-modal');
    const viewOfficialModal = document.getElementById('view-official-modal');
    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const cancelAddOfficialBtn = document.getElementById('cancel-add-official');
    const saveOfficialBtn = document.getElementById('save-official');
    const closeViewModalBtn = document.getElementById('close-view-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    const searchInput = document.getElementById('search-officials');
    const officialForm = document.getElementById('official-form');
    const officialPhoto = document.getElementById('official-photo');
    const officialPhotoPreview = document.getElementById('official-photo-preview');
    const viewBtns = document.querySelectorAll('.view-official');
    const editBtns = document.querySelectorAll('.edit-official');
    const deleteBtns = document.querySelectorAll('.delete-official');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    
    // Current state
    let currentOfficialId = null;
    let isEditMode = false;
    
    // Profile dropdown functionality
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
    
    // Logout button functionality
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Add your logout logic here
            alert('Logout functionality would be implemented here');
            console.log('Logout clicked');
        });
    }
    
    // Modal open/close functionality
    function openModal(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // Add official modal
    if (addOfficialBtn && addOfficialModal) {
        addOfficialBtn.addEventListener('click', function() {
            isEditMode = false;
            officialForm.reset();
            officialPhotoPreview.src = 'https://via.placeholder.com/100';
            document.querySelector('#add-official-modal h2').textContent = 'Add New Official';
            openModal(addOfficialModal);
        });
    }
    
    // Close modals
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    if (cancelAddOfficialBtn) {
        cancelAddOfficialBtn.addEventListener('click', function() {
            closeModal(addOfficialModal);
        });
    }
    
    if (closeViewModalBtn) {
        closeViewModalBtn.addEventListener('click', function() {
            closeModal(viewOfficialModal);
        });
    }
    
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            closeModal(deleteConfirmModal);
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });
    
    // Photo preview
    if (officialPhoto && officialPhotoPreview) {
        officialPhoto.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    officialPhotoPreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Save official
    if (saveOfficialBtn) {
        saveOfficialBtn.addEventListener('click', function() {
            if (officialForm.checkValidity()) {
                // In a real app, you would send this data to the server
                const officialData = {
                    name: document.getElementById('official-name').value,
                    position: document.getElementById('official-position').value,
                    contact: document.getElementById('official-contact').value,
                    termStart: document.getElementById('term-start').value,
                    termEnd: document.getElementById('term-end').value,
                    status: document.getElementById('official-status').value,
                    photo: officialPhotoPreview.src
                };
                
                console.log('Official data:', officialData);
                alert(isEditMode ? 'Official updated successfully!' : 'Official added successfully!');
                closeModal(addOfficialModal);
                
                // In a real app, you would update the table with the new data
            } else {
                officialForm.reportValidity();
            }
        });
    }
    
    // View official
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const cells = row.querySelectorAll('td');
            
            document.getElementById('view-official-name').textContent = cells[0].querySelector('span').textContent;
            document.getElementById('view-official-position').textContent = cells[1].textContent;
            document.getElementById('view-official-contact').textContent = cells[2].textContent;
            document.getElementById('view-official-term').textContent = cells[3].textContent;
            
            // Update status badge
            const statusBadge = document.getElementById('view-official-status');
            statusBadge.textContent = cells[4].querySelector('.badge').textContent;
            statusBadge.className = 'badge ' + cells[4].querySelector('.badge').className.split(' ')[1];
            
            // Set photo (in a real app, this would come from the data)
            document.getElementById('view-official-photo').src = cells[0].querySelector('img').src;
            
            openModal(viewOfficialModal);
        });
    });
    
    // Edit official
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            isEditMode = true;
            currentOfficialId = this.getAttribute('data-id');
            const row = this.closest('tr');
            const cells = row.querySelectorAll('td');
            
            // Fill the form with existing data
            document.getElementById('official-name').value = cells[0].querySelector('span').textContent;
            document.getElementById('official-position').value = cells[1].textContent;
            document.getElementById('official-contact').value = cells[2].textContent;
            
            // Split term years
            const termYears = cells[3].textContent.split('-');
            document.getElementById('term-start').value = termYears[0].trim();
            document.getElementById('term-end').value = termYears[1].trim();
            
            document.getElementById('official-status').value = cells[4].querySelector('.badge').textContent;
            officialPhotoPreview.src = cells[0].querySelector('img').src;
            
            document.querySelector('#add-official-modal h2').textContent = 'Edit Official';
            openModal(addOfficialModal);
        });
    });
    
    // Delete official
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentOfficialId = this.getAttribute('data-id');
            openModal(deleteConfirmModal);
        });
    });
    
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            // In a real app, you would send a delete request to the server
            console.log('Deleting official with ID:', currentOfficialId);
            alert('Official deleted successfully!');
            closeModal(deleteConfirmModal);
            
            // In a real app, you would remove the row from the table
        });
    }
    
    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#officials-table tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:first-child span').textContent.toLowerCase();
                const position = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const contact = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || position.includes(searchTerm) || contact.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update showing count
            updateShowingCount();
        });
    }
    
    // Pagination (basic implementation)
    function updateShowingCount() {
        const visibleRows = document.querySelectorAll('#officials-table tbody tr:not([style*="display: none"])');
        document.getElementById('showing-start').textContent = '1';
        document.getElementById('showing-end').textContent = visibleRows.length;
        document.getElementById('total-records').textContent = visibleRows.length;
        
        // Disable/enable pagination buttons
        prevPageBtn.parentElement.classList.add('disabled');
        nextPageBtn.parentElement.classList.add('disabled');
    }
    
    // Initialize
    updateShowingCount();
});