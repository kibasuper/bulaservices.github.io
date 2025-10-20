<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./css/profile.css">
</head>
<body>
    <header class="app-header">
        <div class="container header-content">
            <h1>
                <i class="fas fa-city"></i>
                Barangay Bula Admin
            </h1>
            <div class="user-menu">
                <button class="user-menu-btn" id="user-menu-btn" aria-expanded="false" aria-haspopup="true">
                    <span>Admin User</span>
                    <i class="fas fa-user-circle"></i>
                </button>
                <div class="dropdown-menu" id="dropdown-menu">
                    <a href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> Account Settings
                    </a>
                    <a href="notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                    <a href="support.php">
                        <i class="fas fa-question-circle"></i> Help & Support
                    </a>
                    <div class="dropdown-divider"></div>
                    <button id="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="dashboard-container container">
        <div class="page-header">
            <h1 class="page-title">
                <a href="admin.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                My Profile
            </h1>
        </div>

        <div class="card">
            <h2 class="card-title">Personal Information</h2>
            <div class="flex" style="gap: 2rem; margin-bottom: 1.5rem;">
                <div style="flex: 1;">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=2563eb&color=fff&size=120" 
                         alt="Profile Picture" 
                         style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-light);">
                </div>
                <div style="flex: 3;">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <p>Admin User</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <p>admin@barangaybula.gov.ph</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <p>Administrator</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Contact Information</h2>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <p>+63 912 345 6789</p>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <p>Barangay Hall, Bula, General Santos City</p>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Account Security</h2>
            <button class="btn btn-primary">
                <i class="fas fa-key"></i> Change Password
            </button>
        </div>
    </main>

    <!-- Logout Modal -->
    <div class="modal" id="logout-modal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h3>Logging Out</h3>
            <p>You are being securely logged out of the system...</p>
            <div class="spinner">
                <i class="fas fa-circle-notch fa-spin"></i>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <div class="container">
            <p>Barangay Bula Management System © 2025</p>
        </div>
    </footer>

    <script src="./script/profile.js"></script>
</body>
</html>
