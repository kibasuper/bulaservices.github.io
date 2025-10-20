<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Management System - Resident Demographics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/barangay-reporting&analytics.css">
    
    
</head>
<body>
<?php include 'templates/sidebar.php'; ?>
<?php include 'templates/topbar.php'; ?>
    <!-- Main Content -->
    <main>
        
        <div class="container mt-4">
            <div class="report-header">
                <h1><i class="fas fa-users me-2"></i> Resident Demographics Report</h1>
                <p class="text-muted">As of <span id="report-date">October 25, 2023</span></p>
            </div>

            <div class="filter-section">
                <div class="row">
                    <div class="col-md-4">
                        <label for="date-range" class="form-label">Date Range</label>
                        <select id="date-range" class="form-select">
                            <option selected>Last 30 Days</option>
                            <option>Last 3 Months</option>
                            <option>Last 6 Months</option>
                            <option>Last Year</option>
                            <option>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="barangay" class="form-label">Barangay</label>
                        <select id="barangay" class="form-select">
                            <option selected>San Antonio</option>
                            <option>San Isidro</option>
                            <option>San Miguel</option>
                            <option>San Roque</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="summary-card bg-primary">
                        <h5><i class="fas fa-user-friends me-2"></i>Total Residents</h5>
                        <p>4,382</p>
                        <small>100% of population</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card bg-info">
                        <h5><i class="fas fa-male me-2"></i>Male Residents</h5>
                        <p>2,154</p>
                        <small>49.2% of population</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card bg-success">
                        <h5><i class="fas fa-female me-2"></i>Female Residents</h5>
                        <p>2,228</p>
                        <small>50.8% of population</small>
                    </div>
                </div>
            </div>

            <!-- Age Group Report -->
            <div class="report-card">
                <div class="report-card-header">
                    <h3><i class="fas fa-chart-bar me-2"></i>Population by Age Group</h3>
                </div>
                <div class="report-card-body">
                    <div class="chart-container">
                        <canvas id="ageGroupChart"></canvas>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Age Group</th>
                                <th>Male</th>
                                <th>Female</th>
                                <th>Total</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>0-12 (Children)</td>
                                <td>412</td>
                                <td>398</td>
                                <td>810</td>
                                <td>18.5%</td>
                            </tr>
                            <tr>
                                <td>13-17 (Teenagers)</td>
                                <td>215</td>
                                <td>223</td>
                                <td>438</td>
                                <td>10.0%</td>
                            </tr>
                            <tr>
                                <td>18-35 (Young Adults)</td>
                                <td>645</td>
                                <td>712</td>
                                <td>1,357</td>
                                <td>31.0%</td>
                            </tr>
                            <tr>
                                <td>36-59 (Adults)</td>
                                <td>612</td>
                                <td>635</td>
                                <td>1,247</td>
                                <td>28.5%</td>
                            </tr>
                            <tr>
                                <td>60+ (Seniors)</td>
                                <td>270</td>
                                <td>260</td>
                                <td>530</td>
                                <td>12.0%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Household Composition -->
            <div class="report-card">
                <div class="report-card-header">
                    <h3><i class="fas fa-home me-2"></i>Household Composition</h3>
                </div>
                <div class="report-card-body">
                    <div class="chart-container">
                        <canvas id="householdChart"></canvas>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Household Type</th>
                                <th>Number of Households</th>
                                <th>Residents</th>
                                <th>Average Members</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Single-person</td>
                                <td>125</td>
                                <td>125</td>
                                <td>1.0</td>
                            </tr>
                            <tr>
                                <td>Nuclear Family</td>
                                <td>1,024</td>
                                <td>3,482</td>
                                <td>3.4</td>
                            </tr>
                            <tr>
                                <td>Extended Family</td>
                                <td>287</td>
                                <td>1,245</td>
                                <td>4.3</td>
                            </tr>
                            <tr>
                                <td>Multi-family</td>
                                <td>42</td>
                                <td>210</td>
                                <td>5.0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Employment Status -->
            <div class="report-card">
                <div class="report-card-header">
                    <h3><i class="fas fa-briefcase me-2"></i>Employment Status (Ages 18+)</h3>
                </div>
                <div class="report-card-body">
                    <div class="chart-container">
                        <canvas id="employmentChart"></canvas>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Employment Status</th>
                                <th>Male</th>
                                <th>Female</th>
                                <th>Total</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Employed (Full-time)</td>
                                <td>1,012</td>
                                <td>845</td>
                                <td>1,857</td>
                                <td>56.3%</td>
                            </tr>
                            <tr>
                                <td>Employed (Part-time)</td>
                                <td>215</td>
                                <td>312</td>
                                <td>527</td>
                                <td>16.0%</td>
                            </tr>
                            <tr>
                                <td>Self-employed</td>
                                <td>287</td>
                                <td>198</td>
                                <td>485</td>
                                <td>14.7%</td>
                            </tr>
                            <tr>
                                <td>Unemployed</td>
                                <td>142</td>
                                <td>156</td>
                                <td>298</td>
                                <td>9.0%</td>
                            </tr>
                            <tr>
                                <td>Retired</td>
                                <td>98</td>
                                <td>87</td>
                                <td>185</td>
                                <td>5.6%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-muted mt-4">
                <p>Generated by Barangay Management System on <span id="generated-date">October 25, 2023</span></p>
                <p>This is an automated report. For questions, please contact the Barangay Office.</p>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="scripts/barangay-reporting&analytics.js"></script>
    <script src="scripts/sidebar.js"></script>
        
</body>
</html>