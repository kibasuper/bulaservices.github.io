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

            <!-- Requests local filters -->
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

            <!-- Sales local filters -->
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
                    <div class="chart-half">
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-half">
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEGMENT FILTER / EXPORT -->
            <div class="segment-block">
                <h3 class="section-subtitle" style="margin-top:2rem; font-weight:600; font-size:1rem;">
                    Resident Segmentation
                </h3>

                <div class="local-filters">
                    <div class="lf-row">
                        <!-- Age range -->
                        <div class="lf-group">
                            <label>Age Range</label>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <input type="number" id="demoAgeMin" class="lf-input" placeholder="Min (e.g. 18)" min="0" style="width:6rem;">
                                <span class="lf-sep">to</span>
                                <input type="number" id="demoAgeMax" class="lf-input" placeholder="Max (e.g. 30)" min="0" style="width:6rem;">
                            </div>
                        </div>

                        <!-- Civil Status -->
                        <div class="lf-group">
                            <label>Civil Status</label>
                            <select id="demoCivil" class="lf-select">
                                <option value="">All</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="widowed">Widowed</option>
                                <option value="separated">Separated</option>
                            </select>
                        </div>

                        <!-- Gender -->
                        <div class="lf-group">
                            <label>Gender</label>
                            <select id="demoGender" class="lf-select">
                                <option value="">All</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <!-- Purok -->
                        <div class="lf-group">
                            <label>Purok</label>
                            <select id="demoPurok" class="lf-select">
                                <option value="">All Puroks</option>
                                <option value="1">Purok 1: Pearly Shell</option>
                                <option value="2">Purok 2: Fishermans Village</option>
                                <option value="3">Purok 3: Rajah Muda</option>
                                <option value="4">Purok 4: Rajah Muda 4A</option>
                                <option value="5">Purok 5: Rajah Muda 4B</option>
                                <option value="6">Purok 6: Rajah Muda 5</option>
                                <option value="7">Purok 7: Lagang-Lagang</option>
                                <option value="8">Purok 8: Zone 1A</option>
                                <option value="9">Purok 9: Zone 2B</option>
                                <option value="10">Purok 10: Zone 2A</option>
                                <option value="11">Purok 11: Zone 2B</option>
                                <option value="12">Purok 12: Zone 2C</option>
                                <option value="13">Purok 13: Zone 3,4,5</option>
                                <option value="14">Purok 14: Zone 6</option>
                                <option value="15">Purok 15: Zone 7</option>
                                <option value="16">Purok 16: Zone 8</option>
                                <option value="17">Purok 17: Zone 9</option>
                                <option value="18">Purok 18: Calsanter</option>
                                <option value="19">Purok 19: Sagrada Corazon</option>
                                <option value="20">Purok 20: Gonzales Subd.</option>
                                <option value="21">Purok 21: Gensanville Phase 1</option>
                                <option value="22">Purok 22: Gensanville Phase 2</option>
                                <option value="23">Purok 23: Sitio Rapoa</option>
                                <option value="24">Purok 24: San Pedro</option>
                                <option value="25">Purok 25: Asai Village</option>
                            </select>
                        </div>

                        <!-- Actions -->
                        <div class="lf-group">
                            <label>&nbsp;</label>
                            <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                                <button class="lf-apply" id="demoApply">Apply</button>
                                <button class="report-btn export-btn" id="demoExportBtn" data-report="demo_export">
                                    <i class="fas fa-file-csv"></i> Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result summary -->
                <div class="segment-summary" style="margin:1rem 0 .5rem 0;font-size:.9rem;color:#555;">
                    <span id="demoCountLabel">Total residents found: 0</span>
                </div>

                <!-- Result table -->
                <div class="segment-table-wrapper" style="max-height:300px;overflow:auto;border:1px solid #ddd;border-radius:.5rem;">
                    <table class="data-table" id="demoTable" style="min-width:600px;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Civil Status</th>
                                <th>Purok</th>
                            </tr>
                        </thead>
                        <tbody id="demoTableBody">
                            <!-- filled by JS -->
                        </tbody>
                    </table>
                </div>

                <div class="segment-note" style="font-size:.75rem;color:#777;margin-top:.5rem;">
                    Showing first 200 results (if more exist). CSV export will include all filtered residents.
                </div>
            </div>
        </div>

    </main>
    <script src="./script/report.js?v=3"></script>
</body>
</html>
