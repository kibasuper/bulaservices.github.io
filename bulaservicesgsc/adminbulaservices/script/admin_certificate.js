// Admin Certificate Form JavaScript
document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const form = document.getElementById('adminCertificateForm');
    const sections = document.querySelectorAll('.form-section');
    const steps = document.querySelectorAll('.step');
    
    // Navigation buttons
    const nextBtn1 = document.getElementById('nextBtn1');
    const nextBtn2 = document.getElementById('nextBtn2');
    const prevBtn1 = document.getElementById('prevBtn1');
    const prevBtn2 = document.getElementById('prevBtn2');
    const cancelBtn = document.getElementById('cancelBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    // Form elements
    const purposeSelect = document.getElementById('purpose');
    const otherPurposeContainer = document.getElementById('otherPurposeContainer');
    const copyQuantity = document.getElementById('copyQuantity');
    const calculatedFee = document.getElementById('calculatedFee');
    
    // Review elements
    const reviewFullName = document.getElementById('reviewFullName');
    const reviewEmail = document.getElementById('reviewEmail');
    const reviewContact = document.getElementById('reviewContact');
    const reviewAddress = document.getElementById('reviewAddress');
    const reviewPurpose = document.getElementById('reviewPurpose');
    const reviewCopies = document.getElementById('reviewCopies');
    const reviewFee = document.getElementById('reviewFee');
    
    // Search elements
    const residentSearch = document.getElementById('residentSearch');
    const searchBtn = document.getElementById('searchBtn');
    const searchResults = document.getElementById('searchResults');
    
    let currentSection = 1;
    const CERTIFICATE_PRICE = window.CERTIFICATE_PRICE || 0;

    // Initialize
    updateFee();
    setupEventListeners();

    function setupEventListeners() {
        // Navigation
        nextBtn1.addEventListener('click', () => navigateTo(2));
        nextBtn2.addEventListener('click', () => navigateTo(3));
        prevBtn1.addEventListener('click', () => navigateTo(1));
        prevBtn2.addEventListener('click', () => navigateTo(2));
        cancelBtn.addEventListener('click', confirmCancel);
        
        // Form elements
        purposeSelect.addEventListener('change', handlePurposeChange);
        copyQuantity.addEventListener('input', updateFee);
        
        // Search
        searchBtn.addEventListener('click', searchResidents);
        residentSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchResidents();
            }
        });
        
        // Form submission
        form.addEventListener('submit', handleFormSubmit);
    }

    function navigateTo(sectionNumber) {
        // Validate current section before proceeding
        if (sectionNumber > currentSection && !validateCurrentSection()) {
            return;
        }
        
        // Update review section if going to section 3
        if (sectionNumber === 3) {
            updateReviewSection();
        }
        
        // Update UI
        sections.forEach(section => section.classList.remove('active'));
        steps.forEach(step => step.classList.remove('active'));
        
        document.getElementById(`section${sectionNumber}`).classList.add('active');
        document.getElementById(`step${sectionNumber}`).classList.add('active');
        
        currentSection = sectionNumber;
    }

    function validateCurrentSection() {
        const currentSectionEl = document.getElementById(`section${currentSection}`);
        const requiredFields = currentSectionEl.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = 'var(--danger-color)';
                
                // Remove error styling when user starts typing
                field.addEventListener('input', function () {
                    this.style.borderColor = 'var(--border-color)';
                }, { once: true });
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields');
        }
        
        return isValid;
    }

    function handlePurposeChange() {
        const isOther = purposeSelect.value === 'Other';
        otherPurposeContainer.style.display = isOther ? 'block' : 'none';
        
        if (isOther) {
            document.getElementById('purposeDetails').setAttribute('required', 'required');
        } else {
            document.getElementById('purposeDetails').removeAttribute('required');
        }
    }

    function updateFee() {
        const copies = parseInt(copyQuantity.value) || 1;
        const total = copies * CERTIFICATE_PRICE;
        calculatedFee.textContent = total.toFixed(2);
    }

    function updateReviewSection() {
        reviewFullName.textContent = document.getElementById('fullName').value;
        reviewEmail.textContent = document.getElementById('email').value;
        reviewContact.textContent = document.getElementById('contactNumber').value;
        reviewAddress.textContent = document.getElementById('address').value;
        
        const purpose = purposeSelect.value;
        const purposeDetails = document.getElementById('purposeDetails').value;
        reviewPurpose.textContent = purpose === 'Other' ? purposeDetails : purpose;
        
        const copies = parseInt(copyQuantity.value) || 1;
        reviewCopies.textContent = copies;
        reviewFee.textContent = `₱${(copies * CERTIFICATE_PRICE).toFixed(2)}`;
    }

    async function searchResidents() {
        const query = residentSearch.value.trim();
        if (!query) {
            alert('Please enter a search term');
            return;
        }
        
        try {
            searchBtn.disabled = true;
            searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
            
            const response = await fetch(`../php/search_residents.php?q=${encodeURIComponent(query)}`, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            
            if (data.success && data.residents.length > 0) {
                displaySearchResults(data.residents);
            } else {
                searchResults.innerHTML = '<div class="search-result-item">No residents found</div>';
                searchResults.style.display = 'block';
            }
        } catch (error) {
            console.error('Search error:', error);
            alert('Error searching residents');
        } finally {
            searchBtn.disabled = false;
            searchBtn.innerHTML = '<i class="fas fa-search"></i> Search';
        }
    }

    function displaySearchResults(residents) {
        searchResults.innerHTML = residents.map(resident => `
            <div class="search-result-item" onclick="selectResident(${JSON.stringify(resident).replace(/"/g, '&quot;')})">
                <strong>${resident.first_name} ${resident.last_name}</strong><br>
                <small>${resident.email} | ${resident.contact_number} | ${resident.address}</small>
            </div>
        `).join('');
        searchResults.style.display = 'block';
    }

    window.selectResident = function (resident) {
        document.getElementById('fullName').value = `${resident.first_name} ${resident.last_name}`;
        document.getElementById('email').value = resident.email || '';
        document.getElementById('contactNumber').value = resident.contact_number || '';
        document.getElementById('address').value = resident.address || '';
        
        searchResults.style.display = 'none';
        residentSearch.value = '';
    };

    async function handleFormSubmit(e) {
        e.preventDefault();
        
        if (!validateCurrentSection()) {
            return;
        }
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            
            const formData = new FormData(form);
            
            const response = await fetch('../php/admin_create_request.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success) {
                showSuccessModal(data.reference_number);
            } else {
                throw new Error(data.message || 'Failed to create request');
            }
        } catch (error) {
            console.error('Submission error:', error);
            alert(error.message || 'Error creating request');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Create Request';
        }
    }

    function showSuccessModal(referenceNumber) {
        const modal = document.getElementById('successModal');
        const refElement = document.getElementById('referenceNumber');
        
        refElement.textContent = `Reference: ${referenceNumber}`;
        modal.classList.add('active');
        
        document.getElementById('closeModalBtn').addEventListener('click', () => {
            window.location.href = 'request.php';
        });
    }

    function confirmCancel() {
        if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
            window.location.href = 'request.php';
        }
    }
});