<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./css/notification.css">
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
                        <i class="fas fa-user"></i>
                        My Profile
                    </a>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i>
                        Account Settings
                    </a>
                    <a href="notifications.php">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                    <a href="support.php">
                        <i class="fas fa-question-circle"></i>
                        Help & Support
                    </a>
                    <div class="dropdown-divider"></div>
                    <button id="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Log Out
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
                Notifications
            </h1>
            <button class="btn btn-sm" id="mark-all-read-btn">
                <i class="fas fa-check-circle"></i> Mark all as read
            </button>
        </div>

        <div class="card">
            <div class="notification-item notification-unread" data-id="1">
                <div class="notification-icon">
                    <i class="fas fa-file-certificate"></i>
                </div>
                <div>
                    <h3>New Barangay Clearance Request</h3>
                    <p>Juan Dela Cruz submitted a new request for barangay clearance.</p>
                    <small>10 minutes ago</small>
                </div>
            </div>

            <div class="notification-item" data-id="2">
                <div class="notification-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h3>New Resident Registration</h3>
                    <p>Maria Santos has completed her resident registration.</p>
                    <small>2 hours ago</small>
                </div>
            </div>

            <div class="notification-item" data-id="3">
                <div class="notification-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div>
                    <h3>System Maintenance</h3>
                    <p>Scheduled maintenance on June 15, 2025 from 10:00 PM to 2:00 AM</p>
                    <small>Yesterday</small>
                </div>
            </div>

            <div class="notification-item" data-id="4">
                <div class="notification-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3>Password Expiry Notice</h3>
                    <p>Your password will expire in 7 days. Please change your password soon.</p>
                    <small>2 days ago</small>
                </div>
            </div>
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

    <script src="./script/notification.js"></script>
</body>
</html>
