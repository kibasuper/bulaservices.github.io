// ===================== DOM Elements =====================
const userMenuBtn = document.getElementById('user-menu-btn');
const dropdownMenu = document.getElementById('dropdown-menu');

const cartItems = document.getElementById('cartItems');
const cartTotal = document.getElementById('cartTotal');
const cartChange = document.getElementById('cartChange');
const clearCartBtn = document.getElementById('clearCartBtn');
const processPaymentBtn = document.getElementById('processPaymentBtn');
const cashGivenInput = document.getElementById('cashGiven');

// Optional email input in UI (if present)
const emailInput = document.getElementById('emailInput');

const receiptModal = document.getElementById('receiptModal');
const printReceiptBtn = document.getElementById('printReceiptBtn');
const closeReceiptBtn = document.getElementById('closeReceiptBtn');

const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');
const requestsTbody = document.getElementById('requestsTableBody');

// ===================== Endpoints (same-origin to respect CSP) =====================
const ENDPOINT_LIST = '/php/get_billing_requests.php';
const ENDPOINT_PAY  = '/php/process_payment.php';

// ===================== State =====================
let allItems = [];
let filteredItems = [];
let hiddenIds = new Set();
let cart = [];

// ===================== Utils =====================
const peso = (n) => `₱${Number(n || 0).toFixed(2)}`;
const debounce = (fn, ms = 250) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
function toast(msg, icon = 'success') { if (window.Swal) Swal.fire({ icon, title: msg, timer: 1200, showConfirmButton: false }); }

function isPayableStatus(s) {
  const status = String(s || '').trim().toLowerCase();
  return !['paid', 'completed', 'cancelled', 'canceled', 'void', 'refunded', 'settled'].includes(status);
}

// NEW: display “Processing” instead of “Approved”
function displayStatus(s) {
  const raw = String(s || '').trim().toLowerCase();
  if (raw === 'approved') return 'Processing';
  return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : 'Processing';
}

// ===================== Init =====================
document.addEventListener('DOMContentLoaded', initCashier);

function initCashier() {
  // User menu
  if (userMenuBtn && dropdownMenu) {
    userMenuBtn.addEventListener('click', e => { e.stopPropagation(); dropdownMenu.classList.toggle('active'); });
    document.addEventListener('click', () => dropdownMenu.classList.remove('active'));
  }

  clearCartBtn?.addEventListener('click', clearCart);
  processPaymentBtn?.addEventListener('click', processPayment);
  printReceiptBtn?.addEventListener('click', () => window.print());
  closeReceiptBtn?.addEventListener('click', () => { receiptModal.classList.remove('active'); clearCart(); });
  cashGivenInput?.addEventListener('input', updateChange);

  searchBtn?.addEventListener('click', refreshList);
  searchInput?.addEventListener('input', debounce(refreshList, 150));
  searchInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') refreshList(); });

  // Click row to add to cart
  requestsTbody?.addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    const id = tr.getAttribute('data-id');
    const item = filteredItems.find(x => String(x.id) === String(id));
    if (!item) return;
    addToCart(item);
  });

  // Initial load
  fetchRequests().catch(err => {
    console.error('init fetch error', err);
    requestsTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#c00;">${err.message || 'Failed to load'}</td></tr>`;
  });

  // Light auto-refresh so brand-new approvals pop in
  setInterval(() => {
    if (!receiptModal.classList.contains('active')) {
      fetchRequests().catch(()=>{});
    }
  }, 20000); // 20s
}

// ===================== Server I/O =====================
async function fetchRequests() {
  const q = (searchInput?.value || '').trim();
  const url = q ? `${ENDPOINT_LIST}?q=${encodeURIComponent(q)}&debug=1`
                : `${ENDPOINT_LIST}?debug=1`;

  try {
    requestsTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">Loading…</td></tr>`;

    const res = await fetch(url, { credentials: 'same-origin' });
    const text = await res.text();
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${text.slice(0, 300)}`);

    let json;
    try { json = JSON.parse(text); }
    catch { throw new Error(`Non-JSON response: ${text.slice(0, 300)}`); }

    console.log('[cashier] api len:', Array.isArray(json.requests) ? json.requests.length : 'n/a');
    if (json.debug) console.log('[cashier] debug:', json.debug);

    if (!json.success) throw new Error(json.message || 'Server error');

    allItems = Array.isArray(json.requests) ? json.requests : [];
    refreshList();
  } catch (err) {
    console.error('fetchRequests error', err);
    requestsTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#c00;">${err.message || 'Server error'}</td></tr>`;
    throw err;
  }
}

async function refetchAfterPayment() {
  await fetchRequests();
}

// ===================== Rendering =====================
function refreshList() {
  const q = (searchInput?.value || '').trim().toLowerCase();
  let base = allItems.slice();

  if (q) {
    base = base.filter(r =>
      (r.code && r.code.toLowerCase().includes(q)) ||
      (r.type && r.type.toLowerCase().includes(q)) ||
      (r.details && r.details.toLowerCase().includes(q)) ||
      (r.customer_name && r.customer_name.toLowerCase().includes(q))
    );
  }

  // Only show entries that still need payment
  base = base.filter(r => isPayableStatus(r.status));

  filteredItems = base.filter(r => !hiddenIds.has(String(r.id)));
  drawTable();
}

function drawTable() {
  requestsTbody.innerHTML = '';

  if (!filteredItems.length) {
    const msg = (searchInput?.value || '').trim()
      ? 'No results'
      : 'No pending payment requests';
    requestsTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#666;">${msg}</td></tr>`;
    return;
  }

  const groups = new Map();
  for (const item of filteredItems) {
    const key = (item.customer_name || 'Unknown') + '|' + (item.customer_contact || '');
    if (!groups.has(key)) groups.set(key, { name: item.customer_name || 'Unknown', contact: item.customer_contact || '', items: [] });
    groups.get(key).items.push(item);
  }

  for (const [, group] of groups) {
    const head = document.createElement('tr');
    head.className = 'group-header-row';
    head.innerHTML = `
      <td colspan="7" style="background:#f7f8fa;font-weight:600;">
        ${group.name}${group.contact ? ` • ${group.contact}` : ''}
      </td>
    `;
    requestsTbody.appendChild(head);

    for (const req of group.items) {
      const tr = document.createElement('tr');
      tr.setAttribute('data-id', req.id);
      tr.className = 'clickable-row';
      tr.title = 'Click to add to POS';
      tr.innerHTML = `
        <td>${req.code}</td>
        <td>${req.type}</td>
        <td>${req.details}</td>
        <td>${new Date(req.datetime).toLocaleString()}</td>
        <td>${displayStatus(req.status)}</td>
        <td>${peso(req.amount)}</td>
        <td style="opacity:.6;">Click row to add</td>
      `;
      requestsTbody.appendChild(tr);
    }
  }
}

// ===================== Cart =====================
function addToCart(src) {
  if (cart.some(i => String(i.id) === String(src.id))) {
    return toast('Already added', 'info');
  }
  cart.push({
    id: src.id,
    code: src.code,
    type: src.type,
    details: src.details,
    amount: Number(src.amount || 0),

    // carry customer fields if present (safe defaults)
    customer_name: src.customer_name || '',
    customer_contact: src.customer_contact || '',
    email: (src.email || '').trim()
  });
  hiddenIds.add(String(src.id));
  updateCartUI();
  refreshList();
  toast('Added to cart');
}

function removeFromCart(id) {
  cart = cart.filter(i => String(i.id) !== String(id));
  hiddenIds.delete(String(id));
  updateCartUI();
  refreshList();
}

function clearCart() {
  cart = [];
  hiddenIds.clear();
  updateCartUI();
  refreshList();
}

function updateCartUI() {
  cartItems.innerHTML = '';
  if (!cart.length) {
    cartItems.innerHTML = '<div class="empty-cart-message">No items in cart</div>';
    cartTotal.textContent = peso(0);
    processPaymentBtn.disabled = true;
    updateChange();
    return;
  }

  let total = 0;
  for (const item of cart) {
    const row = document.createElement('div');
    row.className = 'cart-item';
    row.innerHTML = `
      <div class="cart-item-info">
        <div class="cart-item-name">${item.type}</div>
        <div class="cart-item-details">${item.code}</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div class="cart-item-price">${peso(item.amount)}</div>
        <button class="remove-item-btn" data-id="${item.id}" title="Remove"><i class="fas fa-times"></i></button>
      </div>
    `;
    cartItems.appendChild(row);
    total += item.amount;
  }

  document.querySelectorAll('.remove-item-btn').forEach(b => b.addEventListener('click', () => removeFromCart(b.dataset.id)));

  cartTotal.textContent = peso(total);
  updateChange();
}

function updateChange() {
  const cash = parseFloat(cashGivenInput.value) || 0;
  const total = cart.reduce((s, i) => s + i.amount, 0);
  const change = Math.max(0, cash - total);
  cartChange.textContent = peso(change);
  processPaymentBtn.disabled = !(cart.length && cash >= total);
}

// ===================== Payment =====================
async function processPayment() {
  if (!cart.length) return toast('Add items first', 'info');

  const cash = parseFloat(cashGivenInput.value) || 0;
  const total = cart.reduce((s, i) => s + i.amount, 0);
  if (cash < total) return toast(`You need at least ${peso(total)}`, 'error');

  const confirmResult = await Swal.fire({
    title: 'Confirm Payment',
    html: `<p><b>Total:</b> ${peso(total)}</p><p><b>Cash:</b> ${peso(cash)}</p><p><b>Change:</b> ${peso(cash - total)}</p>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Confirm',
    cancelButtonText: 'Cancel'
  });
  if (!confirmResult.isConfirmed) return;

  const receiptNumber = 'BULA-' + new Date().getFullYear() + '-' + Math.floor(1000 + Math.random() * 9000);

  // Prefer email from input if present; else use first cart item email; else empty string.
  const emailFromInput = (emailInput?.value || '').trim();
  const safeEmail = emailFromInput || (cart[0]?.email ?? '').trim() || '';

  // Also pass customer name/contact (from first item) if available
  const safeName = (cart[0]?.customer_name ?? '').trim() || '';
  const safeContact = (cart[0]?.customer_contact ?? '').trim() || '';

  try {
    const response = await fetch(ENDPOINT_PAY, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        receiptNumber,
        cashGiven: cash,
        totalAmount: total,
        items: cart,

        email: safeEmail,
        customerName: safeName,
        customerContact: safeContact
      })
    });
    const text = await response.text();
    if (!response.ok) throw new Error(`HTTP ${response.status}: ${text.slice(0, 300)}`);

    let result;
    try { result = JSON.parse(text); }
    catch { throw new Error(`Non-JSON response: ${text.slice(0, 300)}`); }

    if (!result.success) throw new Error(result.message || 'Payment failed');

    // Receipt
    document.getElementById('receipt-number').textContent = receiptNumber;
    document.getElementById('receipt-date').textContent = new Date().toLocaleString('en-PH');

    const receiptItems = document.getElementById('receipt-items');
    receiptItems.innerHTML = '';
    cart.forEach(i => {
      const row = document.createElement('div');
      row.className = 'receipt-row';
      row.innerHTML = `<span>${i.type} (${i.code})</span><span>${peso(i.amount)}</span>`;
      receiptItems.appendChild(row);
    });
    document.getElementById('receipt-total').textContent  = peso(total);
    document.getElementById('receipt-cash').textContent   = peso(cash);
    document.getElementById('receipt-change').textContent = peso(cash - total);

    receiptModal.classList.add('active');

    // Clear + refresh (and remove paid items immediately)
    const paidIds = new Set(cart.map(i => String(i.id)));
    allItems = allItems.filter(i => !paidIds.has(String(i.id)));
    filteredItems = filteredItems.filter(i => !paidIds.has(String(i.id)));

    cart = [];
    hiddenIds.clear();
    updateCartUI();
    refreshList();

    await refetchAfterPayment();

    toast('Payment Successful');
  } catch (err) {
    console.error(err);
    toast(err.message || 'Payment failed', 'error');
  }
}
