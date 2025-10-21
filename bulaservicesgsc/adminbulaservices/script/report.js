// script/report.js — trust server labels; male/female; CSV + Print-to-PDF exports
document.addEventListener('DOMContentLoaded', () => {
  if (typeof ChartDataLabels !== 'undefined') Chart.register(ChartDataLabels);

  if (typeof flatpickr !== 'undefined') {
    flatpickr('#dateRangeStart', { dateFormat: 'Y-m-d', defaultDate: new Date(new Date().setMonth(new Date().getMonth() - 1)) });
    flatpickr('#dateRangeEnd',   { dateFormat: 'Y-m-d', defaultDate: new Date() });
  }

  let requestsChart, requestsPieChart, ageChart, genderChart;
  let lastApi = null; // keep latest payload for exports

  const $ = (id) => document.getElementById(id);
  const hasEl = (id) => !!$(id);
  const peso = (n) => `₱${Number(n || 0).toLocaleString()}`;

  // Must match server order
  const CANON_TYPES = [
    'Barangay Clearance',
    'Business Permit',
    'Community Tax Cert.',
    'Cert. of Indigency',
    'Cert. of Residency',
    'Low Income Cert.',
    'Proof of Income',
    'Individual Voluntary Statement',
    'Gym Reservation'
  ];

  const isOther = (label) => {
    const n = String(label || '').trim().toLowerCase();
    return !n || n === 'other' || n === 'others';
  };

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
    const res = await fetch(`./server/report_api.php?${qs.toString()}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });

    let data;
    const ctype = res.headers.get('content-type') || '';
    if (ctype.includes('application/json')) data = await res.json();
    else throw new Error(await res.text() || `HTTP ${res.status}`);

    if (!res.ok || !data.ok) throw new Error((data && data.error) || `HTTP ${res.status}`);
    return data;
  }

  function hydrateUIFromApi(api) {
    lastApi = api;

    // Dates
    if (hasEl('dateRangeStart')) $('dateRangeStart').value = api?.period?.start_date || '';
    if (hasEl('dateRangeEnd'))   $('dateRangeEnd').value   = api?.period?.end_date   || '';

    // Summary tiles
    if (hasEl('totalRequests'))       $('totalRequests').textContent = api?.summary?.totalRequests ?? 0;
    if (hasEl('pendingApprovals'))    $('pendingApprovals').textContent = api?.summary?.pendingApprovals ?? 0;
    if (hasEl('totalRevenue'))        $('totalRevenue').textContent = peso(api?.summary?.totalRevenue ?? 0);
    if (hasEl('registeredResidents')) $('registeredResidents').textContent = Number(api?.summary?.registeredResidents ?? 0).toLocaleString();

    // Cosmetic deltas
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

    // ===== Requests (trust server keys) =====
    const byType = (api?.requests && api.requests.byType) ? api.requests.byType : {};
    const reqAgg = {};
    CANON_TYPES.forEach(t => (reqAgg[t] = { total: 0, approved: 0, rejected: 0, pending: 0 }));

    Object.keys(byType).forEach(label => {
      if (isOther(label)) return;
      const r = byType[label] || {};
      if (!(label in reqAgg)) reqAgg[label] = { total: 0, approved: 0, rejected: 0, pending: 0 };
      reqAgg[label].total    = Number(r.total || 0);
      reqAgg[label].approved = Number(r.approved || 0);
      reqAgg[label].rejected = Number(r.rejected || 0);
      reqAgg[label].pending  = Number(r.pending || 0);
    });

    // Requests table
    if (hasEl('requestsTableBody')) {
      const tbody = $('requestsTableBody');
      tbody.innerHTML = '';
      CANON_TYPES.forEach(t => {
        const r = reqAgg[t] || { total:0, approved:0, rejected:0, pending:0 };
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

    // Requests charts
    const labels = CANON_TYPES.slice();
    const approvedMap = {}, rejectedMap = {}, pendingMap = {}, totalsMap = {};
    labels.forEach(t => {
      const r = reqAgg[t];
      approvedMap[t] = r.approved;
      rejectedMap[t] = r.rejected;
      pendingMap[t]  = r.pending;
      totalsMap[t]   = r.total;
    });
    if (hasEl('requestsChart'))    updateRequestsChart(labels, approvedMap, rejectedMap, pendingMap);
    if (hasEl('requestsPieChart')) updateRequestsPieChart(labels, totalsMap);

    // ===== Sales (trust server keys) =====
    const byTypeSales = (api?.sales && api.sales.byType)
      ? api.sales.byType
      : ((api?.financial && api.financial.byType) ? api.financial.byType : {});

    const salesAgg = {};
    CANON_TYPES.forEach(t => (salesAgg[t] = 0));
    Object.keys(byTypeSales).forEach(label => {
      if (isOther(label)) return;
      if (!(label in salesAgg)) salesAgg[label] = 0;
      salesAgg[label] = Number(byTypeSales[label] || 0);
    });

    const grandTotal = Number((api?.sales?.total) ?? (api?.financial?.total) ?? 0);

    if (hasEl('financialTableBody')) {
      const ftbody = $('financialTableBody');
      ftbody.innerHTML = '';
      CANON_TYPES.forEach(t => {
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

    // ===== Demographics (male/female only) =====
    const ageDist = (api?.demographics && api.demographics.age) || {};
    const genderDist = (api?.demographics && api.demographics.gender) || {};

    if (hasEl('ageChart'))    updateAgeChart(['0-17','18-35','36-55','56+'], ageDist);
    if (hasEl('genderChart')) updateGenderChart(['Male','Female'], genderDist);
  }

  async function loadDataForPeriod(period) {
    try { hydrateUIFromApi(await fetchReport({ period })); }
    catch (e) { console.error(e); alert('Failed to load report. Details in console.'); }
  }
  async function loadCustomRange(startYmd, endYmd) {
    try { hydrateUIFromApi(await fetchReport({ period: 'custom', start_date: startYmd, end_date: endYmd })); }
    catch (e) { console.error(e); alert('Failed to load report. Details in console.'); }
  }

  // ===== Charts =====
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
          backgroundColor: ['#2563eb','#059669','#7c3aed','#3b82f6','#10b981','#d97706','#84cc16','#ec4899','#0ea5e9'],
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
      plugins: (typeof ChartDataLabels !== 'undefined') ? [ChartDataLabels] : []
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
      plugins: (typeof ChartDataLabels !== 'undefined') ? [ChartDataLabels] : []
    });
  }

  function updateGenderChart(labels, dataMap) {
    const el = $('genderChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (genderChart) genderChart.destroy();

    const keyed = {
      Male: Number(dataMap.male || dataMap.Male || 0),
      Female: Number(dataMap.female || dataMap.Female || 0)
    };

    genderChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels, // ['Male','Female']
        datasets: [{
          data: labels.map(l => keyed[l] || 0),
          backgroundColor: ['#3b82f6','#ec4899']
        }]
      },
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
      plugins: (typeof ChartDataLabels !== 'undefined') ? [ChartDataLabels] : []
    });
  }

  // ====== EXPORTS ======

  // Figure out which section a clicked export button is in
  function detectReportContext(btn) {
    const section = btn.closest('.report-section') || btn.closest('section') || document;
    if (section.querySelector('#requestsTableBody') || section.querySelector('#requestsChart')) return 'requests';
    if (section.querySelector('#financialTableBody')) return 'sales';
    if (section.querySelector('#ageChart') || section.querySelector('#genderChart')) return 'demographics';
    // fallback via header text
    const h = (section.querySelector('.report-header h2')?.textContent || '').toLowerCase();
    if (h.includes('request')) return 'requests';
    if (h.includes('sale')) return 'sales';
    if (h.includes('demographic') || h.includes('resident')) return 'demographics';
    return 'requests';
  }

  // CSV helpers
  function csvEscape(v) {
    if (v == null) return '';
    const s = String(v);
    if (/[",\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
    return s;
  }
  function downloadBlob(text, filename, type = 'text/csv;charset=utf-8;') {
    const blob = new Blob([text], { type });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
  }

  function buildRequestsCSV(api) {
    const rows = [['Service Type','Total','Approved','Rejected','Pending']];
    const byType = api?.requests?.byType || {};
    CANON_TYPES.forEach(t => {
      const r = byType[t] || { total:0, approved:0, rejected:0, pending:0 };
      rows.push([t, r.total||0, r.approved||0, r.rejected||0, r.pending||0]);
    });
    return rows.map(r => r.map(csvEscape).join(',')).join('\n');
  }
  function buildSalesCSV(api) {
    const rows = [['Service Type','Amount (PHP)']];
    const byType = (api?.sales?.byType) || (api?.financial?.byType) || {};
    CANON_TYPES.forEach(t => rows.push([t, Number(byType[t]||0).toFixed(2)]));
    rows.push(['Total', Number((api?.sales?.total) ?? (api?.financial?.total) ?? 0).toFixed(2)]);
    return rows.map(r => r.map(csvEscape).join(',')).join('\n');
  }
  function buildDemographicsCSV(api) {
    const age = api?.demographics?.age || {};
    const gender = api?.demographics?.gender || {};
    const rows = [];
    rows.push(['Resident Demographics']);
    rows.push([`Period: ${api?.period?.start_date || ''} to ${api?.period?.end_date || ''}`]);
    rows.push(['Generated at', new Date().toLocaleString()]);
    rows.push([]);
    rows.push(['Age Distribution']);
    rows.push(['0-17','18-35','36-55','56+']);
    rows.push([
      Number(age['0-17']||0),
      Number(age['18-35']||0),
      Number(age['36-55']||0),
      Number(age['56+']||0)
    ]);
    rows.push([]);
    rows.push(['Gender Distribution']);
    rows.push(['Male','Female']);
    rows.push([Number(gender.male||0), Number(gender.female||0)]);
    return rows.map(r => r.map(csvEscape).join(',')).join('\n');
  }

  // Print-to-PDF helpers
  function canvasDataURL(id) {
    const c = $(id);
    if (!c || typeof c.toDataURL !== 'function') return null;
    try { return c.toDataURL('image/png'); } catch { return null; }
  }
  function openPrintable(title, innerHtml) {
    const w = window.open('', '_blank', 'noopener,noreferrer');
    if (!w) { alert('Pop-up blocked. Please allow pop-ups to export PDF.'); return; }
    const styles = `
      <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, sans-serif; margin: 24px; color: #111; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        h2 { font-size: 16px; margin: 18px 0 8px; }
        .meta { font-size: 12px; color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
        th { background: #f5f5f5; text-align: left; }
        .charts { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 8px; }
        .charts img { width: 100%; height: auto; border: 1px solid #eee; padding: 6px; background: #fff; }
        @media print { @page { size: A4; margin: 12mm; } body { margin: 0; } .print-hide { display: none !important; } }
      </style>
    `;
    const now = new Date().toLocaleString();
    const period = `${lastApi?.period?.start_date || ''} to ${lastApi?.period?.end_date || ''}`;
    w.document.write(`
      <html>
        <head><title>${title}</title>${styles}</head>
        <body>
          <h1>${title}</h1>
          <div class="meta">Period: ${period} &nbsp;•&nbsp; Generated: ${now}</div>
          ${innerHtml}
          <div class="print-hide" style="margin-top:16px;">
            <button onclick="window.print()">Print / Save as PDF</button>
          </div>
        </body>
      </html>
    `);
    w.document.close();
    setTimeout(() => { try { w.focus(); w.print(); } catch {} }, 400);
  }
  function buildRequestsPrintable() {
    const byType = lastApi?.requests?.byType || {};
    const rows = CANON_TYPES.map(t => {
      const r = byType[t] || { total:0, approved:0, rejected:0, pending:0 };
      return `<tr>
        <td>${t}</td>
        <td>${r.total||0}</td>
        <td>${r.approved||0}</td>
        <td>${r.rejected||0}</td>
        <td>${r.pending||0}</td>
      </tr>`;
    }).join('');
    const barImg = canvasDataURL('requestsChart');
    const pieImg = canvasDataURL('requestsPieChart');
    return `
      <h2>Service Requests</h2>
      <table>
        <thead><tr><th>Service Type</th><th>Total</th><th>Approved</th><th>Rejected</th><th>Pending</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <div class="charts">
        ${barImg ? `<div><img src="${barImg}" alt="Requests Chart"></div>` : ''}
        ${pieImg ? `<div><img src="${pieImg}" alt="Requests Distribution"></div>` : ''}
      </div>
    `;
  }
  function buildSalesPrintable() {
    const byType = (lastApi?.sales?.byType) || (lastApi?.financial?.byType) || {};
    const total  = Number((lastApi?.sales?.total) ?? (lastApi?.financial?.total) ?? 0);
    const rows = CANON_TYPES.map(t => `<tr><td>${t}</td><td>${peso(Number(byType[t]||0))}</td></tr>`).join('');
    return `
      <h2>Sales</h2>
      <table>
        <thead><tr><th>Service Type</th><th>Amount</th></tr></thead>
        <tbody>${rows}</tbody>
        <tfoot><tr><th>Total</th><th>${peso(total)}</th></tr></tfoot>
      </table>
    `;
  }
  function buildDemographicsPrintable() {
    const age = lastApi?.demographics?.age || {};
    const gender = lastApi?.demographics?.gender || {};
    const ageRow = `
      <tr>
        <td>${Number(age['0-17']||0)}</td>
        <td>${Number(age['18-35']||0)}</td>
        <td>${Number(age['36-55']||0)}</td>
        <td>${Number(age['56+']||0)}</td>
      </tr>`;
    const genderRow = `<tr><td>${Number(gender.male||0)}</td><td>${Number(gender.female||0)}</td></tr>`;
    const ageImg = canvasDataURL('ageChart');
    const genderImg = canvasDataURL('genderChart');
    return `
      <h2>Resident Demographics</h2>
      <table>
        <caption style="text-align:left; font-weight:600; margin-bottom:6px;">Age Distribution</caption>
        <thead><tr><th>0–17</th><th>18–35</th><th>36–55</th><th>56+</th></tr></thead>
        <tbody>${ageRow}</tbody>
      </table>
      <table>
        <caption style="text-align:left; font-weight:600; margin:10px 0 6px;">Gender Distribution</caption>
        <thead><tr><th>Male</th><th>Female</th></tr></thead>
        <tbody>${genderRow}</tbody>
      </table>
      <div class="charts">
        ${ageImg ? `<div><img src="${ageImg}" alt="Age Chart"></div>` : ''}
        ${genderImg ? `<div><img src="${genderImg}" alt="Gender Chart"></div>` : ''}
      </div>
    `;
  }

  // Hook up export buttons
  document.querySelectorAll('.export-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
      if (!lastApi) { alert('Data not loaded yet.'); return; }

      const icon = this.querySelector('i');
      const isPDF = icon && icon.classList.contains('fa-file-pdf');
      const section = detectReportContext(this);
      const periodSlug = `${lastApi?.period?.start_date || ''}_to_${lastApi?.period?.end_date || ''}`.replace(/[^\d\-to_]/g, '');
      const dateStamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');

      if (isPDF) {
        if (section === 'requests')          openPrintable('Service Requests Report', buildRequestsPrintable());
        else if (section === 'sales')        openPrintable('Sales Report', buildSalesPrintable());
        else if (section === 'demographics') openPrintable('Resident Demographics Report', buildDemographicsPrintable());
      } else {
        let csvText = '';
        let fname = '';
        if (section === 'requests') {
          csvText = buildRequestsCSV(lastApi);
          fname = `requests_${periodSlug}_${dateStamp}.csv`;
        } else if (section === 'sales') {
          csvText = buildSalesCSV(lastApi);
          fname = `sales_${periodSlug}_${dateStamp}.csv`;
        } else {
          csvText = buildDemographicsCSV(lastApi);
          fname = `demographics_${periodSlug}_${dateStamp}.csv`;
        }
        downloadBlob(csvText, fname);
      }
    });
  });

  // Time filter + tabs + custom range
  document.querySelectorAll('.time-filter').forEach((filter) => {
    filter.addEventListener('click', () => {
      document.querySelectorAll('.time-filter').forEach((f) => f.classList.remove('active'));
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

  // Kickoff
  loadDataForPeriod('this_month');
});
