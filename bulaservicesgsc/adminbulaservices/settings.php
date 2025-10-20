<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - Account Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./css/settings.css
    ">
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
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Account Settings</a>
                    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</
                    <div class="dropdown-divider"></div>
                    <button id="logout-btn"><i class="fas fa-sign-out-alt"></i> Log Out</button>
                </div>
            </div>
        </div>
    </header>

    <main class="dashboard-container container">
        <div class="page-header">
            <h1 class="page-title">
                <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
                Account Settings
            </h1>
        </div>

        <div class="card">
            <h2 class="card-title">Profile Settings</h2>
            <form id="profile-form">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" value="Admin User" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="admin@barangaybula.gov.ph" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" value="+63 912 345 6789">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">Security Settings</h2>
            <div class="form-group">
                <label class="form-label">Password</label>
                <button class="btn btn-primary" id="change-password-btn"><i class="fas fa-key"></i> Change Password</button>
            </div>
            <div class="form-group">
                <label class="form-label">Two-Factor Authentication</label>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span>Disabled</span>
                    <button class="btn btn-sm" id="toggle-2fa-btn"><i class="fas fa-toggle-off"></i> Enable</button>
                </div>
            </div>
        </div>
    </main>

    <!-- Logout Modal -->
    <div class="modal" id="logout-modal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
            <h3>Logging Out</h3>
            <p>You are being securely logged out of the system...</p>
            <div class="spinner"><i class="fas fa-circle-notch fa-spin"></i></div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal" id="password-modal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-key"></i></div>
            <h3>Change Password</h3>
            <form id="password-form">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
            </form>
        </div>
    </div>

    <footer class="app-footer">
        <div class="container"><p>Barangay Bula Management System © 2025</p></div>
    </footer>

    <script src="./script/settings.js"></script>
</body>
</html>
