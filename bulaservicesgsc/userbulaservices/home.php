<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';
require_once __DIR__ . '/server/file_urls.php';

ensureUserAccess();

// (Leave verbose error_mode to your preference)
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

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
    <!-- Used by home.js to normalize image URLs if needed -->
    <meta name="uploads-origin" content="https://admin.bulaservicesgsc.com">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - Modern Services Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
    <link rel="stylesheet" href="./style/home.css">
</head>
<body>
    <!-- Modern Navbar -->
    <nav class="navbar">
        <div class="logo">
            <img src="./pics/logo.png" alt="Barangay Logo">
            <span class="logo-text">Barangay Bula</span>
        </div>
        <ul class="nav-links">
            <li><a href="track.php">My Request</a></li>
            <li><a href="home.php">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About</a></li>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Barangay Services at Your Fingertips</h1>
            <p>Request documents, reserve facilities, and access barangay services anytime, anywhere with our modern online portal</p>
            <a href="#services" class="cta-button">Explore Services</a>
        </div>
    </section>

    <!-- Announcements Carousel (hydrated by home.js) -->
    <section class="carousel" id="annCarousel" aria-label="Barangay announcements" style="display:none;">
      <div class="carousel-inner">
        <div class="carousel-images"></div>
        <button class="prev" aria-label="Previous slide"><i class="fas fa-chevron-left"></i></button>
        <button class="next" aria-label="Next slide"><i class="fas fa-chevron-right"></i></button>
        <!-- subtle overlay so controls are legible on any background -->
        <div class="carousel-overlay"></div>
      </div>
      <div class="carousel-dots"></div>
    </section>


        <!-- Services Section -->
    <section class="services" id="services">
    <h3 class="section-subtitle">Primary Documents</h3>
    <div class="services-grid">
        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-file-alt"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="bc" aria-label="Barangay Clearance price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Barangay Clearance</h3>
            <p>Official document certifying residency and good standing in the barangay</p>
            <a href="bc.php" class="service-link">Request Now</a>
        </div>
        </div>

        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-briefcase"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="bp" aria-label="Business Clearance price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Business Clearance</h3>
            <p>Required document for operating businesses within the barangay</p>
            <a href="bp.php" class="service-link">Request Now</a>
        </div>
        </div>

        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-id-card"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="cedula" aria-label="Cedula price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Community Tax Certificate (Cedula)</h3>
            <p>Proof of payment of community tax for various transactions</p>
            <a href="cedula.php" class="service-link">Request Now</a>
        </div>
        </div>

        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-file-signature"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="ivs" aria-label="IVS price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Individual Voluntary Statement</h3>
            <p>Official sworn statement for various legal purposes</p>
            <a href="ivs.php" class="service-link">Request Now</a>
        </div>
        </div>
    </div>

    <h3 class="section-subtitle" style="margin-top: 3rem;">Additional Certificates</h3>
    <div class="services-grid">
        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-hands-helping"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="indigency" aria-label="Indigency price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Certificate of Indigency</h3>
            <p>Document certifying financial status for availing government assistance</p>
            <a href="coi.php" class="service-link">Request Now</a>
        </div>
        </div>

        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-home"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="residency" aria-label="Residency price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Certificate of Residency</h3>
            <p>Proof of residence within the barangay jurisdiction</p>
            <a href="cor.php" class="service-link">Request Now</a>
        </div>
        </div>

        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-money-bill-wave"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="lic" aria-label="Low Income Cert price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Low Income Certificate</h3>
            <p>Certification of income level for social welfare programs</p>
            <a href="lic.php" class="service-link">Request Now</a>
        </div>
        </div>

        <div class="service-card">
        <div class="service-icon">
            <i class="fas fa-file-invoice-dollar"></i>
            <!-- NEW -->
            <span class="price-badge" data-price-key="pic" aria-label="Proof of Income Cert price">₱—</span>
        </div>
        <div class="service-content">
            <h3>Proof of Income Certificate</h3>
            <p>Official document verifying income for loan and other applications</p>
            <a href="pic.php" class="service-link">Request Now</a>
        </div>
        </div>
    </div>

    <!-- Facility Services Section -->
    <div class="facility-services">
        <h3 class="section-subtitle">Facility Services</h3>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">
                <i class="fas fa-clipboard-list"></i>
                <!-- dashboard has no price -->
                </div>
                <div class="service-content">
                <h3>My Requests Dashboard</h3>
                <p>View all your submitted requests and check their current status</p>
                <a href="track.php" class="service-link">View My Requests</a>
                </div>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-warehouse"></i>
                    <!-- NEW stacked badges with time ranges -->
                    <div class="price-badge-stack">
                        <span class="price-badge" data-price-key="gym_morning">₱—</span>
                        <span class="price-badge" data-price-key="gym_evening">₱—</span>
                    </div>
                </div>
                <div class="service-content">
                    <h3>Gym Reservation</h3>
                    <p>Book our barangay gym facilities for your events or workouts</p>
                    <a href="gym.php" class="service-link">Reserve Now</a>
                </div>
            </div>
        </div>
    </div>
    </section>

    <section class="about" id="about" style="background-color: #ffffff; padding: 4rem 1rem;"></section>
    <section class="about" style="background-color: #ffffff; padding: 4rem 1rem;">
        <div class="about-container" style="max-width: 1200px; margin: 0 auto;">
            <h2 class="section-title" style="text-align: center; margin-bottom: 3rem; font-size: 1.75rem; color: #1e293b; position: relative;">
                About Barangay Bula
                <span style="content: ''; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 80px; height: 4px; background: linear-gradient(to right, #2563eb, #f59e0b); border-radius: 2px;"></span>
            </h2>
            
            <div class="about-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: center;">
                <div class="about-text">
                    <h3 style="font-size: 1.5rem; color: #2563eb; margin-bottom: 1rem;">Our Community</h3>
                    <p style="margin-bottom: 1.5rem; color: #64748b;">
                        Barangay Bula is a vibrant community in General Santos City known for its rich cultural heritage and progressive initiatives. 
                        Established in 1959, our barangay has grown into a model community with over 34,000 residents.
                    </p>
                    <p style="margin-bottom: 1.5rem; color: #64748b;">
                        We pride ourselves on our commitment to digital transformation in local governance, making government services more accessible 
                        to all residents through this modern online portal.
                    </p>
                    
                    <h3 style="font-size: 1.5rem; color: #2563eb; margin-bottom: 1rem; margin-top: 2rem;">Our Mission</h3>
                    <p style="margin-bottom: 1.5rem; color: #64748b;">
                        to achieve self-reliance through the implementation of policies and programs that promote infrastructure development, economic growth, social progress, and effective governance
                    </p>
                    
                    <div class="stats" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-top: 2rem;">
                        <div class="stat-item" style="text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; color: #2563eb;">34K+</div>
                            <div style="color: #64748b;">Residents</div>
                        </div>
                        <div class="stat-item" style="text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; color: #2563eb;">25</div>
                            <div style="color: #64748b;">Puroks</div>
                        </div>
                        <div class="stat-item" style="text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; color: #2563eb;">66</div>
                            <div style="color: #64748b;">Years</div>
                        </div>
                        <div class="stat-item" style="text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: 700; color: #2563eb;">100+</div>
                            <div style="color: #64748b;">Programs</div>
                        </div>
                    </div>
                </div>
                
                <div class="about-image" style="border-radius: 1rem; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                    <img src="./pics/barangay-hall.jpg" alt="Barangay Bula Hall" style="width: 100%; height: auto; display: block;">
                </div>
            </div>
            
            <div class="officials" style="margin-top: 4rem;">
                <h3 style="text-align: center; font-size: 1.5rem; color: #2563eb; margin-bottom: 2rem;">Our Barangay Officials</h3>
                
                <div class="officials-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 2rem;">
                    <div class="official-card" style="background-color: #f8fafc; border-radius: 0.5rem; padding: 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin: 0 auto 1rem; border: 3px solid #2563eb;">
                            <img src="./pics/captain.jpg" alt="Barangay Captain" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <h4 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.5rem;">Hon. Nicanora T. Vargas</h4>
                        <p style="color: #2563eb; font-weight: 600; margin-bottom: 0.5rem;">Barangay Captain</p>
                        <p style="color: #64748b; font-size: 0.9rem;"></p>
                    </div>
                    
                    <div class="official-card" style="background-color: #f8fafc; border-radius: 0.5rem; padding: 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin: 0 auto 1rem; border: 3px solid #2563eb;">
                            <img src="./pics/kagawad1.jpg" alt="Barangay Kagawad" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <h4 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.5rem;">Hon. Roel L. Granfon</h4>
                        <p style="color: #2563eb; font-weight: 600; margin-bottom: 0.5rem;">Kagawad</p>
                        <p style="color: #64748b; font-size: 0.9rem;"></p>
                    </div>
                    
                    <div class="official-card" style="background-color: #f8fafc; border-radius: 0.5rem; padding: 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin: 0 auto 1rem; border: 3px solid #2563eb;">
                            <img src="./pics/kagawad2.jpg" alt="Barangay Kagawad" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <h4 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.5rem;">Hon. Dante C. Granada</h4>
                        <p style="color: #2563eb; font-weight: 600; margin-bottom: 0.5rem;">Kagawad</p>
                        <p style="color: #64748b; font-size: 0.9rem;"></p>
                    </div>
                    
                    <div class="official-card" style="background-color: #f8fafc; border-radius: 0.5rem; padding: 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin: 0 auto 1rem; border: 3px solid #2563eb;">
                            <img src="./pics/secretary.jpg" alt="Barangay Secretary" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <h4 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.5rem;">Mr. Jhon Kyle S. Cerilla LPT</h4>
                        <p style="color: #2563eb; font-weight: 600; margin-bottom: 0.5rem;">Barangay Secretary</p>
                        <p style="color: #64748b; font-size: 0.9rem;"></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Contact & Location Section -->
    <section class="contact">
        <div class="contact-container">
            <div class="contact-info">
                <h2>Visit Barangay Bula</h2>
                <p>Find our location and get in touch with us through any of these channels:</p>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Address</h3>
                        <p>453V+CFW, Edilberto Lopez Sr. St, General Santos City (Dadiangas), 9500 South Cotabato</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Phone</h3>
                        <p><a href="tel:+639123456789">(083) 552-9692</a> / <a href="tel:+639987654321">+63 912 345 6789</a></p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Email</h3>
                        <p><a href="mailto:support@bulaservicesgsc.com">support@bulaservicesgsc.com</a></p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Office Hours</h3>
                        <p>Monday to Friday: 8:00 AM - 5:00 PM</p>
                        <p>Saturday: 8:00 AM - 12:00 PM</p>
                    </div>
                </div>

                <div class="map-actions">
                    <button class="map-action-btn" onclick="getDirections()">
                        <i class="fas fa-route"></i>
                        Get Directions
                    </button>
                    <button class="map-action-btn" onclick="openInGoogleMaps()">
                        <i class="fas fa-external-link-alt"></i>
                        Open in Google Maps
                    </button>
                </div>
            </div>
            
            <div class="map-container">
                <div id="barangayMap"></div>
                <div class="map-overlay">
                    <div class="map-marker">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="map-info">
                        <h3>Barangay Bula Hall</h3>
                        <p>General Santos City</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
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
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    <!-- Cache-busted JS -->
    <script src="./script/home.js?v=2025-10-11-2"></script>
</body>
</html>
