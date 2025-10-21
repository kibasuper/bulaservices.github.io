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
    </header>

    <main class="dashboard-container container">
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
        </div>

        <div class="time-filters">
            <div class="time-filter active" data-period="this_month">This Month</div>
            <div class="time-filter" data-period="last_month">Last Month</div>
            <div class="time-filter" data-period="this_quarter">This Quarter</div>
            <div class="time-filter" data-period="this_year">This Year</div>
            <div class="time-filter" data-period="custom">Custom Range</div>
        </div>

        <div class="date-range-picker" id="dateRangePicker" style="display: none;">
            <input type="text" class="date-range-input" id="dateRangeStart" placeholder="Start Date">
            <span>to</span>
            <input type="text" class="date-range-input" id="dateRangeEnd" placeholder="End Date">
            <button class="date-range-btn" id="applyDateRange">Apply</button>
        </div>

        <!-- Service Requests -->
        <div class="report-section">
            <div class="report-header">
                <h2 class="section-title">Service Requests Report</h2>
                <div class="report-actions">
        
                    <button class="report-btn export-btn" data-report="requests"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

          

            <table class="data-table" id="requestsTable">
                <thead>
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

        <!-- Sales Report -->
        <div class="report-section">
            <div class="report-header">
                <h2 class="section-title">Sales Report</h2>
                <div class="report-actions">
                    
                    <button class="report-btn export-btn" data-report="financial"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

            <table class="data-table" id="financialTable">
                <thead>
                    <tr>
                        <th>Service Type</th>
                        <th>Total Sales</th>
                        <th>Cash Payments</th>
                    </tr>
                </thead>
                <tbody id="financialTableBody"></tbody>
            </table>
        </div>

        <!-- Demographics -->
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

    <script src="./script/report.js"></script>
</body>
</html>
