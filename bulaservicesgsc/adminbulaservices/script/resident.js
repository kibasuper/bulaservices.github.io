document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const userMenuBtn = document.getElementById('user-menu-btn');
    const dropdownMenu = document.getElementById('dropdown-menu');
    const logoutBtn = document.getElementById('logout-btn');
    const addResidentBtn = document.getElementById('add-resident-btn');
    const addResidentModal = document.getElementById('add-resident-modal');
    const viewResidentModal = document.getElementById('view-resident-modal');
    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const cancelAddResidentBtn = document.getElementById('cancel-add-resident');
    const saveResidentBtn = document.getElementById('save-resident');
    const closeViewModalBtn = document.getElementById('close-view-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    const searchInput = document.getElementById('search-residents');
    const residentForm = document.getElementById('resident-form');
    const residentPhoto = document.getElementById('resident-photo');
    const residentPhotoPreview = document.getElementById('resident-photo-preview');
    const viewBtns = document.querySelectorAll('.view-resident');
    const editBtns = document.querySelectorAll('.edit-resident');
    const deleteBtns = document.querySelectorAll('.delete-resident');
    
    // Current state
    let currentResidentId = null;
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
    
    // Add resident modal
    if (addResidentBtn && addResidentModal) {
        addResidentBtn.addEventListener('click', function() {
            isEditMode = false;
            residentForm.reset();
            residentPhotoPreview.src = 'https://via.placeholder.com/150';
            document.querySelector('#add-resident-modal h2').textContent = 'Add New Resident';
            document.getElementById('edit-id').value = '';
            openModal(addResidentModal);
        });
    }
    
    // Close modals
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    if (cancelAddResidentBtn) {
        cancelAddResidentBtn.addEventListener('click', function() {
            closeModal(addResidentModal);
        });
    }
    
    if (closeViewModalBtn) {
        closeViewModalBtn.addEventListener('click', function() {
            closeModal(viewResidentModal);
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
    if (residentPhoto && residentPhotoPreview) {
        residentPhoto.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    residentPhotoPreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Save resident
    if (saveResidentBtn) {
        saveResidentBtn.addEventListener('click', function() {
            if (residentForm.checkValidity()) {
                // In a real app, you would send this data to the server
                const residentData = {
                    id: document.getElementById('edit-id').value || Date.now(),
                    firstName: document.getElementById('first-name').value,
                    middleName: document.getElementById('middle-name').value,
                    lastName: document.getElementById('last-name').value,
                    suffix: document.getElementById('suffix').value,
                    birthDate: document.getElementById('birth-date').value,
                    age: document.getElementById('age').value,
                    gender: document.getElementById('gender').value,
                    civilStatus: document.getElementById('civil-status').value,
                    contactNumber: document.getElementById('contact-number').value,
                    address: document.getElementById('address').value,
                    email: document.getElementById('email').value,
                    occupation: document.getElementById('occupation').value,
                    voterStatus: document.getElementById('voter-status').value,
                    residentStatus: document.getElementById('resident-status').value,
                    notes: document.getElementById('notes').value,
                    photo: residentPhotoPreview.src
                };
                
                console.log('Resident data:', residentData);
                alert(isEditMode ? 'Resident updated successfully!' : 'Resident added successfully!');
                closeModal(addResidentModal);
                
                // In a real app, you would update the table with the new data
            } else {
                residentForm.reportValidity();
            }
        });
    }
    
    // View resident
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const cells = row.querySelectorAll('td');
            
            document.getElementById('view-full-name').textContent = cells[1].textContent;
            document.getElementById('view-age').textContent = cells[2].textContent;
            document.getElementById('view-gender').textContent = cells[3].textContent;
            document.getElementById('view-address').textContent = cells[4].textContent;
            document.getElementById('view-contact-number').textContent = cells[5].textContent;
            document.getElementById('view-civil-status').textContent = cells[6].textContent;
            document.getElementById('view-resident-status').innerHTML = cells[6].innerHTML;
            document.getElementById('view-resident-photo').src = cells[0].querySelector('img').src;
            
            // Set sample data for other fields (in a real app, this would come from the data)
            document.getElementById('view-birth-date').textContent = 'May 15, 1988';
            document.getElementById('view-email').textContent = 'juan.delacruz@example.com';
            document.getElementById('view-occupation').textContent = 'Farmer';
            document.getElementById('view-voter-status').textContent = 'Registered';
            document.getElementById('view-notes').textContent = 'No remarks';
            
            openModal(viewResidentModal);
        });
    });
    
    // Edit resident
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            isEditMode = true;
            currentResidentId = this.getAttribute('data-id');
            const row = this.closest('tr');
            const cells = row.querySelectorAll('td');
            
            // Fill the form with existing data (sample data)
            document.getElementById('first-name').value = 'Juan';
            document.getElementById('middle-name').value = '';
            document.getElementById('last-name').value = 'Dela Cruz';
            document.getElementById('suffix').value = '';
            document.getElementById('birth-date').value = '1988-05-15';
            document.getElementById('age').value = '35';
            document.getElementById('gender').value = 'Male';
            document.getElementById('civil-status').value = 'Married';
            document.getElementById('contact-number').value = '09123456789';
            document.getElementById('address').value = '123 Bula Street';
            document.getElementById('email').value = 'juan.delacruz@example.com';
            document.getElementById('occupation').value = 'Farmer';
            document.getElementById('voter-status').value = 'Registered';
            document.getElementById('resident-status').value = 'Active';
            document.getElementById('notes').value = 'No remarks';
            residentPhotoPreview.src = cells[0].querySelector('img').src;
            document.getElementById('edit-id').value = currentResidentId;
            
            document.querySelector('#add-resident-modal h2').textContent = 'Edit Resident';
            openModal(addResidentModal);
        });
    });
    
    // Delete resident
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentResidentId = this.getAttribute('data-id');
            openModal(deleteConfirmModal);
        });
    });
    
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            // In a real app, you would send a delete request to the server
            console.log('Deleting resident with ID:', currentResidentId);
            alert('Resident deleted successfully!');
            closeModal(deleteConfirmModal);
            
            // In a real app, you would remove the row from the table
        });
    }
    
    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#residents-table tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const address = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                const contact = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
                const status = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || address.includes(searchTerm) || contact.includes(searchTerm) || status.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});