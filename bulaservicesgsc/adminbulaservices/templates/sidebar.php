<link href="./styles/sidebar.css" rel="stylesheet">
<script src="./scripts/sidebar.js"></script>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2>Barangay Bula</h2>
        </div>
        
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="sidebar-search" placeholder="Search menu..." onkeyup="searchMenu()">
        </div>
        
        <div class="sidebar-menu">
            <!-- Dashboard -->
            <div class="menu-item" onclick="toggleSubmenu('dashboard')">
                <a href="#">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                    <i class="fas fa-chevron-down" id="dashboard-arrow"></i>
                </a>
                <ul class="submenu" id="dashboard-submenu">
                    <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Overview</a></li>
                    <li><a href="activity_planning.php"><i class="fas fa-calendar-check"></i> Activities</a></li>
                </ul>
            </div>
            
            <!-- People Management -->
            <div class="menu-item" onclick="toggleSubmenu('people')">
                <a href="#">
                    <i class="fas fa-users"></i>
                    <span>People Management</span>
                    <i class="fas fa-chevron-down" id="people-arrow"></i>
                </a>
                <ul class="submenu" id="people-submenu">
                    <li><a href="Officials_record.php"><i class="fa-solid fa-user-tie"></i> Officials</a></li>
                    <li><a href="residence_Records.php"><i class="fas fa-address-book"></i> Residents</a></li>
                    <li><a href="user_management.php"><i class="fa-solid fa-user-gear"></i> System Users</a></li>
                </ul>
            </div>
            
            <!-- Documents -->
            <div class="menu-item" onclick="toggleSubmenu('documents')">
                <a href="#">
                    <i class="fas fa-file-alt"></i>
                    <span>Documents</span>
                    <i class="fas fa-chevron-down" id="documents-arrow"></i>
                </a>
                <ul class="submenu" id="documents-submenu">
                    <li><a href="Requestform.php"><i class="fas fa-file-download"></i> Requests</a></li>
                </ul>
            </div>
            
            <!-- Certificates -->
            <div class="menu-item" onclick="toggleSubmenu('certificates')">
                <a href="#">
                    <i class="fas fa-certificate"></i>
                    <span>Certificates</span>
                    <i class="fas fa-chevron-down" id="certificates-arrow"></i>
                </a>
                <ul class="submenu" id="certificates-submenu">
                    <li><a href="resident_certificate.php"><i class="fas fa-file"></i> Residency</a></li>
                    <li><a href="Certificate_of_indigency.php"><i class="fas fa-file-invoice"></i> Indigency</a></li>
                    <li><a href="barangay_certificate.php"><i class="fa-solid fa-certificate"></i> Barangay</a></li>
                    <li><a href="cedula.php"><i class="fas fa-file-signature"></i> Community Tax</a></li>
                    <li><a href="low_income_certificate.php"><i class="fas fa-file-medical"></i> Low Income</a></li>
                    <li><a href="ivs_request.php"><i class="fas fa-file-contract"></i> IVS Request</a></li>
                    <li><a href="barangay_businesspermit.php"><i class="fas fa-file-signature"></i> Business Permit</a></li>
                    <li><a href="proof_of_income_certificate.php"><i class="fas fa-file-invoice-dollar"></i> Proof of Income</a></li>
                </ul>
            </div>
            
            <!-- Facilities -->
            <div class="menu-item" onclick="toggleSubmenu('facilities')">
                <a href="#">
                    <i class="fas fa-building"></i>
                    <span>Facilities</span>
                    <i class="fas fa-chevron-down" id="facilities-arrow"></i>
                </a>
                <ul class="submenu" id="facilities-submenu">
                    <li><a href="facility_reserve.php"><i class="fas fa-calendar-plus"></i> Reservations</a></li>
                    <li><a href="Gymnasium-Reservation.php"><i class="fa-solid fa-dumbbell"></i> Gymnasium</a></li>
                    <li><a href="Gymnasium-Equipment.php"><i class="fa-solid fa-screwdriver-wrench"></i> Equipment</a></li>
                </ul>
            </div>
            
            <!-- Financial -->
            <div class="menu-item" onclick="toggleSubmenu('financial')">
                <a href="#">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Financial</span>
                    <i class="fas fa-chevron-down" id="financial-arrow"></i>
                </a>
                <ul class="submenu" id="financial-submenu">
                    <li><a href="cashier.php"><i class="fa-solid fa-cash-register"></i> Cashier</a></li>
                    <li><a href="revenue.php"><i class="fas fa-money-bill"></i> Revenue</a></li>
                </ul>
            </div>
            
            <!-- Reports -->
            <div class="menu-item" onclick="toggleSubmenu('reports')">
                <a href="#">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                    <i class="fas fa-chevron-down" id="reports-arrow"></i>
                </a>
                <ul class="submenu" id="reports-submenu">
                    <li><a href="barangay-reporting&analytics.php"><i class="fa-solid fa-chart-pie"></i> Barangay</a></li>
                    <li><a href="service-request-reports.php"><i class="fas fa-file-invoice"></i> Service Requests</a></li>
                    <li><a href="gymnasium-usage.php"><i class="fa-solid fa-school"></i> Gymnasium Usage</a></li>
                    <li><a href="Financial-Reports&Income-Reports.php"><i class="fa-solid fa-coins"></i> Financial</a></li>
                </ul>
            </div>

            <!-- Editable Forms -->
            <div class="menu-item" onclick="toggleSubmenu('editable-forms')">
                <a href="#">
                    <i class="fas fa-edit"></i>
                    <span>Editable Forms</span>
                    <i class="fas fa-chevron-down" id="editable-forms-arrow"></i>
                </a>
                <ul class="submenu" id="editable-forms-submenu">
                    <li><a href="IVS_form.php"><i class="fas fa-file-signature"></i> IVS form</a></li>
                    <li><a href="BC_form.php"><i class="fas fa-file-signature"></i> BC form</a></li>
                    <li><a href="BP_form.php"><i class="fas fa-file-signature"></i> BP form</a></li>
                    <li><a href="CEDULA_form.php"><i class="fas fa-file-signature"></i> CEDULA form</a></li>
                    <li><a href="COI_form.php"><i class="fas fa-file-signature"></i> COI form</a></li>
                    <li><a href="COR_form.php"><i class="fas fa-file-signature"></i> COR form</a></li>
                    <li><a href="POIC_form.php"><i class="fas fa-file-signature"></i> POIC form</a></li>
                    <li><a href="LIC_form.php"><i class="fas fa-file-signature"></i> LIC form</a></li>
                    <li><a href="L&F_form.php"><i class="fas fa-file-signature"></i> L&F form</a></li>
                    <li><a href="GYM_form.php"><i class="fas fa-file-signature"></i> GYM form</a></li>
                </ul>
            </div>
            
            <!-- System -->
            <div class="menu-item" onclick="toggleSubmenu('system')">
                <a href="#">
                    <i class="fas fa-cog"></i>
                    <span>System</span>
                    <i class="fas fa-chevron-down" id="system-arrow"></i>
                </a>
                <ul class="submenu" id="system-submenu">
                    <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
                    <li><a href="#" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
