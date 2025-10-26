<?php
require_once __DIR__ . '/server/config.php';
if (empty($_SESSION['admin_id'])) {
  header('Location: index.php');
  exit;
}
if ($_SESSION['admin_role'] !== 'superadmin') {
    header('Location: admin.php?denied=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bula - Enhanced Reports Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="./css/reports.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <header class="app-header">
        <div class="container header-content">
            <h1><i class="fas fa-chart-bar"></i> Reports</h1>
            <div class="header-actions">
                <a href="admin.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>
    </header>

    <main class="dashboard-container container">

        <!-- SUMMARY -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-title">Total Requests</div>
                <div class="card-value" id="totalRequests">0</div>
                <div class="card-change" id="requestsChange"><i class="fas fa-arrow-up"></i> <span id="requestsChangeValue">0%</span> from last month</div>
            </div>
            <div class="summary-card">
                <div class="card-title">Revenue Collected</div>
                <div class="card-value" id="totalRevenue">₱0</div>
                <div class="card-change" id="revenueChange"><i class="fas fa-arrow-up"></i> <span id="revenueChangeValue">0%</span> from last month</div>
            </div>
            <div class="summary-card">
                <div class="card-title">Pending Approvals</div>
                <div class="card-value" id="pendingApprovals">0</div>
                <div class="card-change" id="approvalsChange"><i class="fas fa-arrow-down"></i> <span id="approvalsChangeValue">0%</span> from last month</div>
            </div>
            <div class="summary-card">
                <div class="card-title">Registered Residents</div>
                <div class="card-value" id="registeredResidents">0</div>
                <div class="card-change" id="residentsChange"><i class="fas fa-arrow-up"></i> <span id="residentsChangeValue">0%</span> from last quarter</div>
            </div>
            <!-- NEW: Outsiders -->
            <div class="summary-card">
                <div class="card-title">Registered Outsiders</div>
                <div class="card-value" id="registeredOutsiders">0</div>
                <div class="card-change change-up"><i class="fas fa-arrow-up"></i><span>—</span></div>
            </div>
        </div>

        <!-- GLOBAL TIME FILTERS (still drive the summary cards) -->
        <div class="time-filters" id="globalTimeFilters">
            <div class="time-filter active" data-period="this_month">This Month</div>
            <div class="time-filter" data-period="last_month">Last Month</div>
            <div class="time-filter" data-period="this_quarter">This Quarter</div>
            <div class="time-filter" data-period="this_year">This Year</div>
            <div class="time-filter" data-period="custom">Custom Range</div>
        </div>
        <div class="date-range-picker" id="dateRangePicker" style="display:none;">
            <input type="text" class="date-range-input" id="dateRangeStart" placeholder="Start Date">
            <span>to</span>
            <input type="text" class="date-range-input" id="dateRangeEnd" placeholder="End Date">
            <button class="date-range-btn" id="applyDateRange">Apply</button>
        </div>

        <!-- SERVICE REQUESTS -->
        <div class="report-section" id="requestsSection">
            <div class="report-header">
                <h2 class="section-title">Service Requests Report</h2>
                <div class="report-actions">
                    <button class="report-btn export-btn" data-report="requests"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

            <!-- NEW: Requests local filters -->
            <div class="local-filters">
                <div class="lf-row">
                    <div class="lf-group">
                        <label>View</label>
                        <div class="lf-pills" id="reqViewPills">
                            <button class="pill active" data-view="summary">Summary</button>
                            <button class="pill" data-view="daily">Daily</button>
                        </div>
                    </div>
                    <div class="lf-group">
                        <label>Service</label>
                        <select id="reqServiceSelect" class="lf-select">
                            <option value="">All Services</option>
                            <option value="barangay_clearance">Barangay Clearance</option>
                            <option value="business_permit">Business Permit</option>
                            <option value="cedula">Community Tax Cert.</option>
                            <option value="indigency">Cert. of Indigency</option>
                            <option value="residency">Cert. of Residency</option>
                            <option value="low_income">Low Income Cert.</option>
                            <option value="proof_income">Proof of Income</option>
                            <option value="ivs">Individual Voluntary Statement</option>
                            <option value="gym">Gym Reservation</option>
                        </select>
                    </div>
                    <div class="lf-group">
                        <label>Date Range</label>
                        <input type="text" id="reqDateStart" class="lf-input" placeholder="Start">
                        <span class="lf-sep">to</span>
                        <input type="text" id="reqDateEnd" class="lf-input" placeholder="End">
                        <button class="lf-apply" id="reqApply">Apply</button>
                    </div>
                </div>
            </div>



            <table class="data-table" id="requestsTable">
                <thead id="requestsTableHead">
                    <tr>
                        <th>Service Type</th>
                        <th>Total Requests</th>
                        <th>Approved</th>
                        <th>Rejected</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody"></tbody>
            </table>
        </div>

        <!-- SALES -->
        <div class="report-section" id="salesSection">
            <div class="report-header">
                <h2 class="section-title">Sales Report</h2>
                <div class="report-actions">
                    <button class="report-btn export-btn" data-report="financial"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

            <!-- NEW: Sales local filters -->
            <div class="local-filters">
                <div class="lf-row">
                    <div class="lf-group">
                        <label>View</label>
                        <div class="lf-pills" id="salesViewPills">
                            <button class="pill active" data-view="summary">Summary</button>
                            <button class="pill" data-view="daily">Daily</button>
                        </div>
                    </div>
                    <div class="lf-group">
                        <label>Service</label>
                        <select id="salesServiceSelect" class="lf-select">
                            <option value="">All Services</option>
                            <option value="barangay_clearance">Barangay Clearance</option>
                            <option value="business_permit">Business Permit</option>
                            <option value="cedula">Community Tax Cert.</option>
                            <option value="indigency">Cert. of Indigency</option>
                            <option value="residency">Cert. of Residency</option>
                            <option value="low_income">Low Income Cert.</option>
                            <option value="proof_income">Proof of Income</option>
                            <option value="ivs">Individual Voluntary Statement</option>
                            <option value="gym">Gym Reservation</option>
                        </select>
                    </div>
                    <div class="lf-group">
                        <label>Date Range</label>
                        <input type="text" id="salesDateStart" class="lf-input" placeholder="Start">
                        <span class="lf-sep">to</span>
                        <input type="text" id="salesDateEnd" class="lf-input" placeholder="End">
                        <button class="lf-apply" id="salesApply">Apply</button>
                    </div>
                </div>
            </div>

            <table class="data-table" id="financialTable">
                <thead id="financialTableHead">
                    <tr>
                        <th>Service Type</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody id="financialTableBody"></tbody>
            </table>
        </div>

        <!-- NEW: TRANSACTION HISTORY -->
        <div class="report-section" id="txSection">
            <div class="report-header">
                <h2 class="section-title">Transaction History</h2>
                <div class="report-actions">
                    <button class="report-btn export-btn" data-report="transactions"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

            <div class="local-filters">
                <div class="lf-row">
                    <div class="lf-group">
                        <label>Service</label>
                        <select id="txServiceSelect" class="lf-select">
                            <option value="">All Services</option>
                            <option value="barangay_clearance">Barangay Clearance</option>
                            <option value="business_permit">Business Permit</option>
                            <option value="cedula">Community Tax Cert.</option>
                            <option value="indigency">Cert. of Indigency</option>
                            <option value="residency">Cert. of Residency</option>
                            <option value="low_income">Low Income Cert.</option>
                            <option value="proof_income">Proof of Income</option>
                            <option value="ivs">Individual Voluntary Statement</option>
                            <option value="gym_reservation">Gym Reservation</option>
                        </select>
                    </div>
                    <div class="lf-group">
                        <label>Cashier</label>
                        <input type="text" id="txCashier" class="lf-input" placeholder="Admin ID or name">
                    </div>
                    <div class="lf-group">
                        <label>Receipt / Search</label>
                        <input type="text" id="txSearch" class="lf-input" placeholder="Receipt no. / email">
                    </div>
                    <div class="lf-group">
                        <label>Date Range</label>
                        <input type="text" id="txDateStart" class="lf-input" placeholder="Start">
                        <span class="lf-sep">to</span>
                        <input type="text" id="txDateEnd" class="lf-input" placeholder="End">
                        <button class="lf-apply" id="txApply">Apply</button>
                    </div>
                </div>
            </div>

            <table class="data-table" id="txTable">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Receipt No.</th>
                        <th>Payer</th>
                        <th>Cashier</th>
                        <th>Service Type</th>
                        <th>Request ID</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody id="txTableBody"></tbody>
            </table>

            <div class="pager" id="txPager">
                <button id="txPrev" class="pager-btn" disabled>Prev</button>
                <span id="txPageInfo" class="pager-info">Page 1</span>
                <button id="txNext" class="pager-btn" disabled>Next</button>
            </div>
        </div>

        <!-- DEMOGRAPHICS -->
        <div class="report-section">
            <div class="report-header">
                <h2 class="section-title">Resident Demographics Report</h2>
                <div class="report-actions">
                    <button class="report-btn export-btn" data-report="demographics"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

            <div class="report-tabs">
                <div class="report-tab active" data-tab="population">Population</div>
            </div>

            <div class="tab-content" id="population-tab">
                <div class="chart-row">
                    <div class="chart-half"><div class="chart-container"><canvas id="ageChart"></canvas></div></div>
                    <div class="chart-half"><div class="chart-container"><canvas id="genderChart"></canvas></div></div>
                </div>
            </div>
        </div>
    </main>

    <script src="./script/report.js?v=2"></script>
</body>
</html>
