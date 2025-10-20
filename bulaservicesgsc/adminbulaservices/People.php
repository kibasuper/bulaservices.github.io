<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - People Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./css/people.css">
</head>
<body>
    <header class="app-header">
        <div class="container header-content">
            <h1>
                <i class="fas fa-city"></i>
                Barangay Bula Admin
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
                <h1 class="page-title">Barangay Officials</h1>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">People Management</a></li>
                    <li class="breadcrumb-item active">Officials</li>
                </ul>
            </div>
            <button class="btn btn-primary" id="add-official-btn">
                <i class="fas fa-plus"></i> Add Official
            </button>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Officials List</h2>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search officials..." id="search-officials">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="officials-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Contact</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://via.placeholder.com/40" class="avatar" alt="Juan Dela Cruz">
                                        <span>Juan Dela Cruz</span>
                                    </div>
                                </td>
                                <td>Barangay Captain</td>
                                <td>09123456789</td>
                                <td>2025-2025</td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-official" data-id="1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-official" data-id="1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-official" data-id="1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://via.placeholder.com/40" class="avatar" alt="Maria Santos">
                                        <span>Maria Santos</span>
                                    </div>
                                </td>
                                <td>Barangay Secretary</td>
                                <td>09123456788</td>
                                <td>2025-2025</td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-official" data-id="2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-official" data-id="2">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-official" data-id="2">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://via.placeholder.com/40" class="avatar" alt="Pedro Reyes">
                                        <span>Pedro Reyes</span>
                                    </div>
                                </td>
                                <td>Barangay Treasurer</td>
                                <td>09123456787</td>
                                <td>2025-2025</td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-official" data-id="3">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-official" data-id="3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-official" data-id="3">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://via.placeholder.com/40" class="avatar" alt="Luzviminda Garcia">
                                        <span>Luzviminda Garcia</span>
                                    </div>
                                </td>
                                <td>Kagawad</td>
                                <td>09123456786</td>
                                <td>2025-2025</td>
                                <td><span class="badge badge-danger">Inactive</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary view-official" data-id="4">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary edit-official" data-id="4">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary delete-official" data-id="4">
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
                    Showing <span id="showing-start">1</span> to <span id="showing-end">4</span> of <span id="total-records">4</span> entries
                </div>
                <ul class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1" id="prev-page">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#" data-page="1">1</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#" id="next-page">Next</a>
                    </li>
                </ul>
            </div>
        </div>
    </main>

    <!-- Add Official Modal -->
    <div class="modal" id="add-official-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Official</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="official-form">
                    <div class="text-center mb-4">
                        <img src="https://via.placeholder.com/100" id="official-photo-preview" class="rounded-circle mb-2" width="100" height="100" style="object-fit: cover;" alt="Official Photo">
                        <input type="file" class="d-none" id="official-photo" accept="image/*">
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('official-photo').click()">
                            <i class="fas fa-camera me-1"></i> Upload Photo
                        </button>
                    </div>
                    <div class="form-group mb-3">
                        <label for="official-name">Full Name</label>
                        <input type="text" class="form-control" id="official-name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="official-position">Position</label>
                        <select class="form-control" id="official-position" required>
                            <option value="">Select Position</option>
                            <option value="Barangay Captain">Barangay Captain</option>
                            <option value="Kagawad">Kagawad</option>
                            <option value="SK Chairman">SK Chairman</option>
                            <option value="Barangay Secretary">Barangay Secretary</option>
                            <option value="Barangay Treasurer">Barangay Treasurer</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="term-start">Term Start</label>
                            <input type="number" class="form-control" id="term-start" placeholder="Year" required min="2000" max="2100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="term-end">Term End</label>
                            <input type="number" class="form-control" id="term-end" placeholder="Year" required min="2000" max="2100">
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="official-contact">Contact Number</label>
                        <input type="tel" class="form-control" id="official-contact" required pattern="[0-9]{11}">
                        <small class="text-muted">Format: 09123456789 (11 digits)</small>
                    </div>
                    <div class="form-group mb-3">
                        <label for="official-status">Status</label>
                        <select class="form-control" id="official-status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="On Leave">On Leave</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cancel-add-official">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-official">Save Official</button>
            </div>
        </div>
    </div>

    <!-- View Official Modal -->
    <div class="modal" id="view-official-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Official Details</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <img src="https://via.placeholder.com/150" id="view-official-photo" class="rounded-circle mb-2" width="150" height="150" style="object-fit: cover;" alt="Official Photo">
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Full Name</h5>
                        <p id="view-official-name">Juan Dela Cruz</p>
                    </div>
                    <div class="col-md-6">
                        <h5>Position</h5>
                        <p id="view-official-position">Barangay Captain</p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Contact Number</h5>
                        <p id="view-official-contact">09123456789</p>
                    </div>
                    <div class="col-md-6">
                        <h5>Status</h5>
                        <p><span class="badge badge-success" id="view-official-status">Active</span></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <h5>Term</h5>
                        <p id="view-official-term">2025-2025</p>
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
                <p>Are you sure you want to delete this official? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cancel-delete">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete">Delete Official</button>
            </div>
        </div>
    </div>

    <script src="./script/people.js"></script>
</body>
</html>