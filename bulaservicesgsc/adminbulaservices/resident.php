<?php
require_once __DIR__ . '/server/config.php';
if (empty($_SESSION['admin_id'])) {
  header('Location: index.php');
  exit;
}

if ($_SESSION['admin_role'] !== 'superadmin') {
    header('Location: admin.php?denied=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - Resident Records</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./css/resident.css">
</head>
<body>
    <header class="app-header">
        <div class="container header-content">
            <h1>
                <i class="fas fa-users"></i>
                Barangay Bula - Resident Records
            </h1>
            <div class="header-actions">
                <a href="admin.php" class="dashboard-link">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </header>
    
    <main class="main-content container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Resident Records</h1>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">People Management</a></li>
                    <li class="breadcrumb-item active">Residents</li>
                </ul>
            </div>
            <button class="btn btn-primary" id="add-resident-btn">
                <i class="fas fa-plus"></i> Add Resident
            </button>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Residents List</h2>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search residents..." id="search-residents">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="residents-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Address</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <img src="https://via.placeholder.com/40" class="avatar" alt="Juan Dela Cruz">
                                </td>
                                <td>Juan Dela Cruz</td>
                                <td>35</td>
                                <td>Male</td>
                                <td>123 Bula Street</td>
                                <td>09123456789</td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-resident" data-id="1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-resident" data-id="1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-resident" data-id="1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <img src="https://via.placeholder.com/40" class="avatar" alt="Maria Santos">
                                </td>
                                <td>Maria Santos</td>
                                <td>28</td>
                                <td>Female</td>
                                <td>456 Bula Avenue</td>
                                <td>09123456788</td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-resident" data-id="2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-resident" data-id="2">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-resident" data-id="2">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <img src="https://via.placeholder.com/40" class="avatar" alt="Pedro Reyes">
                                </td>
                                <td>Pedro Reyes</td>
                                <td>42</td>
                                <td>Male</td>
                                <td>789 Bula Road</td>
                                <td>09123456787</td>
                                <td><span class="badge badge-warning">Inactive</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-resident" data-id="3">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-resident" data-id="3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-resident" data-id="3">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <img src="https://via.placeholder.com/40" class="avatar" alt="Luzviminda Garcia">
                                </td>
                                <td>Luzviminda Garcia</td>
                                <td>65</td>
                                <td>Female</td>
                                <td>321 Bula Lane</td>
                                <td>09123456786</td>
                                <td><span class="badge badge-danger">Deceased</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-resident" data-id="4">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-resident" data-id="4">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-resident" data-id="4">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="text-muted">
                    Showing 1 to 4 of 4 entries
                </div>
                <ul class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </ul>
            </div>
        </div>
    </main>

    <!-- Add Resident Modal -->
    <div class="modal" id="add-resident-modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>Add New Resident</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="resident-form">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <img src="https://via.placeholder.com/150" id="resident-photo-preview" class="rounded-circle mb-2" width="150" height="150" style="object-fit: cover;">
                            <input type="file" class="d-none" id="resident-photo" accept="image/*">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('resident-photo').click()">
                                <i class="fas fa-camera me-1"></i> Upload Photo
                            </button>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first-name">First Name</label>
                                    <input type="text" class="form-control" id="first-name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="middle-name">Middle Name</label>
                                    <input type="text" class="form-control" id="middle-name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last-name">Last Name</label>
                                    <input type="text" class="form-control" id="last-name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="suffix">Suffix</label>
                                    <select class="form-control" id="suffix">
                                        <option value="">None</option>
                                        <option value="Jr">Jr</option>
                                        <option value="Sr">Sr</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="birth-date">Date of Birth</label>
                            <input type="date" class="form-control" id="birth-date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="age">Age</label>
                            <input type="number" class="form-control" id="age" required min="0" max="120">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="gender">Gender</label>
                            <select class="form-control" id="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="civil-status">Civil Status</label>
                            <select class="form-control" id="civil-status" required>
                                <option value="">Select Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                                <option value="Divorced">Divorced</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact-number">Contact Number</label>
                            <input type="tel" class="form-control" id="contact-number" required pattern="[0-9]{11}">
                            <small class="text-muted">Format: 09123456789 (11 digits)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address">Complete Address</label>
                        <textarea class="form-control" id="address" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="occupation">Occupation</label>
                            <input type="text" class="form-control" id="occupation">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="voter-status">Voter Status</label>
                            <select class="form-control" id="voter-status">
                                <option value="Registered">Registered</option>
                                <option value="Not Registered">Not Registered</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="resident-status">Resident Status</label>
                            <select class="form-control" id="resident-status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Moved Out">Moved Out</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes">Notes/Remarks</label>
                        <textarea class="form-control" id="notes" rows="2"></textarea>
                    </div>
                    
                    <input type="hidden" id="edit-id" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cancel-add-resident">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-resident">Save Resident</button>
            </div>
        </div>
    </div>

    <!-- View Resident Modal -->
    <div class="modal" id="view-resident-modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>Resident Details</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="https://via.placeholder.com/150" id="view-resident-photo" class="rounded-circle mb-3" width="150" height="150" style="object-fit: cover;">
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <h6>Full Name</h6>
                                <p id="view-full-name">Juan Dela Cruz</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Date of Birth</h6>
                                <p id="view-birth-date">May 15, 1988</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Age</h6>
                                <p id="view-age">35</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Gender</h6>
                                <p id="view-gender">Male</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Civil Status</h6>
                                <p id="view-civil-status">Married</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Contact Number</h6>
                                <p id="view-contact-number">09123456789</p>
                            </div>
                            <div class="col-12 mb-2">
                                <h6>Address</h6>
                                <p id="view-address">123 Bula Street, Barangay Bula</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Email</h6>
                                <p id="view-email">-</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Occupation</h6>
                                <p id="view-occupation">Farmer</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Voter Status</h6>
                                <p id="view-voter-status">Registered</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <h6>Resident Status</h6>
                                <p id="view-resident-status"><span class="badge badge-success">Active</span></p>
                            </div>
                            <div class="col-12 mb-2">
                                <h6>Notes/Remarks</h6>
                                <p id="view-notes">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="close-view-modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="delete-confirm-modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this resident record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cancel-delete">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete">Delete Resident</button>
            </div>
        </div>
    </div>

    <script src="./script/resident.js"></script>
</body>
</html>