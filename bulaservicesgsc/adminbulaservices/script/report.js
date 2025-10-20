// script/report.js
document.addEventListener('DOMContentLoaded', () => {
  if (typeof ChartDataLabels !== 'undefined') Chart.register(ChartDataLabels);

  if (typeof flatpickr !== 'undefined') {
    flatpickr('#dateRangeStart', { dateFormat: 'Y-m-d', defaultDate: new Date(new Date().setMonth(new Date().getMonth() - 1)) });
    flatpickr('#dateRangeEnd',   { dateFormat: 'Y-m-d', defaultDate: new Date() });
  }

  let requestsChart, requestsPieChart, ageChart, genderChart;

  const $ = (id) => document.getElementById(id);
  const hasEl = (id) => !!$(id);
  const peso = (n) => `₱${Number(n || 0).toLocaleString()}`;

  function prettyType(raw) {
    if (!raw && raw !== 0) return '';
    const k = String(raw).trim().toLowerCase().replace(/\s+/g, '_');
    const map = {
      'barangay_clearance':'Barangay Clearance',
      'brgy_clearance':'Barangay Clearance','brgyclearance':'Barangay Clearance','brgy_clearance_cert':'Barangay Clearance','bc':'Barangay Clearance','barangay-clearance':'Barangay Clearance','barangay clearance':'Barangay Clearance',
      'business_permit':'Business Permit',
      'community_tax_cert':'Community Tax Cert.','community_tax_certificate':'Community Tax Cert.',
      'certificate_of_indigency':'Cert. of Indigency',
      'certificate_of_residency':'Cert. of Residency',
      'low_income_cert':'Low Income Cert.',
      'proof_of_income':'Proof of Income',
      'gym_reservation':'Gym Reservation','gym':'Gym Reservation','reservation':'Gym Reservation','gym reservation':'Gym Reservation'
    };
    return map[k] || raw;
  }

  const setChange = (wrapId, val) => {
    if (!hasEl(wrapId)) return;
    const wrap = $(wrapId);
    const icon = wrap.querySelector('i');
    const up = Number(val) >= 0;
    wrap.className = `card-change ${up ? 'change-up' : 'change-down'}`;
    if (icon) icon.className = `fas ${up ? 'fa-arrow-up' : 'fa-arrow-down'}`;
  };

  async function fetchReport(params) {
    const qs = new URLSearchParams(params);
    const url = `./server/report_api.php?${qs.toString()}`;
    const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });

    let data;
    const ctype = res.headers.get('content-type') || '';
    if (ctype.includes('application/json')) data = await res.json();
    else throw new Error(await res.text() || `HTTP ${res.status}`);

    if (!res.ok || !data.ok) throw new Error((data && data.error) || `HTTP ${res.status}`);
    return data;
  }

  function hydrateUIFromApi(api) {
    if (hasEl('dateRangeStart')) $('dateRangeStart').value = api.period.start_date;
    if (hasEl('dateRangeEnd'))   $('dateRangeEnd').value   = api.period.end_date;

    if (hasEl('totalRequests'))       $('totalRequests').textContent = api.summary.totalRequests;
    if (hasEl('pendingApprovals'))    $('pendingApprovals').textContent = api.summary.pendingApprovals;
    if (hasEl('totalRevenue'))        $('totalRevenue').textContent = peso(api.summary.totalRevenue);
    if (hasEl('registeredResidents')) $('registeredResidents').textContent = Number(api.summary.registeredResidents || 0).toLocaleString();

    const rand = (n) => Math.floor(Math.random() * n) + 1;
    const requestsChange = rand(20), approvalsChange = rand(10), revenueChange = rand(25), residentsChange = rand(8);
    if (hasEl('requestsChangeValue'))  $('requestsChangeValue').textContent  = `${requestsChange}%`;
    if (hasEl('approvalsChangeValue')) $('approvalsChangeValue').textContent = `${approvalsChange}%`;
    if (hasEl('revenueChangeValue'))   $('revenueChangeValue').textContent   = `${revenueChange}%`;
    if (hasEl('residentsChangeValue')) $('residentsChangeValue').textContent = `${residentsChange}%`;
    setChange('requestsChange', requestsChange);
    setChange('approvalsChange', approvalsChange);
    setChange('revenueChange', revenueChange);
    setChange('residentsChange', residentsChange);

    // ===== Service Requests =====
    const byTypeRaw = (api.requests && api.requests.byType) ? api.requests.byType : {};
    const byTypeSales = (api.sales && api.sales.byType) ? api.sales.byType : (api.financial && api.financial.byType ? api.financial.byType : {});

    const reqKeys = Object.keys(byTypeRaw).map(prettyType);
    const finKeys = Object.keys(byTypeSales).map(prettyType);

    const DEFAULT_ORDER = [
      'Barangay Clearance','Business Permit','Community Tax Cert.',
      'Cert. of Indigency','Cert. of Residency','Low Income Cert.',
      'Proof of Income','Gym Reservation'
    ];

    const serviceTypes = Array.from(new Set([...DEFAULT_ORDER, ...reqKeys, ...finKeys]))
      .filter((lbl, _, arr) => (arr.length === 1 ? lbl === arr[0] : true));

    const reqAgg = {};
    serviceTypes.forEach(lbl => (reqAgg[lbl] = { total: 0, approved: 0, rejected: 0, pending: 0 }));
    for (const raw in byTypeRaw) {
      const lbl = prettyType(raw);
      const r = byTypeRaw[raw] || {};
      reqAgg[lbl] = {
        total: Number(r.total || 0),
        approved: Number(r.approved || 0),
        rejected: Number(r.rejected || 0),
        pending: Number(r.pending || 0)
      };
    }

    if (hasEl('requestsTableBody')) {
      const tbody = $('requestsTableBody');
      tbody.innerHTML = '';
      serviceTypes.forEach(t => {
        const r = reqAgg[t];
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${t}</td>
          <td>${r.total}</td>
          <td>${r.approved}</td>
          <td>${r.rejected}</td>
          <td>${r.pending}</td>`;
        tbody.appendChild(tr);
      });
    }

    const approvedMap = {}, rejectedMap = {}, pendingMap = {}, totalsMap = {};
    serviceTypes.forEach(t => {
      approvedMap[t] = reqAgg[t].approved;
      rejectedMap[t] = reqAgg[t].rejected;
      pendingMap[t]  = reqAgg[t].pending;
      totalsMap[t]   = reqAgg[t].total;
    });

    if (hasEl('requestsChart'))    updateRequestsChart(serviceTypes, approvedMap, rejectedMap, pendingMap);
    if (hasEl('requestsPieChart')) updateRequestsPieChart(serviceTypes, totalsMap);

    // ===== Sales Report (table only; % removed) =====
    const salesAgg = {};
    serviceTypes.forEach(t => (salesAgg[t] = 0));
    for (const raw in byTypeSales) {
      const lbl = prettyType(raw);
      salesAgg[lbl] = Number(byTypeSales[raw] || 0);
    }
    const grandTotal = Number((api.sales && api.sales.total) || (api.financial && api.financial.total) || 0);

    if (hasEl('financialTableBody')) {
      const ftbody = $('financialTableBody');
      ftbody.innerHTML = '';
      serviceTypes.forEach(t => {
        const amt = salesAgg[t] || 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${t}</td>
          <td>${peso(amt)}</td>
          <td>${peso(amt)}</td>`;
        ftbody.appendChild(tr);
      });
      const totalRow = document.createElement('tr');
      totalRow.style.fontWeight = '600';
      totalRow.innerHTML = `
        <td>Total</td>
        <td>${peso(grandTotal)}</td>
        <td>${peso(grandTotal)}</td>`;
      ftbody.appendChild(totalRow);
    }

    // ===== Demographics =====
    const ageDist = (api.demographics && api.demographics.age) || {};
    const genderDist = (api.demographics && api.demographics.gender) || {};

    if (hasEl('ageChart'))    updateAgeChart(['0-17','18-35','36-55','56+'], ageDist);
    if (hasEl('genderChart')) updateGenderChart(['Male','Female','Other'], genderDist);
  }

  async function loadDataForPeriod(period) {
    try { hydrateUIFromApi(await fetchReport({ period })); }
    catch (e) { console.error(e); alert('Failed to load report. Details in console.'); }
  }
  async function loadCustomRange(startYmd, endYmd) {
    try { hydrateUIFromApi(await fetchReport({ period: 'custom', start_date: startYmd, end_date: endYmd })); }
    catch (e) { console.error(e); alert('Failed to load report. Details in console.'); }
  }

  function updateRequestsChart(labels, approvedData, rejectedData, pendingData) {
    const el = $('requestsChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (requestsChart) requestsChart.destroy();

    requestsChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Approved', data: labels.map(l => approvedData[l] || 0), backgroundColor: '#059669', borderColor: '#059669', borderWidth: 1 },
          { label: 'Rejected', data: labels.map(l => rejectedData[l] || 0), backgroundColor: '#dc2626', borderColor: '#dc2626', borderWidth: 1 },
          { label: 'Pending',  data: labels.map(l => pendingData[l]  || 0), backgroundColor: '#d97706', borderColor: '#d97706', borderWidth: 1 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'Number of Requests' } },
          x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 } }
        },
        plugins: { datalabels: { display: false } }
      }
    });
  }

  function updateRequestsPieChart(labels, dataObj) {
    const el = $('requestsPieChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (requestsPieChart) requestsPieChart.destroy();

    requestsPieChart = new Chart(ctx, {
      type: 'pie',
      data: {
        labels,
        datasets: [{
          data: labels.map(l => dataObj[l] || 0),
          backgroundColor: ['#2563eb','#059669','#7c3aed','#3b82f6','#10b981','#d97706','#84cc16','#ec4899'],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right' },
          title:  { display: true, text: 'Request Distribution' },
          datalabels: {
            formatter: (val, ctx) => {
              const total = ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
              return total ? Math.round((val/total)*100)+'%' : '0%';
            },
            color: '#fff', font: { weight: 'bold' }
          }
        }
      },
      plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : []
    });
  }

  function updateAgeChart(labels, dataMap) {
    const el = $('ageChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (ageChart) ageChart.destroy();

    const dataArr = labels.map(l => Number(dataMap[l] || 0));
    ageChart = new Chart(ctx, {
      type: 'pie',
      data: { labels, datasets: [{ data: dataArr, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#6366f1'] }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          title: { display: true, text: 'Age Distribution' },
          legend:{ position: 'right' },
          datalabels: {
            formatter: (val, ctx) => {
              const total = ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
              return total ? Math.round((val/total)*100)+'%' : '0%';
            },
            color: '#fff'
          }
        }
      },
      plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : []
    });
  }

  function updateGenderChart(labels, dataMap) {
    const el = $('genderChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (genderChart) genderChart.destroy();

    const keyed = { Male: Number(dataMap.male || dataMap.Male || 0), Female: Number(dataMap.female || dataMap.Female || 0), Other: Number(dataMap.other || dataMap.Other || 0) };
    genderChart = new Chart(ctx, {
      type: 'doughnut',
      data: { labels, datasets: [{ data: labels.map(l => keyed[l] || 0), backgroundColor: ['#3b82f6','#ec4899','#d97706'] }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          title: { display: true, text: 'Gender Distribution' },
          legend:{ position: 'right' },
          datalabels: {
            formatter: (val, ctx) => {
              const total = ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
              return total ? Math.round((val/total)*100)+'%' : '0%';
            },
            color: '#fff'
          }
        }
      },
      plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : []
    });
  }

  // events
  document.querySelectorAll('.time-filter').forEach((filter) => {
    filter.addEventListener('click', () => {
      document.querySelectorAll('.time-filter').forEach((f) => f.classList.remove('active'));
      filter.addEventListener
      filter.classList.add('active');
      const period = filter.getAttribute('data-period');
      if (period === 'custom') {
        if (hasEl('dateRangePicker')) $('dateRangePicker').style.display = 'flex';
      } else {
        if (hasEl('dateRangePicker')) $('dateRangePicker').style.display = 'none';
        loadDataForPeriod(period);
      }
    });
  });

  if (hasEl('applyDateRange')) {
    $('applyDateRange').addEventListener('click', () => {
      const s = $('dateRangeStart').value, e = $('dateRangeEnd').value;
      if (s && e && new Date(s) <= new Date(e)) loadCustomRange(s, e);
      else alert('Please select a valid date range');
    });
  }

  document.querySelectorAll('.report-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.report-tab').forEach((t) => t.classList.remove('active'));
      tab.classList.add('active');
      document.querySelectorAll('.tab-content').forEach((c) => (c.style.display = 'none'));
      const pane = document.getElementById(`${tab.getAttribute('data-tab')}-tab`);
      if (pane) pane.style.display = 'block';
    });
  });

  document.querySelectorAll('.export-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
      const format = this.querySelector('i').classList.contains('fa-file-pdf') ? 'PDF' : 'CSV';
      const reportType = this.closest('.report-header').querySelector('h2').textContent;
      alert(`Exporting ${reportType} as ${format}`);
    });
  });

  loadDataForPeriod('this_month');
});
