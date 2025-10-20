<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost & Found Center Admin</title>
    <link rel="stylesheet" href="styles/L&F_form.css">
    
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo-btn">
                    <img src="images/bula_logo.png" alt="Dashboard" class="logo">
                </a>
                <h1>Lost & Found Center Admin</h1>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="admin-panel">
            <div class="sidebar">
                <h2>Current Forms</h2>
                <div class="sidebar-actions">
                    <button class="btn btn-primary btn-sm" id="add-form-btn">+ Add Form</button>
                    <button class="btn btn-danger btn-sm" id="delete-form-btn">Delete Form</button>
                </div>
                <ul>
                    <li><a href="BC_form.php">Barangay Clearance</a></li>
                    <li><a href="BP_form.php">Business Permit</a></li>
                    <li><a href="COR_form.php">Residency Certificate</a></li>
                    <li><a href="COI_form.php">Certificate of Indegency</a></li>
                    <li><a href="POIC_form.php">Proof of Income Certificate</a></li>
                    <li><a href="LIC_form.php">Low Income Certificate</a></li>
                    <li><a href="CEDULA_form.php">Cedula</a></li>
                    <li><a href="IVS_form.php">Individua Voluntary Statement</a></li>
                    <li><a href="L&F_form.php">Lost & Found</a></li>
                    <li><a href="GYM_form.php">GYM</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="form-header">
                    <h2>Lost & Found Center Form Builder</h2>
                    <button class="btn btn-primary" id="preview-btn">Preview Form</button>
                </div>
                
                <div class="form-builder">
                    <!-- Recently Reported Items Section -->
                    <div class="reported-items">
                        <h3>Recently Reported Items Preview</h3>
                        
                        <div class="item-card">
                            <span class="item-status">LOST</span>
                            <h4>Wallet</h4>
                            <p><strong>Location:</strong> Barangay Hall, Barangay City</p>
                            <p><strong>Description:</strong> Brown leather wallet with ID cards</p>
                            <p><strong>Date Reported:</strong> June 15, 2025</p>
                        </div>
                        
                        <div class="item-card">
                            <span class="item-status found">FOUND</span>
                            <h4>iPhone 11 Pro</h4>
                            <p><strong>Location:</strong> Market Area, Barangay City</p>
                            <p><strong>Description:</strong> Black iPhone with blue case</p>
                            <p><strong>Date Found:</strong> June 14, 2025</p>
                        </div>
                    </div>
                    
                    <!-- Report Lost Item Section -->
                    <div class="form-section" draggable="true" data-section-id="lost-item">
                        <h3>
                            Report Lost Item
                            <button class="btn btn-danger btn-sm remove-section">Remove Section</button>
                        </h3>
                        
                        <div class="form-field" draggable="true" data-field-id="item-name">
                            <label>Item Name*</label>
                            <input type="text" placeholder="e.g. Wallet, Phone, Keys">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="item-photo">
                            <label>Upload Photo (Optional)</label>
                            <div class="file-upload">
                                <input type="file" accept="image/*">
                            </div>
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="date-lost">
                            <label>Date Lost*</label>
                            <input type="date">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="location">
                            <label>Location in Barangay*</label>
                            <input type="text" placeholder="Where did you lose the item?">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="description">
                            <label>Description*</label>
                            <textarea placeholder="Any identifying features"></textarea>
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="contact-info">
                            <label>Contact Information*</label>
                            <input type="text" placeholder="Your phone number or email">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <button class="btn btn-primary add-field">+ Add Field</button>
                    </div>
                    
                    <!-- Report Found Item Section -->
                    <div class="form-section" draggable="true" data-section-id="found-item">
                        <h3>
                            Report Found Item
                            <button class="btn btn-danger btn-sm remove-section">Remove Section</button>
                        </h3>
                        
                        <div class="form-field" draggable="true" data-field-id="found-item-name">
                            <label>Item Name*</label>
                            <input type="text" placeholder="What did you find?">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="found-item-photo">
                            <label>Upload Photo (Optional)</label>
                            <div class="file-upload">
                                <input type="file" accept="image/*">
                            </div>
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="date-found">
                            <label>Date Found*</label>
                            <input type="date">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="found-location">
                            <label>Location in Barangay*</label>
                            <input type="text" placeholder="Where did you find the item?">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="owner-sign">
                            <label>Owner Identification</label>
                            <textarea placeholder="Describe how the owner can claim this item"></textarea>
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <div class="form-field" draggable="true" data-field-id="finder-contact">
                            <label>Your Contact Information*</label>
                            <input type="text" placeholder="Phone number or email">
                            <div class="form-field-actions">
                                <button class="btn btn-danger btn-sm remove-field">Remove</button>
                            </div>
                        </div>
                        
                        <button class="btn btn-primary add-field">+ Add Field</button>
                    </div>
                    
                    <button class="btn btn-primary" id="add-section-btn">+ Add New Section</button>
                    
                    <!-- Form Settings -->
                    <div class="form-settings">
                        <h3>Form Settings</h3>
                        
                        <div class="setting-item">
                            <label>
                                <input type="checkbox" checked> Form is active
                            </label>
                        </div>
                        
                        <div class="setting-item">
                            <label>
                                Auto-fill contact information:
                                <select>
                                    <option value="yes" selected>Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </label>
                        </div>
                        
                        <div class="setting-item">
                            <label>
                                Items retention period (days):
                                <input type="number" value="60" min="1">
                            </label>
                        </div>
                        
                        <div class="setting-item">
                            <label>
                                Notification email for claims:
                                <input type="email" placeholder="admin@barangay.gov.ph">
                            </label>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button class="btn btn-success" id="save-form">Save Form</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Structure -->
<div id="form-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2 id="modal-title">Modal Title</h2>
        <div id="modal-body">
            <!-- Content will be inserted here -->
        </div>
    </div>
</div>
    <script src="scripts/L&F_form.js"></script>
</body>
</html>