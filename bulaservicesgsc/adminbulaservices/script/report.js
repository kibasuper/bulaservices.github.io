// script/report.js — per-section filters, daily views, outsiders count, TX history
document.addEventListener('DOMContentLoaded', () => {
  if (typeof ChartDataLabels !== 'undefined') Chart.register(ChartDataLabels);

  // global date pickers (summary cards period)
  if (typeof flatpickr !== 'undefined') {
    flatpickr('#dateRangeStart', { dateFormat: 'Y-m-d', defaultDate: new Date(new Date().setMonth(new Date().getMonth() - 1)) });
    flatpickr('#dateRangeEnd',   { dateFormat: 'Y-m-d', defaultDate: new Date() });

    // local per-section pickers
    flatpickr('#reqDateStart', { dateFormat: 'Y-m-d' });
    flatpickr('#reqDateEnd',   { dateFormat: 'Y-m-d' });
    flatpickr('#salesDateStart', { dateFormat: 'Y-m-d' });
    flatpickr('#salesDateEnd',   { dateFormat: 'Y-m-d' });
    flatpickr('#txDateStart',    { dateFormat: 'Y-m-d' });
    flatpickr('#txDateEnd',      { dateFormat: 'Y-m-d' });
  }

  let requestsChart, requestsPieChart, ageChart, genderChart;
  let lastApi = null;

  const $ = (id) => document.getElementById(id);
  const hasEl = (id) => !!$(id);
  const peso = (n) => `₱${Number(n || 0).toLocaleString()}`;

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
  const serviceCodeToCanon = {
    barangay_clearance: 'Barangay Clearance',
    business_permit: 'Business Permit',
    cedula: 'Community Tax Cert.',
    indigency: 'Cert. of Indigency',
    residency: 'Cert. of Residency',
    low_income: 'Low Income Cert.',
    proof_income: 'Proof of Income',
    ivs: 'Individual Voluntary Statement',
    gym: 'Gym Reservation',
    gym_reservation: 'Gym Reservation'
  };

  const setChange = (wrapId, up) => {
    const wrap = $(wrapId); if (!wrap) return;
    const icon = wrap.querySelector('i');
    wrap.className = `card-change ${up ? 'change-up' : 'change-down'}`;
    if (icon) icon.className = `fas ${up ? 'fa-arrow-up' : 'fa-arrow-down'}`;
  };

  async function fetchReport(params) {
    const qs = new URLSearchParams(params);
    const res = await fetch(`./server/report_api.php?${qs.toString()}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const ctype = res.headers.get('content-type') || '';
    const data = ctype.includes('application/json') ? await res.json() : { ok:false, error: await res.text() };
    if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  }

  /* ---------------- SUMMARY (global period) ---------------- */
  function hydrateSummary(api) {
    if (hasEl('totalRequests'))       $('totalRequests').textContent = api?.summary?.totalRequests ?? 0;
    if (hasEl('pendingApprovals'))    $('pendingApprovals').textContent = api?.summary?.pendingApprovals ?? 0;
    if (hasEl('totalRevenue'))        $('totalRevenue').textContent = peso(api?.summary?.totalRevenue ?? 0);
    if (hasEl('registeredResidents')) $('registeredResidents').textContent = Number(api?.summary?.registeredResidents ?? 0).toLocaleString();
    if (hasEl('registeredOutsiders')) $('registeredOutsiders').textContent = Number(api?.summary?.registeredOutsiders ?? 0).toLocaleString();

    // cosmetic arrows
    setChange('requestsChange', true);
    setChange('approvalsChange', false);
    setChange('revenueChange', true);
    setChange('residentsChange', true);
  }

  /* ---------------- REQUESTS (per-section filters) ---------------- */
  function renderRequestsSummary(byType) {
    const tbody = $('requestsTableBody'); if (!tbody) return;
    const labels = Object.keys(byType).length ? Object.keys(byType) : CANON_TYPES;
    tbody.innerHTML = '';
    labels.forEach(l => {
      const r = byType[l] || { total:0, approved:0, rejected:0, pending:0 };
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${l}</td><td>${r.total||0}</td><td>${r.approved||0}</td><td>${r.rejected||0}</td><td>${r.pending||0}</td>`;
      tbody.appendChild(tr);
    });

    // charts
    if (hasEl('requestsChart')) {
      const ctx = $('requestsChart').getContext('2d');
      if (requestsChart) requestsChart.destroy();
      const lbls = labels;
      requestsChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: lbls,
          datasets: [
            { label:'Approved', data: lbls.map(k => (byType[k]?.approved || 0)), backgroundColor:'#059669', borderColor:'#059669', borderWidth:1 },
            { label:'Rejected', data: lbls.map(k => (byType[k]?.rejected || 0)), backgroundColor:'#dc2626', borderColor:'#dc2626', borderWidth:1 },
            { label:'Pending',  data: lbls.map(k => (byType[k]?.pending  || 0)), backgroundColor:'#d97706', borderColor:'#d97706', borderWidth:1 }
          ]
        },
        options: {
          responsive:true, maintainAspectRatio:false,
          scales: { y:{ beginAtZero:true }, x:{ ticks:{ autoSkip:false, maxRotation:45, minRotation:45 } } },
          plugins: { datalabels: { display:false } }
        }
      });
    }
    if (hasEl('requestsPieChart')) {
      const ctx = $('requestsPieChart').getContext('2d');
      if (requestsPieChart) requestsPieChart.destroy();
      const lbls = labels;
      const totals = lbls.map(k => (byType[k]?.total || 0));
      requestsPieChart = new Chart(ctx, {
        type:'pie',
        data:{ labels: lbls, datasets:[{ data: totals, backgroundColor: ['#2563eb','#059669','#7c3aed','#3b82f6','#10b981','#d97706','#84cc16','#ec4899','#0ea5e9'] }] },
        options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right' }, title:{ display:true, text:'Request Distribution' } } }
      });
    }
  }

  function renderRequestsDaily(daily, selectedServiceCanon) {
    // Build table header for daily view
    const thead = $('requestsTableHead');
    const tbody = $('requestsTableBody');
    if (!thead || !tbody) return;

    if (selectedServiceCanon) {
      thead.innerHTML = `<tr><th>Date</th><th>Total</th><th>Approved</th><th>Rejected</th><th>Pending</th></tr>`;
      tbody.innerHTML = '';
      Object.keys(daily).sort().forEach(ymd => {
        const bySvc = daily[ymd] || {};
        const r = bySvc[selectedServiceCanon] || { total:0, approved:0, rejected:0, pending:0 };
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${ymd}</td><td>${r.total||0}</td><td>${r.approved||0}</td><td>${r.rejected||0}</td><td>${r.pending||0}</td>`;
        tbody.appendChild(tr);
      });
    } else {
      thead.innerHTML = `<tr><th>Date</th><th>Service Type</th><th>Total</th><th>Approved</th><th>Rejected</th><th>Pending</th></tr>`;
      tbody.innerHTML = '';
      Object.keys(daily).sort().forEach(ymd => {
        const bySvc = daily[ymd] || {};
        Object.keys(bySvc).forEach(svc => {
          const r = bySvc[svc] || { total:0, approved:0, rejected:0, pending:0 };
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${ymd}</td><td>${svc}</td><td>${r.total||0}</td><td>${r.approved||0}</td><td>${r.rejected||0}</td><td>${r.pending||0}</td>`;
          tbody.appendChild(tr);
        });
      });
    }

    // Clear charts for daily (simplify v1)
    if (requestsChart) { requestsChart.destroy(); requestsChart = null; }
    if (requestsPieChart) { requestsPieChart.destroy(); requestsPieChart = null; }
  }

  /* ---------------- SALES ---------------- */
  function renderSalesSummary(byType, total) {
    if (!hasEl('financialTableBody')) return;
    const tb = $('financialTableBody');
    tb.innerHTML = '';
    const labels = CANON_TYPES.filter(t => byType[t] != null).concat(Object.keys(byType).filter(t => !CANON_TYPES.includes(t)));
    labels.forEach(l => {
      const amt = Number(byType[l] || 0);
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${l}</td><td>${peso(amt)}</td>`;
      tb.appendChild(tr);
    });
    const tr = document.createElement('tr');
    tr.style.fontWeight = '600';
    tr.innerHTML = `<td>Total</td><td>${peso(total || 0)}</td>`;
    tb.appendChild(tr);
  }

  function renderSalesDaily(daily, selectedServiceCanon) {
    const thead = $('financialTableHead');
    const tb = $('financialTableBody');
    if (!thead || !tb) return;

    if (selectedServiceCanon) {
      thead.innerHTML = `<tr><th>Date</th><th>Amount</th></tr>`;
      tb.innerHTML = '';
      Object.keys(daily).sort().forEach(ymd => {
        const bySvc = daily[ymd] || {};
        const amt = Number(bySvc[selectedServiceCanon] || 0);
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${ymd}</td><td>${peso(amt)}</td>`;
        tb.appendChild(tr);
      });
    } else {
      thead.innerHTML = `<tr><th>Date</th><th>Service Type</th><th>Amount</th></tr>`;
      tb.innerHTML = '';
      Object.keys(daily).sort().forEach(ymd => {
        const bySvc = daily[ymd] || {};
        Object.keys(bySvc).forEach(svc => {
          const amt = Number(bySvc[svc] || 0);
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${ymd}</td><td>${svc}</td><td>${peso(amt)}</td>`;
          tb.appendChild(tr);
        });
      });
    }
  }

  /* ---------------- TX HISTORY ---------------- */
  function renderTxTable(tx) {
    const body = $('txTableBody'); if (!body) return;
    body.innerHTML = '';
    (tx?.rows || []).forEach(row => {
      (row.items || []).forEach(it => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.payment_date || ''}</td>
          <td>${row.receipt_number || ''}</td>
          <td>${row.user?.name || row.user?.email || row.user?.id || ''}</td>
          <td>${row.cashier?.name || row.cashier?.id || ''}</td>
          <td>${it.service || ''}</td>
          <td>${it.request_id || ''}</td>
          <td>${peso(it.amount || 0)}</td>
        `;
        body.appendChild(tr);
      });
    });

    // pager
    const page = tx?.page || 1;
    const size = tx?.page_size || 30;
    const totalRows = tx?.total_rows || 0;
    const maxPage = Math.max(1, Math.ceil(totalRows / size));
    $('txPageInfo').textContent = `Page ${page} of ${maxPage} — ${totalRows} rows`;
    $('txPrev').disabled = (page <= 1);
    $('txNext').disabled = (page >= maxPage);
    $('txPrev').dataset.page = Math.max(1, page - 1);
    $('txNext').dataset.page = Math.min(maxPage, page + 1);
  }

  /* ---------------- FETCH + HYDRATE ---------------- */
  async function loadGlobal(period, s, e) {
    lastApi = await fetchReport({ period, start_date: s || '', end_date: e || '' });
    hydrateSummary(lastApi);
  }

  async function loadRequests() {
    const view = document.querySelector('#reqViewPills .pill.active')?.dataset.view || 'summary';
    const code = $('reqServiceSelect').value || '';
    const s = $('reqDateStart').value || '';
    const e = $('reqDateEnd').value || '';
    const params = { req_view: view, req_service: code, req_start_date: s, req_end_date: e };
    const api = await fetchReport(params);

    // reset header for summary by default
    $('requestsTableHead').innerHTML = `<tr>
      <th>Service Type</th><th>Total Requests</th><th>Approved</th><th>Rejected</th><th>Pending</th>
    </tr>`;

    if (view === 'daily') {
      const canon = code ? serviceCodeToCanon[code] : '';
      renderRequestsDaily(api?.requests?.daily || {}, canon || null);
    } else {
      renderRequestsSummary(api?.requests?.byType || {});
    }
  }

  async function loadSales() {
    const view = document.querySelector('#salesViewPills .pill.active')?.dataset.view || 'summary';
    const code = $('salesServiceSelect').value || '';
    const s = $('salesDateStart').value || '';
    const e = $('salesDateEnd').value || '';
    const params = { sales_view: view, sales_service: code, sales_start_date: s, sales_end_date: e };
    const api = await fetchReport(params);

    if (view === 'daily') {
      const canon = code ? serviceCodeToCanon[code] : '';
      renderSalesDaily(api?.sales?.daily || {}, canon || null);
    } else {
      renderSalesSummary(api?.sales?.byType || {}, api?.sales?.total || 0);
    }
  }

  async function loadTx(page = 1) {
    const code = $('txServiceSelect').value || '';
    const cashier = $('txCashier').value || '';
    const search = $('txSearch').value || '';
    const s = $('txDateStart').value || '';
    const e = $('txDateEnd').value || '';
    const params = { tx_service: code, tx_cashier: cashier, tx_search: search, tx_start_date: s, tx_end_date: e, tx_page: page, tx_page_size: 30 };
    const api = await fetchReport(params);
    renderTxTable(api?.transactions || { rows:[], page:1, page_size:30, total_rows:0 });
  }

  /* ---------------- Demographics ---------------- */
  function updateAgeChart(labels, dataMap) {
    const el = $('ageChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (ageChart) ageChart.destroy();
    const dataArr = labels.map(l => Number(dataMap[l] || 0));
    ageChart = new Chart(ctx, {
      type: 'pie',
      data: { labels, datasets: [{ data: dataArr, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#6366f1'] }] },
      options: { responsive:true, maintainAspectRatio:false, plugins:{ title:{ display:true, text:'Age Distribution' }, legend:{ position:'right' } } }
    });
  }
  function updateGenderChart(labels, dataMap) {
    const el = $('genderChart'); if (!el) return;
    const ctx = el.getContext('2d');
    if (genderChart) genderChart.destroy();
    const keyed = { Male: Number(dataMap.male || dataMap.Male || 0), Female: Number(dataMap.female || dataMap.Female || 0) };
    genderChart = new Chart(ctx, {
      type:'doughnut',
      data:{ labels, datasets:[{ data: labels.map(l => keyed[l] || 0), backgroundColor:['#3b82f6','#ec4899'] }] },
      options:{ responsive:true, maintainAspectRatio:false, plugins:{ title:{ display:true, text:'Gender Distribution' }, legend:{ position:'right' } } }
    });
  }

  /* ---------------- EXPORTS ---------------- */
  function csvEscape(v){ if(v==null)return ''; const s=String(v); return /[",\n]/.test(s)?`"${s.replace(/"/g,'""')}"`:s; }
  function downloadBlob(text, filename, type='text/csv;charset=utf-8;'){
    const blob = new Blob([text], { type });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = filename;
    document.body.appendChild(a); a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
  }

  function buildRequestsCSV(api, view, code) {
    const rows = [];
    if (view === 'daily') {
      const canon = code ? serviceCodeToCanon[code] : '';
      rows.push(canon ? ['Date','Total','Approved','Rejected','Pending'] : ['Date','Service','Total','Approved','Rejected','Pending']);
      const daily = api?.requests?.daily || {};
      Object.keys(daily).sort().forEach(ymd => {
        const bySvc = daily[ymd] || {};
        if (canon) {
          const r = bySvc[canon] || { total:0, approved:0, rejected:0, pending:0 };
          rows.push([ymd, r.total||0, r.approved||0, r.rejected||0, r.pending||0]);
        } else {
          Object.keys(bySvc).forEach(svc => {
            const r = bySvc[svc] || { total:0, approved:0, rejected:0, pending:0 };
            rows.push([ymd, svc, r.total||0, r.approved||0, r.rejected||0, r.pending||0]);
          });
        }
      });
    } else {
      rows.push(['Service Type','Total','Approved','Rejected','Pending']);
      const byType = api?.requests?.byType || {};
      const labels = Object.keys(byType).length ? Object.keys(byType) : CANON_TYPES;
      labels.forEach(t => {
        const r = byType[t] || { total:0, approved:0, rejected:0, pending:0 };
        rows.push([t, r.total||0, r.approved||0, r.rejected||0, r.pending||0]);
      });
    }
    return rows.map(r => r.map(csvEscape).join(',')).join('\n');
  }

  function buildSalesCSV(api, view, code) {
    const rows = [];
    if (view === 'daily') {
      const canon = code ? serviceCodeToCanon[code] : '';
      rows.push(canon ? ['Date','Amount (PHP)'] : ['Date','Service','Amount (PHP)']);
      const daily = api?.sales?.daily || {};
      Object.keys(daily).sort().forEach(ymd => {
        const bySvc = daily[ymd] || {};
        if (canon) rows.push([ymd, Number(bySvc[canon]||0).toFixed(2)]);
        else Object.keys(bySvc).forEach(svc => rows.push([ymd, svc, Number(bySvc[svc]||0).toFixed(2)]));
      });
    } else {
      rows.push(['Service Type','Amount (PHP)']);
      const byType = api?.sales?.byType || {};
      Object.keys(byType).forEach(k => rows.push([k, Number(byType[k]||0).toFixed(2)]));
      rows.push(['Total', Number(api?.sales?.total || 0).toFixed(2)]);
    }
    return rows.map(r => r.map(csvEscape).join(',')).join('\n');
  }

  function buildTxCSV(tx) {
    const rows = [['Date/Time','Receipt','Payer','Cashier','Service','Request ID','Amount (PHP)']];
    (tx?.rows || []).forEach(row => {
      (row.items || []).forEach(it => {
        rows.push([
          row.payment_date || '',
          row.receipt_number || '',
          row.user?.name || row.user?.email || row.user?.id || '',
          row.cashier?.name || row.cashier?.id || '',
          it.service || '',
          it.request_id || '',
          Number(it.amount || 0).toFixed(2)
        ]);
      });
    });
    return rows.map(r => r.map(csvEscape).join(',')).join('\n');
  }

  document.querySelectorAll('.export-btn').forEach((btn) => {
    btn.addEventListener('click', async function () {
      const kind = this.getAttribute('data-report');
      if (kind === 'requests') {
        const view = document.querySelector('#reqViewPills .pill.active')?.dataset.view || 'summary';
        const code = $('reqServiceSelect').value || '';
        const params = { req_view: view, req_service: code, req_start_date: $('reqDateStart').value || '', req_end_date: $('reqDateEnd').value || '' };
        const api = await fetchReport(params);
        downloadBlob(buildRequestsCSV(api, view, code), `requests_${view}.csv`);
      } else if (kind === 'financial') {
        const view = document.querySelector('#salesViewPills .pill.active')?.dataset.view || 'summary';
        const code = $('salesServiceSelect').value || '';
        const params = { sales_view: view, sales_service: code, sales_start_date: $('salesDateStart').value || '', sales_end_date: $('salesDateEnd').value || '' };
        const api = await fetchReport(params);
        downloadBlob(buildSalesCSV(api, view, code), `sales_${view}.csv`);
      } else if (kind === 'demographics') {
        if (!lastApi) return;
        const rows = [];
        rows.push(['Resident Demographics']);
        rows.push(['Age Distribution']);
        rows.push(['0-17','18-35','36-55','56+']);
        const age = lastApi?.demographics?.age || {};
        rows.push([age['0-17']||0, age['18-35']||0, age['36-55']||0, age['56+']||0]);
        rows.push([]);
        rows.push(['Gender Distribution']);
        rows.push(['Male','Female']);
        const g = lastApi?.demographics?.gender || {};
        rows.push([g.male||0, g.female||0]);
        downloadBlob(rows.map(r=>r.map(csvEscape).join(',')).join('\n'), 'demographics.csv');
      } else if (kind === 'transactions') {
        const params = { 
          tx_service: $('txServiceSelect').value || '', 
          tx_cashier: $('txCashier').value || '', 
          tx_search: $('txSearch').value || '',
          tx_start_date: $('txDateStart').value || '', 
          tx_end_date: $('txDateEnd').value || '', 
          tx_page: 1, tx_page_size: 10000 // export all (sensible cap)
        };
        const api = await fetchReport(params);
        downloadBlob(buildTxCSV(api?.transactions || {}), 'transactions.csv');
      }
    });
  });

  /* ---------------- UI hooks ---------------- */
  document.querySelectorAll('#globalTimeFilters .time-filter').forEach((f) => {
    f.addEventListener('click', async () => {
      document.querySelectorAll('#globalTimeFilters .time-filter').forEach(x => x.classList.remove('active'));
      f.classList.add('active');
      const period = f.dataset.period;
      if (period === 'custom') {
        $('dateRangePicker').style.display = 'flex';
      } else {
        $('dateRangePicker').style.display = 'none';
        await loadGlobal(period);
      }
    });
  });
  if (hasEl('applyDateRange')) $('applyDateRange').addEventListener('click', () => {
    const s = $('dateRangeStart').value, e = $('dateRangeEnd').value;
    if (s && e && new Date(s) <= new Date(e)) loadGlobal('custom', s, e);
    else alert('Please select a valid date range');
  });

  // requests local
  document.querySelectorAll('#reqViewPills .pill').forEach(p => p.addEventListener('click', async () => {
    document.querySelectorAll('#reqViewPills .pill').forEach(x => x.classList.remove('active'));
    p.classList.add('active'); await loadRequests();
  }));
  $('reqApply').addEventListener('click', loadRequests);
  $('reqServiceSelect').addEventListener('change', loadRequests);

  // sales local
  document.querySelectorAll('#salesViewPills .pill').forEach(p => p.addEventListener('click', async () => {
    document.querySelectorAll('#salesViewPills .pill').forEach(x => x.classList.remove('active'));
    p.classList.add('active'); await loadSales();
  }));
  $('salesApply').addEventListener('click', loadSales);
  $('salesServiceSelect').addEventListener('change', loadSales);

  // tx local + pager
  $('txApply').addEventListener('click', () => loadTx(1));
  $('txPrev').addEventListener('click', (e) => loadTx(Number(e.currentTarget.dataset.page || 1)));
  $('txNext').addEventListener('click', (e) => loadTx(Number(e.currentTarget.dataset.page || 1)));

  // Kickoff: global summary, then sections
  (async () => {
    await loadGlobal('this_month');
    // demographics charts from lastApi
    updateAgeChart(['0-17','18-35','36-55','56+'], lastApi?.demographics?.age || {});
    updateGenderChart(['Male','Female'], lastApi?.demographics?.gender || {});
    // sections initial load (no local filters yet)
    await loadRequests();
    await loadSales();
    await loadTx(1);
  })();
});
