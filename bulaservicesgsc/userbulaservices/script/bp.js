// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Load user data from server
    fetch('./get_user_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Display user data
                document.getElementById('fullName').value = data.data.full_name;
                document.getElementById('contactNumber').value = data.data.contact_number;
                document.getElementById('address').value = data.data.address;
                document.getElementById('yearOfStay').value = data.data.year_of_stay;
            } else {
                alert('Failed to load user data: ' + data.message);
                window.location.href = 'home.php';
            }
        })
        .catch(error => {
            console.error('Error loading user data:', error);
            alert('Failed to load user data. Please try again.');
            window.location.href = 'home.php';
        });
    
    // ... rest of your existing JavaScript code ...
    
    // Handle form submission - UPDATED
    document.getElementById('businessPermitForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate final section
        if (!validateSection('section3')) {
            return;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitApplication');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        
        // Prepare form data
        const formData = new FormData();
        
        // Add form fields
        formData.append('business_name', document.getElementById('businessName').value);
        formData.append('business_type', document.getElementById('businessType').value);
        formData.append('business_address', document.getElementById('businessAddress').value);
        formData.append('purpose', document.getElementById('purpose').value);
        formData.append('copy_quantity', document.getElementById('copyQuantity').value);
        
        // Add optional fields
        if (document.getElementById('businessType').value === 'Other') {
            formData.append('other_business_type', document.getElementById('otherBusinessType').value);
        }
        
        if (document.getElementById('purpose').value === 'Other') {
            formData.append('other_purpose', document.getElementById('otherPurpose').value);
        }
        
        // Add clearance method
        const clearanceMethod = document.querySelector('input[name="clearanceMethod"]:checked').value;
        formData.append('clearance_method', clearanceMethod);
        
        // Add file if uploaded
        if (clearanceMethod === 'upload' && document.getElementById('purokClearance').files[0]) {
            formData.append('purok_clearance', document.getElementById('purokClearance').files[0]);
        }
        
        // Submit to server
        fetch('./submit_business_permit.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success modal with reference number
                document.getElementById('referenceNumber').textContent = data.reference_number;
                const successModal = document.getElementById('successModal');
                successModal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                alert('Submission failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            alert('An error occurred during submission. Please try again.');
        })
        .finally(() => {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
        });
    });
    
    // ... rest of your existing JavaScript code ...
});