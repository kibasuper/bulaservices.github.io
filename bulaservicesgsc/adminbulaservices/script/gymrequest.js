// =================== CONFIG ===================
const API_URL = './php/gymadback.php';
const ADMIN_API_KEY = window.ADMIN_API_KEY || 'change-this-admin-key-123';

// =================== API ===================
async function apiPost(payload) {
  const res = await fetch(API_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-ADMIN-KEY': ADMIN_API_KEY
    },
    body: JSON.stringify(payload)
  });

  let data, text;
  try { data = await res.clone().json(); } catch { text = await res.text(); }

  if (!res.ok || (data && data.status !== 'success')) {
    if (text && !data) console.error('Server body:', text);
    const msg = (data && (data.message + (data.detail ? ' — ' + data.detail : ''))) || text || `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

// =================== BOOTSTRAP ===================
document.addEventListener('DOMContentLoaded', () => {
  const reviewModal  = document.getElementById('reviewModal');
  const successModal = document.getElementById('successModal');

  // Backdrop close
  if (reviewModal) {
    reviewModal.addEventListener('click', (e) => {
      if (e.target === e.currentTarget) closeReviewModal();
    });
  }
  if (successModal) {
    successModal.addEventListener('click', (e) => {
      if (e.target === e.currentTarget) closeSuccessModal();
    });
  }

  // Enhance success modal: ESC & focus trap
  (function enhanceSuccessModal(){
    if (!successModal) return;
    const card = successModal.querySelector('.modal-card') || successModal.firstElementChild;
    document.addEventListener('keydown', (e) => {
      if (!successModal.classList.contains('active')) return;
      if (e.key === 'Escape') successModal.classList.remove('active');
      if (e.key === 'Tab') {
        const f = successModal.querySelectorAll('button,[href],[tabindex]:not([tabindex="-1"])');
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
        else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
      }
    });
    const _show = window.showSuccessModal;
    window.showSuccessModal = function(title, message){
      const t = document.getElementById('successTitle');
      const m = document.getElementById('successMessage');
      if (t) t.textContent = title || 'Success';
      if (m) m.textContent = message || '';
      successModal.classList.add('active');
      setTimeout(()=>card?.focus?.(), 0);
      if (_show && _show !== window.showSuccessModal) try { _show(title, message); } catch {}
    };
  })();

  refreshData();
});

// =================== LIST / TABLE ===================
async function refreshData() {
  const refreshBtn = document.querySelector('.action-button.refresh');
  let originalHtml;
  if (refreshBtn) {
    originalHtml = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;
  }

  try {
    const res = await apiPost({ action: 'list_reservations', per_page: 200 });
    const tbody = document.getElementById('requestsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    const rows = Array.isArray(res.data) ? res.data : [];
    if (rows.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="7" style="text-align:center; padding:12px;">No gym requests found</td>`;
      tbody.appendChild(tr);
    } else {
      rows.forEach(row => {
        const tr = document.createElement('tr');
        const ref = String(row.reference_number || '');
        const id = Number(row.id);

        tr.dataset.id = String(id);
        tr.dataset.ref = ref;

        const dateStr = safeDate(row.reservation_date);
        const slots = Array.isArray(row.time_slots) ? row.time_slots : [];
        const slotText = slots.map(s => {
          if (s && typeof s === 'object') {
            if (typeof s.time === 'string' && s.time.trim() !== '') return s.time.trim();
            if (typeof s.hour === 'number') return hourToRange(s.hour);
          }
          if (typeof s === 'string' && s.trim() !== '') return s.trim();
          return '';
        }).filter(Boolean).join(' | ');

        const statusLc = (row.status || '').toLowerCase();

        tr.innerHTML = `
          <td class="transaction-code">${escapeHtml(ref)}</td>
          <td>${escapeHtml(row.resident_name || '')}</td>
          <td>${escapeHtml(row.activity || '')}</td>
          <td>${dateStr}</td>
          <td>${escapeHtml(slotText)}</td>
          <td><span class="status-badge status-${statusLc}">${cap(statusLc)}</span></td>
          <td>
            <button class="action-btn" data-action="review" data-ref="${escapeAttr(ref)}">
              <i class="fas fa-eye"></i> Review
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    // Row action (Review)
    tbody.onclick = (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const action = btn.getAttribute('data-action');
      const ref = btn.getAttribute('data-ref') || '';
      if (action === 'review') reviewRequest(ref);
    };
  } catch (e) {
    alert(e.message || 'Failed to load requests');
    console.error(e);
  } finally {
    if (refreshBtn) {
      refreshBtn.innerHTML = originalHtml;
      refreshBtn.disabled = false;
    }
  }
}

// Remove a row from the list by reference number
function removeRowByRef(ref) {
  const rows = document.querySelectorAll('#requestsTableBody tr');
  for (const row of rows) {
    const code = row.querySelector('.transaction-code')?.textContent?.trim();
    if (code === ref) {
      row.remove();
      break;
    }
  }
  const tbody = document.getElementById('requestsTableBody');
  if (tbody && tbody.children.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="7" style="text-align:center; padding:12px;">No gym requests found</td>`;
    tbody.appendChild(tr);
  }
}

// =================== REVIEW MODAL ===================
window.reviewRequest = async function(ref) {
  try {
    const res = await apiPost({ action: 'get_by_reference', reference: ref });
    const r = res.data;

    const timeSlotBadges = (Array.isArray(r.time_slots) ? r.time_slots : [])
      .map(s => `<span class="slot-badge">${escapeHtml(s && s.time ? s.time : hourToRange(s && typeof s.hour === 'number' ? s.hour : NaN))}</span>`)
      .join('');

    const status = (r.status || '').toLowerCase();
    const isTerminal = ['completed', 'cancelled', 'canceled', 'rejected'].includes(status);

    // Transaction Details block (names + dates; date only)
    const trans = r.transaction || {};
    const approvedBy = trans.approved_by_name || '—';
    const approvedOn = trans.approved_at ? niceDateOnly(trans.approved_at) : '—';
    const processedBy = trans.processed_by_name || '—';
    const processedOn = trans.processed_at ? niceDateOnly(trans.processed_at) : '—';
    const releasedBy = trans.released_by_name || '—';
    const releasedOn = trans.released_at ? niceDateOnly(trans.released_at) : '—';

    let actionsHtml = `
      <button class="modal-btn close-btn" onclick="closeReviewModal()">
        <i class="fas fa-times"></i> Close
      </button>`;

    if (!isTerminal) {
      if (status === 'approved') {
        actionsHtml += `
          <button class="modal-btn approve-btn" onclick="completeRequest(${Number(r.id)}, '${escapeJs(r.reference_number)}')">
            <i class="fas fa-check-double"></i> Mark Complete
          </button>`;
      } else {
        actionsHtml += `
          <button class="modal-btn reject-btn" onclick="openRejectConfirm(${Number(r.id)}, '${escapeJs(r.reference_number)}')">
            <i class="fas fa-times-circle"></i> Reject
          </button>
          <button class="modal-btn approve-btn" onclick="approveRequest(${Number(r.id)}, '${escapeJs(r.reference_number)}')">
            <i class="fas fa-check-circle"></i> Approve
          </button>`;
      }
    }

    const modalContent = `
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="gymReqTitle" tabindex="-1">
        <div class="modal-header">
          <div class="modal-icon-wrap" aria-hidden="true"><i class="fas fa-dumbbell"></i></div>
          <h3 class="modal-title" id="gymReqTitle">GYM REQUEST #${escapeHtml(r.reference_number || '')}</h3>
          <p class="modal-subtitle">Gym Reservation Review</p>
        </div>

        <div class="modal-body">
          <div class="detail-row"><span class="detail-label">Full Name:</span><span class="detail-value">${escapeHtml(r.resident_name || 'N/A')}</span></div>
          <div class="detail-row"><span class="detail-label">Contact No:</span><span class="detail-value">${escapeHtml(r.contact_number || 'N/A')}</span></div>
          <div class="detail-row"><span class="detail-label">Activity:</span><span class="detail-value">${escapeHtml(r.activity || 'N/A')}</span></div>
          <div class="detail-row"><span class="detail-label">Reservation Date:</span><span class="detail-value">${formatDate(r.reservation_date)}</span></div>
          <div class="detail-row"><span class="detail-label">Time Slots:</span><span class="detail-value"><div class="slots-display">${timeSlotBadges}</div></span></div>
          <div class="detail-row"><span class="detail-label">Total:</span><span class="detail-value">₱${Number(r.total_amount || 0).toFixed(2)}</span></div>
          <div class="detail-row"><span class="detail-label">Status:</span><span class="detail-value"><span class="status-badge status-${status}">${cap(status)}</span></span></div>
          <div class="detail-row"><span class="detail-label">Reference:</span><span class="detail-value">${escapeHtml(r.reference_number || '')}</span></div>

          <div class="divider"></div>

          <div class="section-title">Transaction Details</div>
          <div class="tx-grid">
            <div class="tx-item">
              <div class="tx-label">Approved By</div>
              <div class="tx-value">${escapeHtml(approvedBy)}</div>
              <div class="tx-date">${escapeHtml(approvedOn)}</div>
            </div>
            <div class="tx-item">
              <div class="tx-label">Processed By</div>
              <div class="tx-value">${escapeHtml(processedBy)}</div>
              <div class="tx-date">${escapeHtml(processedOn)}</div>
            </div>
            <div class="tx-item">
              <div class="tx-label">Released By</div>
              <div class="tx-value">${escapeHtml(releasedBy)}</div>
              <div class="tx-date">${escapeHtml(releasedOn)}</div>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          ${actionsHtml}
        </div>
      </div>
    `;
    const modal = document.getElementById('reviewModal');
    modal.innerHTML = modalContent;
    modal.classList.add('active');
    setTimeout(()=>modal.querySelector('.modal-card')?.focus?.(), 0);
  } catch (e) {
    console.error(e);
    alert('Error loading request');
  }
};

// =================== REJECT FLOW ===================
let _pendingReject = null;

function ensureConfirmRejectModal() {
  let el = document.getElementById('confirmRejectModal');
  if (!el) {
    el = document.createElement('div');
    el.id = 'confirmRejectModal';
    el.className = 'modal-overlay';
    document.body.appendChild(el);
  }
  el.innerHTML = `
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmRejectTitle" aria-describedby="confirmRejectMsg" tabindex="-1">
      <div class="modal-header">
        <div class="modal-icon-wrap warn" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="modal-title" id="confirmRejectTitle">Confirm Rejection</h3>
        <p class="modal-subtitle" id="confirmRejectSub">Are you sure you want to reject this reservation?</p>
      </div>
      <div class="modal-body">
        <p id="confirmRejectMsg">This action cannot be undone. The resident will be notified and the slots will be freed.</p>
      </div>
      <div class="modal-actions">
        <button type="button" class="modal-btn close-btn" id="rejectCancel"><i class="fas fa-times"></i> Cancel</button>
        <button type="button" class="modal-btn reject-btn" id="rejectOk"><i class="fas fa-ban"></i> Reject</button>
      </div>
    </div>
  `;
  el.addEventListener('click', (e) => { if (e.target === e.currentTarget) closeRejectConfirm(); });
  document.addEventListener('keydown', (e) => {
    if (!el.classList.contains('active')) return;
    if (e.key === 'Escape') closeRejectConfirm();
    if (e.key === 'Tab') {
      const f = el.querySelectorAll('button,[href],[tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    }
  });
  return el;
}

window.openRejectConfirm = function(id, ref) {
  _pendingReject = { id: Number(id), ref: String(ref || '') };
  const el = ensureConfirmRejectModal();
  const sub = el.querySelector('#confirmRejectSub');
  if (sub) sub.textContent = `Reject reservation #${_pendingReject.ref}?`;

  const okBtnOld = el.querySelector('#rejectOk');
  const okBtnNew = okBtnOld.cloneNode(true);
  okBtnOld.parentNode.replaceChild(okBtnNew, okBtnOld);
  okBtnNew.onclick = () => confirmReject();

  const cancelBtn = el.querySelector('#rejectCancel');
  cancelBtn.onclick = () => closeRejectConfirm();

  el.classList.add('active');
  setTimeout(()=>el.querySelector('.modal-card')?.focus?.(), 0);
};

window.closeRejectConfirm = function() {
  const el = document.getElementById('confirmRejectModal');
  if (el) el.classList.remove('active');
};

window.confirmReject = async function() {
  if (!_pendingReject) return;
  const { id, ref } = _pendingReject;
  try {
    await apiPost({ action: 'update_status', id, status: 'rejected' });
    removeRowByRef(ref);
    closeRejectConfirm();
    closeReviewModal();
    showSuccessModal('Request Rejected', 'Reservation has been cancelled and the time slots are now available.');

    setTimeout(() => {
      if (typeof HISTORY_URL !== 'undefined' && HISTORY_URL) {
        window.location.href = HISTORY_URL;
      }
    }, 800);
  } catch (e) {
    closeRejectConfirm();
    alert(e.message || 'Failed to reject reservation');
  } finally {
    _pendingReject = null;
  }
};

// =================== APPROVE / COMPLETE ===================
window.approveRequest = async function(id, ref) {
  try {
    await apiPost({ action: 'update_status', id, status: 'approved' });
    removeRowByRef(ref);
    showSuccessModal('Request Approved', 'Reservation is now approved.');
  } catch (e) {
    alert(e.message);
  }
  closeReviewModal();
};

window.completeRequest = async function(id, ref) {
  try {
    await apiPost({ action: 'update_status', id, status: 'completed' });
    removeRowByRef(ref);
    showSuccessModal('Request Completed', 'Reservation marked as completed.');
  } catch (e) {
    alert(e.message);
  }
  closeReviewModal();
};

// =================== UI HELPERS ===================
window.closeReviewModal = function() {
  const modal = document.getElementById('reviewModal');
  modal.classList.remove('active');
  modal.innerHTML = '';
};

window.showSuccessModal = function(title, message) {
  document.getElementById('successTitle').textContent = title || 'Success';
  document.getElementById('successMessage').textContent = message || '';
  document.getElementById('successModal').classList.add('active');
};

function closeSuccessModal() {
  document.getElementById('successModal').classList.remove('active');
}

// =================== SMALL UTILITIES ===================
function cap(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
}

function hourToRange(h) {
  if (typeof h !== 'number' || Number.isNaN(h)) return '—';
  const disp = (x) => {
    const p = x >= 12 ? 'PM' : 'AM';
    const dh = x > 12 ? x - 12 : (x === 0 ? 12 : x);
    return `${dh}:00 ${p}`;
  };
  return `${disp(h)} - ${disp(h + 1)}`;
}

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  })[m]);
}

function escapeAttr(str) {
  return escapeHtml(str).replace(/"/g, '&quot;');
}

function escapeJs(str) {
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function niceDateOnly(dateTimeStr){
  // returns YYYY-MM-DD in locale format (no time)
  const d = new Date(dateTimeStr);
  return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'2-digit' });
}

function safeDate(dateStr) {
  const d = new Date(`${dateStr}T00:00:00`);
  return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Optional: navigation
function newTransaction() {
  window.location.href = "gyms.php";
}
