// ==== DOM ====
const transactionModal = document.getElementById('transaction-details-modal');
const closeModalButtons = document.querySelectorAll('.close-modal');
const applyFiltersBtn = document.getElementById('apply-filters');
const exportBtn = document.getElementById('export-btn');
const printReceiptBtn = document.getElementById('print-receipt');
const closeDetailsBtn = document.getElementById('close-details-modal');
const toast = document.getElementById('toast');
const toastMessage = document.getElementById('toast-message');

// Filter selects / inputs
const periodSel = document.getElementById('time-period');
const typeSel   = document.getElementById('service-type');
const statusSel = document.getElementById('transaction-status'); // simplified: all|completed|pending|rejected
const searchInput = document.getElementById('transaction-search');

let transactionsData = {};   // keyed by payment_id
let lastRenderedRows = [];   // for export/print
let pollTimer = null;
let lastDigest = '';
let currentTransaction = null;
let searchTimer = null;

document.addEventListener('DOMContentLoaded', function () {
  fetchTransactions(true);

  closeModalButtons.forEach(btn => btn.addEventListener('click', closeAllModals));
  if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', closeAllModals);
  window.addEventListener('click', e => { if (e.target === transactionModal) closeAllModals(); });

  exportBtn?.addEventListener('click', exportToExcel);
  printReceiptBtn?.addEventListener('click', printReceipt);
  applyFiltersBtn?.addEventListener('click', () => fetchTransactions(true));

  // trigger fetch on time/type/status change
  [periodSel, typeSel, statusSel].forEach(sel => sel?.addEventListener('change', () => fetchTransactions(true)));

  // search: debounce to avoid excessive requests
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      if (searchTimer) clearTimeout(searchTimer);
      searchTimer = setTimeout(() => fetchTransactions(true), 350);
    });
    // also allow Enter to trigger immediate search
    searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); fetchTransactions(true); }});
  }

  startPolling();
});

// ===== Polling =====
function startPolling() { stopPolling(); pollTimer = setInterval(() => fetchTransactions(false), 7000); }
function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

// ===== Helpers =====
function findTbody() {
  return (
    document.querySelector('#transactions-body') ||
    document.querySelector('#transactionsTableBody') ||
    document.querySelector('.transactions-body') ||
    document.querySelector('#transactions-table tbody') ||
    document.querySelector('table tbody')
  );
}

function renderStatus(status) {
  if (!status) return '';
  const normalized = String(status).toLowerCase();
  let cls = 'status-default';
  switch (normalized) {
    case 'completed':
    case 'complete': cls = 'status-completed'; break;
    case 'paid':      cls = 'status-approved';  break;
    case 'pending':   cls = 'status-pending';   break;
    case 'approved':  cls = 'status-approved';  break;
    case 'rejected':  cls = 'status-rejected';  break;
    case 'cancelled': cls = 'status-cancelled'; break;
  }
  const label = (normalized === 'complete') ? 'Completed' : capitalize(status);
  return `<span class="status-badge ${cls}">${label}</span>`;
}

function capitalize(s) { if (!s) return ''; s = String(s); return s.charAt(0).toUpperCase() + s.slice(1); }

function formatDate(dateStr) {
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '';
  return d.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
}

function formatDateOnly(dateStr) {
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '';
  return d.toLocaleDateString('en-PH', { dateStyle: 'medium' });
}

function showToast(message) {
  if (!toast || !toastMessage) return;
  toastMessage.textContent = message || 'Done';
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2000);
}

function closeAllModals() {
  if (transactionModal) {
    transactionModal.classList.remove('show');
    transactionModal.setAttribute('aria-hidden', 'true');
  }
}

function getRangeFromPeriod(label) {
  if (!label || label.toLowerCase() === 'all time') return { from: '', to: '', allTime: true };
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  let from = new Date(today), to = new Date(today);

  switch (label.toLowerCase()) {
    case 'last 7 days':  from = new Date(today.getTime() - 6 * 24 * 3600 * 1000); break;
    case 'last 30 days': from = new Date(today.getTime() - 29 * 24 * 3600 * 1000); break;
    case 'last 90 days': from = new Date(today.getTime() - 89 * 24 * 3600 * 1000); break;
    case 'this month':   from = new Date(today.getFullYear(), today.getMonth(), 1); break;
    case 'last month': {
      const m = today.getMonth() - 1;
      const y = m < 0 ? today.getFullYear() - 1 : today.getFullYear();
      const mm = (m + 12) % 12;
      from = new Date(y, mm, 1);
      const end = new Date(y, mm + 1, 0);
      return { from: from.toISOString().slice(0,10), to: end.toISOString().slice(0,10) };
    }
    default: return { from: '', to: '', allTime: true };
  }
  return { from: from.toISOString().slice(0,10), to: to.toISOString().slice(0,10) };
}

function mapTypeFilter(labelOrValue) {
  // If the select option provides canonical value (we set value attributes), return it directly.
  if (!labelOrValue) return 'all';
  const v = String(labelOrValue).trim().toLowerCase();

  // map some human choices just in case
  if (v === 'all' || v === 'all services') return 'all';
  if (v === 'gym' || v === 'gym services') return 'gym';

  // canonical certificate keys (match your DB / server-side keys)
  const allowed = [
    'barangay_clearance','business_permit','cedula','ivs','indigency',
    'residency','low_income','proof_income','other'
  ];
  if (allowed.includes(v)) return v;

  // fallback to 'all'
  return 'all';
}

function digestRows(rows) {
  try {
    const keyParts = rows.map(r => [
      r.payment_id, r.receipt_number, r.payment_date, r.total_amount,
      r.payment_status, (r.requests||[]).length,
      r.approved_by_name || '', r.released_by_summary || ''
    ].join('|'));
    return keyParts.join('~');
  } catch { return String(Math.random()); }
}

// ===== Fetch & render =====
async function fetchTransactions(resetDigest = false) {
  const periodLabel = periodSel?.value || 'All Time';
  const range = getRangeFromPeriod(periodLabel);
  const typeVal   = mapTypeFilter(typeSel?.value || typeSel?.selectedOptions?.[0]?.value);
  const q = (searchInput?.value || '').trim();
  const statusVal = (statusSel?.value || 'all').toLowerCase(); // only: all|completed|pending|rejected

  const params = new URLSearchParams();
  if (!range.allTime && range.from && range.to) { params.set('from', range.from); params.set('to', range.to); }
  if (typeVal !== 'all')   params.set('type', typeVal);
  if (statusVal && statusVal !== 'all') params.set('status', statusVal);
  if (q) params.set('q', q);

  const url = `./php/get_transactions.php${params.toString() ? ('?' + params.toString()) : ''}`;

  try {
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) {
      console.error('Fetch transactions failed', res.status, await res.text());
      showToast('Failed to load transactions');
      return;
    }
    const data = await res.json();
    if (!Array.isArray(data)) return;

    const d = digestRows(data);
    if (resetDigest || d !== lastDigest) {
      lastDigest = d;
      renderTransactions(data);
    }
  } catch (err) {
    console.error(err);
    showToast('Failed to load transactions');
  }
}

function renderTransactions(data) {
  const tbody = findTbody();
  if (!tbody) { console.warn('Transactions tbody not found.'); return; }

  tbody.innerHTML = '';
  transactionsData = {};
  lastRenderedRows = data || [];

  (data || []).forEach(tr => {
    transactionsData[tr.payment_id] = tr;

    const serviceTypes = (tr.requests || []).map(r => r.service_type).join(', ') || '—';
    const refs = (tr.requests || []).map(r => r.transaction_no).filter(Boolean);
    const uniqueRefs = Array.from(new Set(refs));
    const primaryRef = uniqueRefs[0] || '—';
    const showRef = uniqueRefs.length > 1 ? 'Multiple' : (primaryRef || '—');

    const row = document.createElement('tr');
    row.innerHTML = `
      <td class="transaction-code">${showRef}</td>
      <td>${tr.receipt_number || '—'}</td>
      <td>${formatDate(tr.payment_date || '')}</td>
      <td>${tr.customer_name || ''}</td>
      <td><span class="service-badge">${serviceTypes}</span></td>
      <td>₱${Number(tr.total_amount || 0).toFixed(2)}</td>
      <td>${renderStatus(tr.payment_status || '')}</td>
      <td>
        <button class="action-btn view-btn" data-id="${tr.payment_id}">
          <i class="fas fa-eye"></i> View
        </button>
      </td>
    `;
    tbody.appendChild(row);
  });

  document.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => viewTransaction(btn.dataset.id)));

  if (!data || data.length === 0) showToast('No transactions for the selected filters');
}

// ===== small DOM util for rows =====
function toggleRowByFieldId(id, show) {
  const el = document.getElementById(id);
  if (!el) return;
  const row = el.closest('.detail-row') || el.parentElement;
  if (row) row.style.display = show ? '' : 'none';
}

// ===== Modal (view) =====
function viewTransaction(paymentId) {
  const tr = transactionsData[paymentId];
  if (!tr) return;
  currentTransaction = tr;

  const refs = (tr.requests || []).map(r => r.transaction_no).filter(Boolean);
  const uniqueRefs = Array.from(new Set(refs));
  const refDisplay = uniqueRefs.length
    ? uniqueRefs.map(r => `<code style="background:#f1f5f9;border-radius:6px;padding:2px 6px">${r}</code>`).join(' ')
    : '—';

  // Services table rows (normalize 'complete' → 'Completed')
  const servicesHTML = (tr.requests || []).map(r => {
    const raw = (r.status || '').toLowerCase();
    const display = raw === 'complete' ? 'Completed' : capitalize(r.status || '');
    const badge = renderStatus(raw || '');
    const fixedBadge = raw === 'complete' ? badge.replace(/>Complete</, '>Completed<') : badge;
    return `
      <tr>
        <td>${r.transaction_no || ''}</td>
        <td>${r.service_type || ''}</td>
        <td>${r.description || ''}</td>
        <td>${fixedBadge}</td>
      </tr>
    `;
  }).join('');

  // Fill modal meta (date-only here)
  setText('detail-id', tr.receipt_number || '—');
  setText('detail-date', formatDateOnly(tr.payment_date || ''));
  setText('detail-customer', tr.customer_name || '—');
  setText('detail-contact', tr.customer_contact || '—');
  setText('detail-amount', `₱${Number(tr.total_amount || 0).toFixed(2)}`);
  setText('detail-payment', tr.payment_method || 'Cash');
  setText('detail-processor', tr.processed_by_name || '-');
  setHTML('detail-ref', refDisplay);

  // Build approver strings (DATE ONLY)
  const approver = tr.approved_by_name || '—';
  const approvAt = tr.approved_at ? formatDateOnly(tr.approved_at) : '';
  const approvedText = approver + (approvAt ? ` • ${approvAt}` : '');

  // Released By (DATE ONLY)
  const released = tr.released_by_summary || '—';
  const relAt    = tr.released_at_summary ? formatDateOnly(tr.released_at_summary) : '';
  const releasedText = released + (relAt ? ` • ${relAt}` : '');

  // Inject line items
  const svcTbody = document.getElementById('detail-service');
  if (svcTbody) {
    svcTbody.innerHTML = servicesHTML || '<tr><td colspan="4" style="text-align:center;color:#666;">No line items</td></tr>';
  }

  // ---- Decide layout per your rules ----
  const reqs = Array.isArray(tr.requests) ? tr.requests : [];
  const isGym = (x) => String(x?.service_type || '').toLowerCase().includes('gym');
  const gymOnly  = reqs.length > 0 && reqs.every(isGym);
  const certOnly = reqs.length > 0 && reqs.every(r => !isGym(r));

  // Reset visibility (default show all)
  toggleRowByFieldId('detail-approvedby', true);
  toggleRowByFieldId('detail-status', true);
  toggleRowByFieldId('detail-releasedby', true);

  if (certOnly) {
    setText('detail-status', approvedText);   // label is "Status:"
    setText('detail-releasedby', releasedText);
    toggleRowByFieldId('detail-approvedby', false);
    toggleRowByFieldId('detail-status', true);
    toggleRowByFieldId('detail-releasedby', true);
  } else if (gymOnly) {
    setText('detail-approvedby', approvedText);
    toggleRowByFieldId('detail-approvedby', true);
    toggleRowByFieldId('detail-status', false);
    toggleRowByFieldId('detail-releasedby', false);
  } else {
    setText('detail-approvedby', approvedText);
    setText('detail-status', capitalize(tr.payment_status || '') || '—');
    setText('detail-releasedby', releasedText);
    toggleRowByFieldId('detail-approvedby', true);
    toggleRowByFieldId('detail-status', true);
    toggleRowByFieldId('detail-releasedby', true);
  }

  if (transactionModal) {
    transactionModal.classList.add('show');
    transactionModal.setAttribute('aria-hidden', 'false');
  }
}

function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text; }
function setHTML(id, html) { const el = document.getElementById(id); if (el) el.innerHTML = html; }

// ===== Export / Print =====
function exportToExcel() {
  if (!Array.isArray(lastRenderedRows) || lastRenderedRows.length === 0) { showToast('Nothing to export'); return; }
  const rows = lastRenderedRows.map(tr => {
    const refs = (tr.requests || []).map(r => r.transaction_no).filter(Boolean);
    const uniqueRefs = Array.from(new Set(refs));
    return {
      'Reference(s)': uniqueRefs.join(', ') || '',
      'Receipt #': tr.receipt_number || '',
      'Date & Time': formatDate(tr.payment_date || ''),
      'Customer': tr.customer_name || '',
      'Service Items': (tr.requests || []).map(r => r.service_type).join(', '),
      'Amount': Number(tr.total_amount || 0),
      'Status': tr.payment_status || '',
      'Approved By': tr.approved_by_name || '',
      'Released By': tr.released_by_summary || '',
      'Processed By': tr.processed_by_name || ''
    };
  });

  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Transactions');

  const periodLabel = periodSel?.value || 'AllTime';
  const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
  XLSX.writeFile(wb, `transactions_${periodLabel.replace(/\s+/g,'_')}_${stamp}.xlsx`);
}

function printReceipt() {
  const receiptNo = document.getElementById('detail-id')?.textContent || '';
  if (!receiptNo) { showToast('Open a transaction first'); return; }

  const dateStr    = document.getElementById('detail-date').textContent;
  const customer   = document.getElementById('detail-customer').textContent;
  const contact    = document.getElementById('detail-contact').textContent;
  const amount     = document.getElementById('detail-amount').textContent;
  const payment    = document.getElementById('detail-payment').textContent;
  const processor  = document.getElementById('detail-processor').textContent;

  const approvedBy = document.getElementById('detail-approvedby')?.textContent || '—';
  const releasedBy = document.getElementById('detail-releasedby')?.textContent || '—';

  const rowsHtml = Array.from(document.querySelectorAll('#detail-service tr')).map(tr => `<tr>${tr.innerHTML}</tr>`).join('');
  const refHtml = document.getElementById('detail-ref')?.textContent || '';

  const html = `
    <html>
    <head>
      <title>Receipt ${receiptNo}</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; font-size: 12px; }
        th { background: #f5f5f5; text-align: left; }
        .meta p { margin: 4px 0; font-size: 13px; }
        .right { text-align: right; }
      </style>
    </head>
    <body>
      <h2>Barangay Bula — Official Receipt</h2>
      <div class="meta">
        <p><strong>Reference(s):</strong> ${refHtml}</p>
        <p><strong>Receipt #:</strong> ${receiptNo}</p>
        <p><strong>Date:</strong> ${dateStr}</p>
        <p><strong>Customer:</strong> ${customer}</p>
        <p><strong>Contact:</strong> ${contact}</p>
        <p><strong>Payment Method:</strong> ${payment}</p>
        <p><strong>Processed By:</strong> ${processor}</p>
        <p><strong>Approved By:</strong> ${approvedBy}</p>
        <p><strong>Released By:</strong> ${releasedBy}</p>
      </div>
      <table>
        <thead>
          <tr><th>Reference #</th><th>Service</th><th>Description</th><th>Status</th></tr>
        </thead>
        <tbody>${rowsHtml}</tbody>
        <tfoot>
          <tr><th colspan="3" class="right">TOTAL</th><th>${amount}</th></tr>
        </tfoot>
      </table>
      <script>window.print();</script>
    </body>
    </html>
  `;
  const w = window.open('', '_blank');
  w.document.write(html);
  w.document.close();
}
