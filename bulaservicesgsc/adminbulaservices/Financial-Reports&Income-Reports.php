<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Management System - Financial Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/Financial-reports&Income-reports.css">
    
</head>
<body>
<?php include 'templates/sidebar.php'; ?>
<?php include 'templates/topbar.php'; ?>
    <!-- Main Content -->
    <main>      

        <div class="container mt-4">
            <div class="report-header">
                <h1><i class="fas fa-chart-pie me-2"></i> Financial Reports & Income Statements</h1>
                <p class="text-muted">As of <span id="report-date">October 25, 2025</span></p>
            </div>

            <div class="filter-section">
                <div class="row">
                    <div class="col-md-3">
                        <label for="fiscal-year" class="form-label">Fiscal Year</label>
                        <select id="fiscal-year" class="form-select">
                            <option selected>2025</option>
                            <option>2022</option>
                            <option>2021</option>
                            <option>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="report-type" class="form-label">Report Type</label>
                        <select id="report-type" class="form-select">
                            <option selected>Summary Report</option>
                            <option>Detailed Income</option>
                            <option>Expense Breakdown</option>
                            <option>Balance Sheet</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="fund-category" class="form-label">Fund Category</label>
                        <select id="fund-category" class="form-select">
                            <option selected>All Funds</option>
                            <option>General Fund</option>
                            <option>Special Education Fund</option>
                            <option>Trust Fund</option>
                            <option>Development Fund</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" id="apply-filters">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-card bg-primary">
                        <h5><i class="fas fa-wallet me-2"></i>Total Revenue</h5>
                        <p>₱1,245,680</p>
                        <small>Year-to-Date</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card bg-success">
                        <h5><i class="fas fa-receipt me-2"></i>Total Expenses</h5>
                        <p>₱987,420</p>
                        <small>Year-to-Date</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card bg-info">
                        <h5><i class="fas fa-piggy-bank me-2"></i>Net Surplus</h5>
                        <p>₱258,260</p>
                        <small>Current Balance</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card bg-warning">
                        <h5><i class="fas fa-percentage me-2"></i>Savings Rate</h5>
                        <p>20.7%</p>
                        <small>Of Total Revenue</small>
                    </div>
                </div>
            </div>

            <!-- Revenue vs Expenses -->
            <div class="report-card">
                <div class="report-card-header">
                    <h3><i class="fas fa-chart-bar me-2"></i>Revenue vs Expenses</h3>
                </div>
                <div class="report-card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="revenueExpensesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Income Sources -->
            <div class="report-card">
                <div class="report-card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-money-bill-wave me-2"></i>Income Sources</h3>
                    <button class="btn btn-sm btn-outline-light" id="export-income">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="report-card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="incomeSourcesChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Income Source</th>
                                        <th>Amount</th>
                                        <th>Percentage</th>
                                        <th>YTD Growth</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Business Permits</td>
                                        <td>₱420,500</td>
                                        <td>33.7%</td>
                                        <td><span class="text-success">+12.5%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Community Taxes</td>
                                        <td>₱315,200</td>
                                        <td>25.3%</td>
                                        <td><span class="text-success">+8.2%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Service Fees</td>
                                        <td>₱210,800</td>
                                        <td>16.9%</td>
                                        <td><span class="text-success">+15.7%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Grants & Donations</td>
                                        <td>₱187,000</td>
                                        <td>15.0%</td>
                                        <td><span class="text-danger">-5.3%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Other Income</td>
                                        <td>₱112,180</td>
                                        <td>9.0%</td>
                                        <td><span class="text-success">+3.8%</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expense Breakdown -->
            <div class="report-card">
                <div class="report-card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-file-invoice-dollar me-2"></i>Expense Breakdown</h3>
                    <button class="btn btn-sm btn-outline-light" id="export-expenses">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
                <div class="report-card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="expenseBreakdownChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Expense Category</th>
                                        <th>Budget</th>
                                        <th>Actual</th>
                                        <th>Variance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Personnel Services</td>
                                        <td>₱320,000</td>
                                        <td>₱315,200</td>
                                        <td><span class="text-success">₱4,800 (1.5%)</span></td>
                                    </tr>
                                    <tr>
                                        <td>Maintenance</td>
                                        <td>₱280,000</td>
                                        <td>₱275,500</td>
                                        <td><span class="text-success">₱4,500 (1.6%)</span></td>
                                    </tr>
                                    <tr>
                                        <td>Capital Outlay</td>
                                        <td>₱250,000</td>
                                        <td>₱265,720</td>
                                        <td><span class="text-danger">-₱15,720 (6.3%)</span></td>
                                    </tr>
                                    <tr>
                                        <td>Special Programs</td>
                                        <td>₱180,000</td>
                                        <td>₱175,000</td>
                                        <td><span class="text-success">₱5,000 (2.8%)</span></td>
                                    </tr>
                                    <tr>
                                        <td>Other Expenses</td>
                                        <td>₱120,000</td>
                                        <td>₱115,000</td>
                                        <td><span class="text-success">₱5,000 (4.2%)</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Transactions -->
            <div class="report-card">
                <div class="report-card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-list me-2"></i>Detailed Transactions</h3>
                    <div>
                        <button class="btn btn-sm btn-outline-light me-2" id="export-transactions">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                        <button class="btn btn-sm btn-outline-light" id="new-transaction">
                            <i class="fas fa-plus me-1"></i> New
                        </button>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="transactions-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transaction ID</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Oct 25, 2025</td>
                                    <td>TRX-2025-1025</td>
                                    <td>Monthly Salary - Office Staff</td>
                                    <td>Personnel</td>
                                    <td><span class="badge bg-danger">Expense</span></td>
                                    <td>₱85,200</td>
                                    <td><span class="badge bg-success">Completed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-details">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Oct 24, 2025</td>
                                    <td>TRX-2025-1024</td>
                                    <td>Business Permit - ABC Store</td>
                                    <td>Permits</td>
                                    <td><span class="badge bg-success">Income</span></td>
                                    <td>₱12,500</td>
                                    <td><span class="badge bg-success">Completed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-details">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Oct 23, 2025</td>
                                    <td>TRX-2025-1023</td>
                                    <td>Office Supplies Purchase</td>
                                    <td>Supplies</td>
                                    <td><span class="badge bg-danger">Expense</span></td>
                                    <td>₱8,750</td>
                                    <td><span class="badge bg-success">Completed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-details">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Oct 22, 2025</td>
                                    <td>TRX-2025-1022</td>
                                    <td>Community Tax Collection</td>
                                    <td>Taxes</td>
                                    <td><span class="badge bg-success">Income</span></td>
                                    <td>₱32,150</td>
                                    <td><span class="badge bg-success">Completed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-details">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Oct 21, 2025</td>
                                    <td>TRX-2025-1021</td>
                                    <td>Street Light Maintenance</td>
                                    <td>Infrastructure</td>
                                    <td><span class="badge bg-danger">Expense</span></td>
                                    <td>₱15,000</td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-details">View</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-muted mt-4">
                <p>Generated by Barangay Management System on <span id="generated-date">October 25, 2025</span></p>
                <p>This is an automated report. For questions, please contact the Barangay Office.</p>
            </div>
        </div>
    </main>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Transaction ID:</strong> <span id="modal-trx-id">TRX-2025-1025</span></p>
                            <p><strong>Date:</strong> <span id="modal-trx-date">October 25, 2025</span></p>
                            <p><strong>Type:</strong> <span id="modal-trx-type" class="badge bg-danger">Expense</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Amount:</strong> <span id="modal-trx-amount">₱85,200.00</span></p>
                            <p><strong>Status:</strong> <span id="modal-trx-status" class="badge bg-success">Completed</span></p>
                            <p><strong>Category:</strong> <span id="modal-trx-category">Personnel Services</span></p>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <h6>Transaction Details</h6>
                        <p><strong>Description:</strong> <span id="modal-trx-description">Monthly Salary - Office Staff</span></p>
                        <p><strong>Payee/Payer:</strong> <span id="modal-trx-party">Barangay Bula Treasury</span></p>
                        <p><strong>Reference No.:</strong> <span id="modal-trx-ref">PS-1023</span></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Financial Details</h6>
                            <p><strong>Fund Source:</strong> <span id="modal-trx-fund">General Fund</span></p>
                            <p><strong>Budget Item:</strong> <span id="modal-trx-budget">Salaries & Wages</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Approval Details</h6>
                            <p><strong>Approved By:</strong> <span id="modal-trx-approved">Brgy. Captain Juan Dela Cruz</span></p>
                            <p><strong>Date Approved:</strong> <span id="modal-trx-approved-date">October 24, 2025</span></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6>Attachments</h6>
                        <ul id="modal-trx-attachments">
                            <li><a href="#">Payroll_October_2025.pdf</a></li>
                            <li><a href="#">Approval_Form_1023.pdf</a></li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Print Receipt</button>
                    <button type="button" class="btn btn-success">Export as PDF</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="scripts/Financial-reports&Income-reports.js"></script>
    <script src="scripts/sidebar.js"></script>
</body>
</html>