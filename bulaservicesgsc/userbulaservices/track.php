<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/certificate_functions.php';
require_once __DIR__ . '/server/file_urls.php';

ensureUserAccess();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Current user (safe defaults)
$currentUser = [
    'id'    => $_SESSION['user_id']   ?? null,
    'name'  => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email']?? '',
    'profilePic' => null,
];

// Pull profile_picture from DB and normalize it
try {
    if (!empty($currentUser['id'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbPath = $row['profile_picture'] ?: null;
            $currentUser['profilePic'] = $dbPath ? user_upload_url($dbPath) : null;
        }
    }
} catch (Throwable $e) {
    error_log("home.php profile fetch: " . $e->getMessage());
}

// Final URL used by <img>
$picUrl = $currentUser['profilePic'] ?: './pics/profile-placeholder.jpg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="uploads-origin" content="https://admin.bulaservicesgsc.com">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Barangay Bula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="./style/track.css">
    <link rel="stylesheet" href="./style/home.css">
</head>
<body>
    <!-- Modern Navbar (Same as home.php) -->
    <nav class="navbar">
        <div class="logo">
            <img src="./pics/logo.png" alt="Barangay Logo">
            <span class="logo-text">Barangay Bula</span>
        </div>
        <ul class="nav-links">
            <li><a href="track.php" class="active">My Request</a></li>
            <li><a href="home.php">Home</a></li>
            <li><a href="home.php#services">Services</a></li>
            <li><a href="home.php#about">About</a></li>
            <li class="profile-dropdown">
                <button class="profile-btn">
                    <img src="<?= htmlspecialchars($picUrl, ENT_QUOTES) ?>" alt="Profile" class="profile-pic">
                    <span class="user-name"><?= htmlspecialchars((string)$currentUser['name'], ENT_QUOTES) ?></span>
                </button>
                <div class="dropdown-content" id="dropdownMenu">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="track.php" class="dropdown-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>My Requests</span>
                    </a>
                    <a href="terms.php" class="dropdown-item">
                        <i class="fas fa-info-circle"></i>
                        <span>Terms & Privacy Policy</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item" id="logoutLink" onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Log Out</span>
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Tracker Content -->
    <div class="tracker-container">
        <div class="tracker-header">
            <h1>My Requests</h1>
            <div class="request-count">Showing <strong>0</strong> requests</div>
        </div>

        <div class="tracker-controls">
            <div class="filter-group">
                <label for="sort-by">Sort by:</label>
                <select id="sort-by">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="type-az">Request Type (A-Z)</option>
                    <option value="type-za">Request Type (Z-A)</option>
                    <option value="status">Status</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-status">Filter by:</label>
                <select id="filter-status">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="search-box">
                <i class="fas fa-search"></i>
                <input id="search-input" type="text" placeholder="Search requests...">
            </div>
        </div>

        <div class="requests-list" id="requests-list">
            <!-- Requests will be dynamically inserted here -->
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="social-links">
                <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
            </div>
            <p class="copyright">© 2025 Barangay Bula. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Profile dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const profileBtn = document.querySelector('.profile-btn');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            if (profileBtn && dropdownMenu) {
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    dropdownMenu.classList.remove('show');
                });
            }
            
            // Logout confirmation dialog
            window.confirmLogout = function(event) {
                event.preventDefault();
                
                // Create dialog
                const dialog = document.createElement('div');
                dialog.className = 'logout-dialog';
                dialog.innerHTML = `
                    <div class="logout-dialog-content">
                        <div class="logout-dialog-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <h3>Confirm Logout</h3>
                        <p>Are you sure you want to log out of your account?</p>
                        <div class="logout-dialog-buttons">
                            <button class="logout-dialog-btn logout-dialog-btn-cancel">Cancel</button>
                            <button class="logout-dialog-btn logout-dialog-btn-confirm">Log Out</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(dialog);
                
                // Button handlers
                dialog.querySelector('.logout-dialog-btn-cancel').addEventListener('click', function() {
                    document.body.removeChild(dialog);
                });
                
                dialog.querySelector('.logout-dialog-btn-confirm').addEventListener('click', function() {
                    window.location.href = 'index.php';
                });
                
                // Close on backdrop click
                dialog.addEventListener('click', function(e) {
                    if (e.target === dialog) {
                        document.body.removeChild(dialog);
                    }
                });
            };
        });
    </script>

    <script>
        // just in case you want name in JS later
        window.USER_NAME = <?= json_encode($fullName) ?>;
    </script>
    <script src="./script/track.js"></script>
</body>
</html>