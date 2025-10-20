// Helper function to generate reference number
function generateReferenceNumber() {
    const now = new Date();
    const date = now.toLocaleDateString('en-CA', { 
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).replace(/-/g, '');
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `COI-${date}-${random}`;
}

// Calculate certificate fee based on number of copies (₱100 per copy)
function calculateFee() {
    const quantity = parseInt(document.getElementById('copyQuantity').value) || 1;
    const fee = quantity * 100;
    document.getElementById('calculatedFee').textContent = fee.toFixed(2);
}

// Handle document option selection
function selectDocumentOption(method) {
    document.getElementById(method + 'Option').checked = true;
    document.querySelectorAll('.document-option').forEach(option => {
        option.classList.remove('active');
        option.setAttribute('aria-pressed', 'false');
    });
    event.currentTarget.classList.add('active');
    event.currentTarget.setAttribute('aria-pressed', 'true');
    document.getElementById('uploadContainer').style.display = method === 'upload' ? 'block' : 'none';
    document.getElementById('hallInfo').style.display = method === 'hall' ? 'block' : 'none';
    document.getElementById('purokClearance').required = method === 'upload';
}

// Validate form section
function validateSection(sectionId) {
    let isValid = true;
    const section = document.getElementById(sectionId);
    
    // Validate required fields (skip readonly fields)
    const requiredInputs = section.querySelectorAll('[required]:not([readonly])');
    requiredInputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('has-error');
            const errorId = input.id + 'Error';
            if (document.getElementById(errorId)) {
                document.getElementById(errorId).style.display = 'block';
            }
            isValid = false;
        } else {
            input.classList.remove('has-error');
            const errorId = input.id + 'Error';
            if (document.getElementById(errorId)) {
                document.getElementById(errorId).style.display = 'none';
            }
        }
    });
    
    // Validate purpose "Other"
    if (sectionId === 'section2' && document.getElementById('purpose').value === 'Other') {
        if (!document.getElementById('otherPurpose').value.trim()) {
            document.getElementById('otherPurposeError').style.display = 'block';
            document.getElementById('otherPurpose').classList.add('has-error');
            isValid = false;
        }
    }
    
    // Validate document method
    if (sectionId === 'section3') {
        const documentMethod = document.querySelector('input[name="documentMethod"]:checked');
        if (!documentMethod) {
            document.getElementById('documentMethodError').style.display = 'block';
            isValid = false;
        } else if (documentMethod.value === 'upload' && !document.getElementById('purokClearance').files[0]) {
            document.getElementById('fileUploadError').style.display = 'block';
            isValid = false;
        }
    }
    
    return isValid;
}

// Initialize form when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Sample user data (would come from your system in real implementation)
    const currentUser = {
        fullName: 'Juan Dela Cruz',
        contactNumber: '09123456789',
        address: '123 Bula Street, Purok 5, Barangay Bula',
        yearOfStay: '2015'
    };
    
    // Set user data
    document.getElementById('fullName').value = currentUser.fullName;
    document.getElementById('contactNumber').value = currentUser.contactNumber;
    document.getElementById('address').value = currentUser.address;
    document.getElementById('yearOfStay').value = currentUser.yearOfStay;
    
    // Handle purpose selection
    document.getElementById('purpose').addEventListener('change', function() {
        const show = this.value === 'Other';
        document.getElementById('otherPurposeContainer').style.display = show ? 'block' : 'none';
        document.getElementById('otherPurpose').required = show;
    });
    
    // Handle file upload
    document.getElementById('purokClearance').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                document.getElementById('fileUploadError').textContent = 'File size exceeds 5MB limit';
                document.getElementById('fileUploadError').style.display = 'block';
                e.target.value = '';
                document.getElementById('fileName').textContent = 'No file chosen';
                return;
            }
            const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                document.getElementById('fileUploadError').textContent = 'Invalid file type. Please upload JPG, PNG, or PDF';
                document.getElementById('fileUploadError').style.display = 'block';
                e.target.value = '';
                document.getElementById('fileName').textContent = 'No file chosen';
                return;
            }
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileUploadError').style.display = 'none';
        } else {
            document.getElementById('fileName').textContent = 'No file chosen';
        }
    });
    
    // Handle copy quantity changes
    document.getElementById('copyQuantity').addEventListener('input', calculateFee);
    
    // Navigation between sections
    document.getElementById('nextBtn1').addEventListener('click', function() {
        if (validateSection('section1')) {
            document.getElementById('section1').classList.remove('active');
            document.getElementById('section2').classList.add('active');
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step1').classList.add('completed');
            document.getElementById('step2').classList.add('active');
            document.getElementById('section2').focus();
        }
    });
    
    document.getElementById('nextBtn2').addEventListener('click', function() {
        if (validateSection('section2')) {
            document.getElementById('section2').classList.remove('active');
            document.getElementById('section3').classList.add('active');
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step2').classList.add('completed');
            document.getElementById('step3').classList.add('active');
            document.getElementById('section3').focus();
        }
    });
    
    document.getElementById('prevBtn1').addEventListener('click', function() {
        document.getElementById('section2').classList.remove('active');
        document.getElementById('section1').classList.add('active');
        document.getElementById('step1').classList.add('active');
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2').classList.remove('completed');
        document.getElementById('section1').focus();
    });
    
    document.getElementById('prevBtn2').addEventListener('click', function() {
        document.getElementById('section3').classList.remove('active');
        document.getElementById('section2').classList.add('active');
        document.getElementById('step2').classList.add('active');
        document.getElementById('step3').classList.remove('active');
        document.getElementById('step3').classList.remove('completed');
        document.getElementById('section2').focus();
    });
    
    // Cancel button
    document.getElementById('cancelBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
            window.location.href = 'home.php';
        }
    });
    
    // Form submission
    document.getElementById('indigencyCertificateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!validateSection('section3')) return;
        
        const submitBtn = document.getElementById('submitApplication');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        
        setTimeout(function() {
            document.getElementById('referenceNumber').textContent = generateReferenceNumber();
            document.getElementById('successModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
        }, 1500);
    });
    
    // Modal close handlers
    function redirectHome() {
        document.getElementById('successModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        window.location.href = 'home.php';
    }
    
    document.querySelector('.close-modal').addEventListener('click', redirectHome);
    document.getElementById('closeModalBtn').addEventListener('click', redirectHome);
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('successModal')) {
            redirectHome();
        }
    });
    
    // Initialize fee calculator
    calculateFee();
});