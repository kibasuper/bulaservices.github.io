// request.js
document.addEventListener('DOMContentLoaded', () => {
  // ---- Helpers ----
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // ---- Cache modals ONCE (fixes "already declared") ----
  const reviewModal  = $('#reviewModal');
  const successModal = $('#successModal');

  // Backdrop close
  if (reviewModal) {
    reviewModal.addEventListener('click', (e) => {
      if (e.target === e.currentTarget) closeReviewModal();
    });
  }
  if (successModal) {
    successModal.addEventListener('click', (e) => {
      if (e.target === e.currentTarget) successModal.classList.remove('active');
    });
  }

  // ---- Confirm modal builder (uses your CSS) ----
  function ensureConfirmModal() {
    let el = $('#confirmModal');
    if (el) return el;

    el = document.createElement('div');
    el.id = 'confirmModal';
    el.className = 'modal-overlay';
    el.innerHTML = `
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage" tabindex="-1">
        <div class="modal-header">
          <div class="modal-icon-wrap warn" aria-hidden="true">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <h3 class="modal-title" id="confirmTitle">Confirm Rejection</h3>
          <p class="modal-subtitle" id="confirmSubtitle">Are you sure you want to reject this request?</p>
        </div>
        <div class="modal-body">
          <p id="confirmMessage">This action cannot be undone. The resident will be notified.</p>
        </div>
        <div class="modal-actions">
          <button type="button" class="modal-btn close-btn" id="confirmCancel"><i class="fas fa-times"></i> Cancel</button>
          <button type="button" class="modal-btn reject-btn" id="confirmOk"><i class="fas fa-ban"></i> Reject</button>
        </div>
      </div>
    `;
    document.body.appendChild(el);

    // Backdrop close
    el.addEventListener('click', (e) => {
      if (e.target === e.currentTarget) el.classList.remove('active');
    });

    // ESC + focus trap
    document.addEventListener('keydown', (e) => {
      if (!el.classList.contains('active')) return;
      if (e.key === 'Escape') el.classList.remove('active');
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

  function openConfirmModal({ title, subtitle, message, onConfirm }) {
    const el = ensureConfirmModal();
    const card = el.querySelector('.modal-card');

    const t = el.querySelector('#confirmTitle');     if (t) t.textContent = title || 'Confirm Rejection';
    const s = el.querySelector('#confirmSubtitle');  if (s) s.textContent = subtitle || 'Are you sure you want to reject this request?';
    const m = el.querySelector('#confirmMessage');   if (m) m.textContent = message || 'This action cannot be undone.';

    const cancelBtn = el.querySelector('#confirmCancel');
    const okBtn     = el.querySelector('#confirmOk');

    // Reset listeners by cloning OK button
    const okFresh = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(okFresh, okBtn);

    cancelBtn.onclick = () => el.classList.remove('active');
    okFresh.onclick = async () => {
      el.classList.remove('active');
      try { await onConfirm?.(); } catch (e) { console.error(e); }
    };

    el.classList.add('active');
    setTimeout(() => card?.focus?.(), 0);
  }

  // ---- Public API (attach to window so onclick works) ----
  window.reviewRequest = async function (ref) {
    try {
      const response = await fetch(`./php/get_request_details.php?ref=${encodeURIComponent(ref)}`, {
        credentials: 'same-origin'
      });
      const data = await response.json();

      if (!data?.success) {
        alert(data?.message || 'Failed to fetch request details');
        return;
      }

      const request = data.request;
      if (!request) {
        alert('Request not found');
        return;
      }

      const isPDF = !!request.document_url && /\.pdf(?:[#?].*)?$/i.test(request.document_url);
      const attachmentsSection = request.document_url
        ? `
          <div class="attachments-section">
            <h4>Uploaded Document</h4>
            ${isPDF
              ? `<p><a href="${request.document_url}" target="_blank" rel="noopener">Open PDF in new tab</a></p>`
              : `<img src="${request.document_url}" alt="Uploaded Document">`
            }
          </div>`
        : `<div class="attachments-section"><h4>Uploaded Document</h4>N/A</div>`;

      const modalContent = `
        <div class="modal-card review-modal" role="dialog" aria-modal="true" tabindex="-1">
          <div class="modal-header">
            <div class="modal-icon-wrap" aria-hidden="true"><i class="fas fa-file-alt"></i></div>
            <h3 class="modal-title">REQUEST #${request.reference_number}</h3>
            <p class="modal-subtitle">Certificate Request Review</p>
          </div>
          <div class="modal-body">
            <div class="detail-row"><span class="detail-label">Full Name:</span><span class="detail-value">${request.first_name} ${request.last_name}</span></div>
            <div class="detail-row"><span class="detail-label">Email:</span><span class="detail-value">${request.email || 'N/A'}</span></div>
            <div class="detail-row"><span class="detail-label">Contact No:</span><span class="detail-value">${request.contact_number || 'N/A'}</span></div>
            <div class="detail-row"><span class="detail-label">Address:</span><span class="detail-value">${request.address || 'N/A'}</span></div>
            <div class="detail-row"><span class="detail-label">Certificate Type:</span><span class="detail-value">${String(request.service_type || '').replace(/_/g, ' ')}</span></div>
            <div class="detail-row"><span class="detail-label">Purpose:</span><span class="detail-value">${request.purpose || ''}</span></div>
            <div class="detail-row"><span class="detail-label">Date Requested:</span><span class="detail-value">${request.request_date ? new Date(request.request_date).toLocaleString() : 'N/A'}</span></div>
            <div class="detail-row"><span class="detail-label">Status:</span>
              <span class="detail-value">
                <span class="status-badge status-${String(request.status || '').toLowerCase()}">${request.status || ''}</span>
              </span>
            </div>
            ${attachmentsSection}
          </div>
          <div class="modal-actions">
            <button class="modal-btn close-btn" onclick="closeReviewModal()"><i class="fas fa-times"></i> Close</button>
            <button class="modal-btn reject-btn" onclick="openRejectConfirm('${request.reference_number}')"><i class="fas fa-times-circle"></i> Reject</button>
            <button class="modal-btn approve-btn" onclick="approveRequest('${request.reference_number}')"><i class="fas fa-check-circle"></i> Approve</button>
          </div>
        </div>
      `;

      if (!reviewModal) {
        console.error('Missing #reviewModal container in DOM');
        alert('Review modal container not found in page.');
        return;
      }

      reviewModal.innerHTML = modalContent;
      reviewModal.classList.add('active');
      setTimeout(() => reviewModal.querySelector('.modal-card')?.focus?.(), 0);
    } catch (err) {
      console.error(err);
      alert('Error fetching request details');
    }
  };

  window.closeReviewModal = function () {
    if (reviewModal) reviewModal.classList.remove('active');
  };

  window.openRejectConfirm = function (ref) {
    openConfirmModal({
      title: 'Confirm Rejection',
      subtitle: `Reject request #${ref}?`,
      message: 'This action cannot be undone. The resident will be notified.',
      onConfirm: async () => { await rejectRequest(ref); }
    });
  };

  window.approveRequest = async function (ref) {
    try {
      const formData = new FormData();
      formData.append('ref', ref);
      formData.append('action', 'approve');

      const response = await fetch('./php/approve_request.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      const data = await response.json();
      if (!data.success) throw new Error(data.message || 'Approve failed');

      updateRequestRow(ref, 'approved');
      showSuccessModal('Request Approved', 'Forwarded to billing for payment processing.');
      setTimeout(() => location.reload(), 2000);
    } catch (err) {
      console.error(err);
      alert(err.message || 'Approve failed');
    } finally {
      closeReviewModal();
    }
  };

  window.rejectRequest = async function (ref) {
    try {
      const formData = new FormData();
      formData.append('ref', ref);
      formData.append('action', 'reject');

      const response = await fetch('./php/approve_request.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      const data = await response.json();
      if (!data.success) throw new Error(data.message || 'Reject failed');

      updateRequestRow(ref, 'rejected');
      showSuccessModal('Request Rejected', 'Resident notified about rejection.');
      setTimeout(() => location.reload(), 2000);
    } catch (err) {
      console.error(err);
      alert(err.message || 'Reject failed');
    } finally {
      closeReviewModal();
    }
  };

  window.updateRequestRow = function (ref, status) {
    const rows = $$('#requestsTableBody tr');
    rows.forEach((row) => {
      const codeCell = row.querySelector('.transaction-code');
      if (!codeCell) return;
      if (codeCell.textContent.trim() === ref) {
        if (status === 'approved' || status === 'rejected') {
          row.remove(); // instant removal
        } else {
          const badge = row.querySelector('.status-badge');
          if (badge) {
            badge.className = `status-badge status-${status}`;
            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
          }
        }
      }
    });
  };

  window.showSuccessModal = function (title, message) {
    if (!successModal) return;
    const t = $('#successTitle'); if (t) t.textContent = title || 'Success';
    const m = $('#successMessage'); if (m) m.textContent = message || 'Done.';
    successModal.classList.add('active');
  };

  // Keep this global so your HTML onclick="refreshPage()" works
  window.refreshPage = function () { location.reload(); };
});
