<link rel="stylesheet" href="./styles/topbar.css">

   <!-- Top bar -->
   <header class="topbar">
        <div class="logo">
            <img src="images/bula_logo.png" alt="Barangay Bula Logo">
        </div>
        <div class="clock">00:00:00 PM</div>
        <div class="header-actions">
            <!-- Notification Button -->
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </div>
            <!-- Existing User Profile -->
            <div class="user-profile">
                <img src="images/profile.png" alt="User" id="profile-icon">
                <div class="dropdown-menu" id="dropdown">
                    <p>Barangay Bula</p>
                    <hr>
                    <ul>
                        <li><a href="../userbulaservices/index.php">🔗 Go to Website</a></li>
                        <li><a href="change-username.php">✏️ Change Username</a></li>
                        <li><a href="change-password.php">🔒 Change Password</a></li>
                        <li><a href="settings.php">⚙️ Barangay Settings</a></li>
                        <li><a href="activity-log.php">📜 Activity Log</a></li>
                        <li class="logout">🚪 Logout</li>
                    </ul>
                </div>
            </div>
        </div>
   </header>

<script src="/scripts/topbar.js"></script>