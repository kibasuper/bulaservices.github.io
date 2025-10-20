<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #5e72e4;
            --primary-dark: #3a4ab9;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #ffbe0b;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #1f2937;
            line-height: 1.6;
        }

        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1030;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            transition: var(--transition);
        }

        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand img {
            height: 36px;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: scale(1.1);
        }

        .admin-container {
            padding-top: 80px;
        }

        .admin-header {
            background-color: var(--primary);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .admin-nav-tabs .nav-link {
            font-weight: 500;
            padding: 0.75rem 1.25rem;
        }

        .stat-card {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .reservation-card {
            border-left: 4px solid;
            transition: var(--transition);
            margin-bottom: 1rem;
        }
        .reservation-card.pending { border-left-color: var(--warning); }
        .reservation-card.approved { border-left-color: var(--primary); }
        .reservation-card.paid { border-left-color: var(--success); }
        .reservation-card.rejected { border-left-color: var(--danger); }

        .badge-pending { background-color: var(--warning); }
        .badge-approved { background-color: var(--primary); }
        .badge-paid { background-color: var(--success); }
        .badge-rejected { background-color: var(--danger); }

        .user-status-active { color: var(--success); }
        .user-status-restricted { color: var(--danger); }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1.5rem 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <nav class="navbar">
        <a href="admin.php" class="navbar-brand">
            <img src="./pics/logs.png" alt="Barangay Logo">
            <span>Barangay Bula - Admin Dashboard</span>
        </a>
        <div>
            <button class="btn btn-outline-danger btn-sm" id="logoutBtn">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </button>
        </div>
    </nav>

    <!-- Main Admin Content -->
    <div class="container admin-container">
        <div class="admin-header">
            <h2><i class="fas fa-user-shield me-2"></i>Gym Reservation Management</h2>
            <p class="mb-0">Manage all gym reservations and user accounts</p>
        </div>

        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card bg-white">
                    <div class="stat-value text-primary" id="pendingCount">0</div>
                    <div class="stat-label">Pending Approvals</div>
                    <i class="fas fa-clock text-primary mt-2"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-white">
                    <div class="stat-value text-success" id="upcomingCount">0</div>
                    <div class="stat-label">Upcoming Reservations</div>
                    <i class="fas fa-calendar-check text-success mt-2"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-white">
                    <div class="stat-value text-info" id="todayCount">0</div>
                    <div class="stat-label">Today's Reservations</div>
                    <i class="fas fa-calendar-day text-info mt-2"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-white">
                    <div class="stat-value text-danger" id="problemCount">0</div>
                    <div class="stat-label">Problem Accounts</div>
                    <i class="fas fa-exclamation-triangle text-danger mt-2"></i>
                </div>
            </div>
        </div>

        <!-- Admin Tabs -->
        <ul class="nav nav-tabs admin-nav-tabs" id="adminTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#pendingTab">
                    <i class="fas fa-clock me-1"></i> Pending Approval
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#upcomingTab">
                    <i class="fas fa-calendar-alt me-1"></i> Upcoming
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#historyTab">
                    <i class="fas fa-history me-1"></i> History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#usersTab">
                    <i class="fas fa-users me-1"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#settingsTab">
                    <i class="fas fa-cog me-1"></i> Settings
                </a>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content p-3 border border-top-0 rounded-bottom bg-white">
            <!-- Pending Approvals Tab -->
            <div class="tab-pane fade show active" id="pendingTab">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pendingReservations">
                            <!-- Dynamically filled -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Upcoming Reservations Tab -->
            <div class="tab-pane fade" id="upcomingTab">
                <div class="mb-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-primary" data-filter="approved">Approved</button>
                        <button type="button" class="btn btn-outline-primary" data-filter="paid">Paid</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="upcomingReservations">
                            <!-- Dynamically filled -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- History Tab -->
            <div class="tab-pane fade" id="historyTab">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="date" class="form-control" id="historyDateFrom">
                    </div>
                    <div class="col-md-4">
                        <input type="date" class="form-control" id="historyDateTo">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" id="filterHistory">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="historyReservations">
                            <!-- Dynamically filled -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- User Management Tab -->
            <div class="tab-pane fade" id="usersTab">
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search users..." id="userSearch">
                        <button class="btn btn-primary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Reservations</th>
                                <th>Strikes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userManagementList">
                            <!-- Dynamically filled -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settingsTab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>System Settings</h5>
                            </div>
                            <div class="card-body">
                                <form id="systemSettings">
                                    <div class="mb-3">
                                        <label class="form-label">Gym Rate per Hour (₱)</label>
                                        <input type="number" class="form-control" value="200" min="50" step="50">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Operating Hours</label>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Opening Time</label>
                                                <select class="form-select">
                                                    <option>6:00 AM</option>
                                                    <option>7:00 AM</option>
                                                    <option>8:00 AM</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Closing Time</label>
                                                <select class="form-select">
                                                    <option>10:00 PM</option>
                                                    <option>11:00 PM</option>
                                                    <option selected>12:00 AM</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Maintenance Day</label>
                                        <select class="form-select">
                                            <option>Every 1st Monday</option>
                                            <option selected>Every 2nd Monday</option>
                                            <option>Every 3rd Monday</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                            </div>
                            <div class="card-body">
                                <form id="notificationSettings">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                        <label class="form-check-label" for="emailNotifications">Email Notifications</label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="smsNotifications" checked>
                                        <label class="form-check-label" for="smsNotifications">SMS Notifications</label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notification Email</label>
                                        <input type="email" class="form-control" value="admin@barangaybula.gov.ph">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Reservation Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Reservation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reservationToReject">
                    <div class="mb-3">
                        <label for="rejectionReason" class="form-label">Reason for rejection:</label>
                        <textarea class="form-control" id="rejectionReason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmReject">Confirm Rejection</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="userName">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="userContact">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="userEmail">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Account Status</label>
                                <select class="form-select" id="userStatus">
                                    <option value="active">Active</option>
                                    <option value="restricted">Restricted</option>
                                    <option value="banned">Banned</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Strikes</label>
                                <input type="number" class="form-control" id="userStrikes" min="0" max="5">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Restriction Reason</label>
                                <textarea class="form-control" id="userRestrictionReason" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5 class="mb-3">Reservation History</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="userReservationHistory">
                                <!-- Dynamically filled -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveUserChanges">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <script>
        // Sample data - in a real app, this would come from a backend API
        const tempDatabase = {
            reservations: [
                {
                    id: 1,
                    date: new Date().toISOString().split('T')[0],
                    startTime: '08:00',
                    endTime: '10:00',
                    resident: 'Team Spartans',
                    contact: '09123456789',
                    activity: 'Basketball Practice',
                    participants: 12,
                    notes: 'Preparing for upcoming tournament',
                    reference: 'GYM-ABC1234',
                    status: 'pending',
                    paymentMethod: 'counter',
                    paymentStatus: 'unpaid',
                    adminNotes: '',
                    createdAt: new Date().toISOString()
                },
                {
                    id: 2,
                    date: new Date().toISOString().split('T')[0],
                    startTime: '14:00',
                    endTime: '16:00',
                    resident: 'Barangay Sports Club',
                    contact: '09987654321',
                    activity: 'Volleyball Tournament',
                    participants: 20,
                    notes: 'Quarterfinals match',
                    reference: 'GYM-DEF5678',
                    status: 'approved',
                    paymentMethod: 'counter',
                    paymentStatus: 'unpaid',
                    adminNotes: '',
                    createdAt: new Date('2025-06-10').toISOString()
                },
                {
                    id: 3,
                    date: new Date(new Date().setDate(new Date().getDate() + 1)).toISOString().split('T')[0],
                    startTime: '18:00',
                    endTime: '20:00',
                    resident: 'Zumba Group',
                    contact: '09112233445',
                    activity: 'Zumba Class',
                    participants: 15,
                    notes: 'Weekly fitness session',
                    reference: 'GYM-GHI9012',
                    status: 'paid',
                    paymentMethod: 'counter',
                    paymentStatus: 'paid',
                    adminNotes: 'Paid at front desk',
                    createdAt: new Date().toISOString()
                },
                {
                    id: 4,
                    date: new Date(new Date().setDate(new Date().getDate() - 1)).toISOString().split('T')[0],
                    startTime: '19:00',
                    endTime: '21:00',
                    resident: 'Maria Santos',
                    contact: '09987654321',
                    activity: 'Aerobics Class',
                    participants: 10,
                    notes: 'Regular session',
                    reference: 'GYM-JKL3456',
                    status: 'completed',
                    paymentMethod: 'counter',
                    paymentStatus: 'paid',
                    adminNotes: '',
                    createdAt: new Date('2025-06-15').toISOString()
                },
                {
                    id: 5,
                    date: new Date(new Date().setDate(new Date().getDate() - 3)).toISOString().split('T')[0],
                    startTime: '16:00',
                    endTime: '18:00',
                    resident: 'Juan Dela Cruz',
                    contact: '09123456789',
                    activity: 'Basketball Practice',
                    participants: 8,
                    notes: 'Team practice',
                    reference: 'GYM-MNO7890',
                    status: 'completed',
                    paymentMethod: 'counter',
                    paymentStatus: 'paid',
                    adminNotes: '',
                    createdAt: new Date('2025-06-13').toISOString()
                },
                {
                    id: 6,
                    date: new Date(new Date().setDate(new Date().getDate() - 5)).toISOString().split('T')[0],
                    startTime: '09:00',
                    endTime: '11:00',
                    resident: 'Maria Santos',
                    contact: '09987654321',
                    activity: 'Volleyball Practice',
                    participants: 12,
                    notes: 'No show - strike issued',
                    reference: 'GYM-PQR1234',
                    status: 'no-show',
                    paymentMethod: 'counter',
                    paymentStatus: 'refunded',
                    adminNotes: 'User did not show up, strike issued',
                    createdAt: new Date('2025-06-11').toISOString()
                }
            ],
            users: [
                {
                    id: 'user1',
                    name: 'Juan Dela Cruz',
                    contact: '09123456789',
                    email: 'juan.delacruz@example.com',
                    strikes: 0,
                    status: 'active',
                    restrictionReason: '',
                    reservations: 3
                },
                {
                    id: 'user2',
                    name: 'Maria Santos',
                    contact: '09987654321',
                    email: 'maria.santos@example.com',
                    strikes: 2,
                    status: 'restricted',
                    restrictionReason: 'Multiple no-shows',
                    reservations: 5
                },
                {
                    id: 'user3',
                    name: 'Pedro Reyes',
                    contact: '09112233445',
                    email: 'pedro.reyes@example.com',
                    strikes: 0,
                    status: 'active',
                    restrictionReason: '',
                    reservations: 1
                }
            ],
            settings: {
                ratePerHour: 200,
                openingHour: '6:00 AM',
                closingHour: '12:00 AM',
                maintenanceDay: 'Every 2nd Monday'
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            const userModal = new bootstrap.Modal(document.getElementById('userModal'));

            // Format date for display
            function formatDate(dateStr) {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            }

            // Format time range
            function formatTimeRange(startTime, endTime) {
                return `${startTime} - ${endTime}`;
            }

            // Calculate reservation amount
            function calculateAmount(startTime, endTime) {
                const start = parseInt(startTime.split(':')[0]);
                const end = parseInt(endTime.split(':')[0]);
                return (end - start) * tempDatabase.settings.ratePerHour;
            }

            // Get status badge
            function getStatusBadge(status) {
                const statusMap = {
                    'pending': { class: 'badge-pending', text: 'PENDING' },
                    'approved': { class: 'badge-approved', text: 'APPROVED' },
                    'paid': { class: 'badge-paid', text: 'PAID' },
                    'completed': { class: 'badge-success', text: 'COMPLETED' },
                    'rejected': { class: 'badge-rejected', text: 'REJECTED' },
                    'no-show': { class: 'badge-danger', text: 'NO SHOW' }
                };
                
                const statusInfo = statusMap[status.toLowerCase()] || { class: 'badge-secondary', text: status };
                return `<span class="badge ${statusInfo.class}">${statusInfo.text}</span>`;
            }

            // Get payment badge
            function getPaymentBadge(paymentStatus) {
                return paymentStatus === 'paid' 
                    ? '<span class="badge bg-success">PAID</span>'
                    : '<span class="badge bg-warning">UNPAID</span>';
            }

            // Load pending reservations
            function loadPendingReservations() {
                const pending = tempDatabase.reservations.filter(r => r.status === 'pending');
                const container = document.getElementById('pendingReservations');
                
                document.getElementById('pendingCount').textContent = pending.length;
                
                container.innerHTML = pending.map(res => `
                    <tr>
                        <td>${res.reference}</td>
                        <td>${formatDate(res.date)} ${formatTimeRange(res.startTime, res.endTime)}</td>
                        <td>${res.resident}<br><small>${res.contact}</small></td>
                        <td>${res.activity}<br><small>${res.participants} participants</small></td>
                        <td>₱${calculateAmount(res.startTime, res.endTime)}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-success" onclick="approveReservation('${res.id}')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-danger" onclick="showRejectForm('${res.id}')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="6" class="text-center">No pending reservations</td></tr>';
            }

            // Load upcoming reservations
            function loadUpcomingReservations() {
                const upcoming = tempDatabase.reservations.filter(r => 
                    ['approved', 'paid'].includes(r.status) && 
                    new Date(r.date) >= new Date(new Date().setHours(0,0,0,0))
                ).sort((a, b) => new Date(a.date) - new Date(b.date));
                
                const container = document.getElementById('upcomingReservations');
                
                document.getElementById('upcomingCount').textContent = upcoming.length;
                
                container.innerHTML = upcoming.map(res => `
                    <tr>
                        <td>${res.reference}</td>
                        <td>${formatDate(res.date)} ${formatTimeRange(res.startTime, res.endTime)}</td>
                        <td>${res.resident}</td>
                        <td>${res.activity}</td>
                        <td>${getStatusBadge(res.status)}</td>
                        <td>${getPaymentBadge(res.paymentStatus)}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewReservation('${res.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="7" class="text-center">No upcoming reservations</td></tr>';
            }

            // Load history reservations
            function loadHistoryReservations() {
                const history = tempDatabase.reservations.filter(r => 
                    ['completed', 'rejected', 'no-show'].includes(r.status) ||
                    new Date(r.date) < new Date(new Date().setHours(0,0,0,0))
                ).sort((a, b) => new Date(b.date) - new Date(a.date));
                
                const container = document.getElementById('historyReservations');
                
                container.innerHTML = history.map(res => `
                    <tr>
                        <td>${res.reference}</td>
                        <td>${formatDate(res.date)} ${formatTimeRange(res.startTime, res.endTime)}</td>
                        <td>${res.resident}</td>
                        <td>${res.activity}</td>
                        <td>${getStatusBadge(res.status)}</td>
                        <td>${getPaymentBadge(res.paymentStatus)}</td>
                        <td>₱${calculateAmount(res.startTime, res.endTime)}</td>
                    </tr>
                `).join('') || '<tr><td colspan="7" class="text-center">No history records</td></tr>';
            }

            // Load user management
            function loadUserManagement() {
                const container = document.getElementById('userManagementList');
                
                document.getElementById('problemCount').textContent = 
                    tempDatabase.users.filter(u => u.strikes > 0 || u.status !== 'active').length;
                
                container.innerHTML = tempDatabase.users.map(user => `
                    <tr>
                        <td>${user.name}</td>
                        <td>${user.contact}<br><small>${user.email}</small></td>
                        <td>${user.reservations}</td>
                        <td>${user.strikes}</td>
                        <td class="${user.status === 'active' ? 'user-status-active' : 'user-status-restricted'}">
                            ${user.status.toUpperCase()}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewUserDetails('${user.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${user.strikes > 0 ? `
                                <button class="btn btn-sm btn-outline-success" onclick="clearUserStrikes('${user.id}')">
                                    <i class="fas fa-eraser"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="6" class="text-center">No users found</td></tr>';
            }

            // Load today's count
            function loadTodaysCount() {
                const today = new Date().toISOString().split('T')[0];
                const count = tempDatabase.reservations.filter(r => 
                    r.date === today && ['approved', 'paid'].includes(r.status)
                ).length;
                
                document.getElementById('todayCount').textContent = count;
            }

            // View user details
            window.viewUserDetails = function(userId) {
                const user = tempDatabase.users.find(u => u.id === userId);
                if (!user) return;
                
                document.getElementById('userModalTitle').textContent = `User: ${user.name}`;
                document.getElementById('userName').value = user.name;
                document.getElementById('userContact').value = user.contact;
                document.getElementById('userEmail').value = user.email;
                document.getElementById('userStatus').value = user.status;
                document.getElementById('userStrikes').value = user.strikes;
                document.getElementById('userRestrictionReason').value = user.restrictionReason || '';
                
                // Load user's reservation history
                const userReservations = tempDatabase.reservations
                    .filter(r => r.contact === user.contact)
                    .sort((a, b) => new Date(b.date) - new Date(a.date));
                
                const historyContainer = document.getElementById('userReservationHistory');
                historyContainer.innerHTML = userReservations.map(res => `
                    <tr>
                        <td>${formatDate(res.date)}</td>
                        <td>${res.activity}</td>
                        <td>${getStatusBadge(res.status)}</td>
                        <td>₱${calculateAmount(res.startTime, res.endTime)}</td>
                    </tr>
                `).join('') || '<tr><td colspan="4" class="text-center">No reservation history</td></tr>';
                
                userModal.show();
            };

            // Clear user strikes
            window.clearUserStrikes = function(userId) {
                const user = tempDatabase.users.find(u => u.id === userId);
                if (user) {
                    user.strikes = 0;
                    if (user.status === 'restricted') {
                        user.status = 'active';
                        user.restrictionReason = '';
                    }
                    loadUserManagement();
                    alert('Strikes cleared for user');
                }
            };

            // Approve reservation
            window.approveReservation = function(reservationId) {
                const res = tempDatabase.reservations.find(r => r.id == reservationId);
                if (res) {
                    res.status = 'approved';
                    
                    // In real app: Send SMS notification
                    console.log(`SMS to ${res.contact}: Your reservation ${res.reference} was approved. Please pay ₱${calculateAmount(res.startTime, res.endTime)} at Barangay Hall within 24 hours.`);
                    
                    loadPendingReservations();
                    loadUpcomingReservations();
                    loadTodaysCount();
                }
            };

            // Show reject form
            window.showRejectForm = function(reservationId) {
                document.getElementById('reservationToReject').value = reservationId;
                document.getElementById('rejectionReason').value = '';
                rejectModal.show();
            };

            // Confirm reject
            document.getElementById('confirmReject').addEventListener('click', function() {
                const reservationId = document.getElementById('reservationToReject').value;
                const reason = document.getElementById('rejectionReason').value;
                
                if (!reason) {
                    alert('Please provide a reason for rejection');
                    return;
                }
                
                const res = tempDatabase.reservations.find(r => r.id == reservationId);
                if (res) {
                    res.status = 'rejected';
                    res.adminNotes = reason;
                    
                    // In real app: Send rejection SMS
                    console.log(`SMS to ${res.contact}: Your reservation ${res.reference} was rejected. Reason: ${reason}`);
                    
                    rejectModal.hide();
                    loadPendingReservations();
                }
            });

            // View reservation details
            window.viewReservation = function(reservationId) {
                const res = tempDatabase.reservations.find(r => r.id == reservationId);
                if (!res) return;
                
                alert(`Reservation Details:\n\n` +
                      `Reference: ${res.reference}\n` +
                      `Date: ${formatDate(res.date)}\n` +
                      `Time: ${formatTimeRange(res.startTime, res.endTime)}\n` +
                      `User: ${res.resident} (${res.contact})\n` +
                      `Activity: ${res.activity}\n` +
                      `Participants: ${res.participants}\n` +
                      `Status: ${res.status}\n` +
                      `Payment: ${res.paymentStatus}\n` +
                      `Amount: ₱${calculateAmount(res.startTime, res.endTime)}\n` +
                      `Notes: ${res.notes || 'None'}\n` +
                      `Admin Notes: ${res.adminNotes || 'None'}`);
            };

            // Save user changes
            document.getElementById('saveUserChanges').addEventListener('click', function() {
                const userId = document.getElementById('reservationToReject').value; // Note: This should be updated to get the correct user ID
                const status = document.getElementById('userStatus').value;
                const strikes = parseInt(document.getElementById('userStrikes').value);
                const reason = document.getElementById('userRestrictionReason').value;
                
                const user = tempDatabase.users.find(u => u.id === userId);
                if (user) {
                    user.status = status;
                    user.strikes = strikes;
                    user.restrictionReason = reason;
                    
                    loadUserManagement();
                    userModal.hide();
                    alert('User details updated successfully');
                }
            });

            // Logout button
            document.getElementById('logoutBtn').addEventListener('click', function() {
                // In real app, would clear session and redirect
                alert('You have been logged out');
                window.location.href = 'login.html'; // Redirect to login page
            });

            // Filter upcoming reservations
            document.querySelectorAll('[data-filter]').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    const rows = document.querySelectorAll('#upcomingReservations tr');
                    
                    rows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else {
                            const status = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                            row.style.display = status.includes(filter) ? '' : 'none';
                        }
                    });
                });
            });

            // Initialize all data
            loadPendingReservations();
            loadUpcomingReservations();
            loadHistoryReservations();
            loadUserManagement();
            loadTodaysCount();
        });
    </script>
</body>
</html>