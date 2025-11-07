// request.js
document.addEventListener('DOMContentLoaded', () => {
  // ---- Helpers ----
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const reviewModal  = $('#reviewModal');
  const successModal = $('#successModal');

  /* ===========================
     0) Inject minimal CSS (JS-only)
     =========================== */
  (function injectStyles(){
    const css = `
      /* Clickable thumbs inside review modal */
      .review-modal .attachments-section .thumb img,
      .review-modal .attachments-section img {
        cursor: zoom-in;
        max-height: 120px;
        object-fit: cover;
        border-radius: 8px;
      }
      /* Lightbox base */
      ._lb-overlay {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,.82);
        z-index: 10000;
        padding: 2rem;
      }
      ._lb-overlay.active { display: flex; }

      /* Image lightbox */
      ._lb-img {
        max-width: 95vw;
        max-height: 90vh;
        border-radius: 10px;
        box-shadow: 0 20px 60px rgba(0,0,0,.6);
      }

      /* PDF lightbox iframe */
      ._lb-pdf-frame {
        width: 95vw;
        height: 90vh;
        border: none;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 20px 60px rgba(0,0,0,.6);
      }

      /* Close button */
      ._lb-close {
        position: absolute;
        top: 14px;
        right: 16px;
        font-size: 28px;
        line-height: 1;
        background: #ffffff;
        color: #111;
        border: 0;
        border-radius: 999px;
        width: 40px;
        height: 40px;
        cursor: pointer;
        display: grid;
        place-items: center;
        box-shadow: 0 6px 20px rgba(0,0,0,.25);
      }

      /* Make legacy single <img> previews clickable too */
      .review-modal .attachments-section img[alt="Uploaded Document"] {
        cursor: zoom-in;
      }
    `;
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
  })();

  /* =========================================
     1) Create lightboxes (image + PDF) in JS
     ========================================= */
  const imgOverlay  = document.createElement('div');
  imgOverlay.className = '_lb-overlay';
  imgOverlay.setAttribute('aria-hidden', 'true');
  imgOverlay.innerHTML = `
    <button type="button" class="_lb-close" aria-label="Close preview">&times;</button>
    <img class="_lb-img" alt="Attachment preview"/>
  `;
  document.body.appendChild(imgOverlay);

  const pdfOverlay  = document.createElement('div');
  pdfOverlay.className = '_lb-overlay';
  pdfOverlay.setAttribute('aria-hidden', 'true');
  pdfOverlay.innerHTML = `
    <button type="button" class="_lb-close" aria-label="Close PDF">&times;</button>
    <iframe class="_lb-pdf-frame" title="PDF preview"></iframe>
  `;
  document.body.appendChild(pdfOverlay);

  const imgNode     = $('._lb-img', imgOverlay);
  const imgClose    = $('._lb-close', imgOverlay);
  const pdfFrame    = $('._lb-pdf-frame', pdfOverlay);
  const pdfClose    = $('._lb-close', pdfOverlay);

  function openImageLightbox(src, alt) {
    if (!src) return;
    imgNode.src = src;
    imgNode.alt = alt || 'Attachment preview';
    imgOverlay.classList.add('active');
    imgOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeImageLightbox() {
    imgOverlay.classList.remove('active');
    imgOverlay.setAttribute('aria-hidden', 'true');
    imgNode.src = '';
    document.body.style.overflow = '';
  }
  function openPdfLightbox(url) {
    if (!url) return;
    // Let the server stream the PDF; we just embed it.
    pdfFrame.src = url;
    pdfOverlay.classList.add('active');
    pdfOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closePdfLightbox() {
    pdfOverlay.classList.remove('active');
    pdfOverlay.setAttribute('aria-hidden', 'true');
    // Reset to stop PDF from keeping focus/resources
    pdfFrame.src = 'about:blank';
    document.body.style.overflow = '';
  }

  // Close interactions
  imgOverlay.addEventListener('click', (e) => {
    if (e.target === imgOverlay || e.target === imgClose) closeImageLightbox();
  });
  pdfOverlay.addEventListener('click', (e) => {
    if (e.target === pdfOverlay || e.target === pdfClose) closePdfLightbox();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && imgOverlay.classList.contains('active')) closeImageLightbox();
    if (e.key === 'Escape' && pdfOverlay.classList.contains('active')) closePdfLightbox();
  });

  /* ======================
     2) Existing modal glue
     ====================== */
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

    el.addEventListener('click', (e) => {
      if (e.target === e.currentTarget) el.classList.remove('active');
    });

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

  /* ============================
     3) Requirements HTML builder
     ============================ */
  function renderRequirements(requirements, legacyUrl) {
    // Nothing: show N/A
    if (!Array.isArray(requirements) || requirements.length === 0) {
      if (legacyUrl) {
        const isPDF = /\.pdf(?:[#?].*)?$/i.test(legacyUrl);
        return `
          <div class="attachments-section">
            <h4>Uploaded Document (legacy)</h4>
            ${isPDF ? `<p><a href="${legacyUrl}" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Open PDF</a></p>`
                     : `<img src="${legacyUrl}" alt="Uploaded Document">`}
          </div>
        `;
      }
      return `<div class="attachments-section"><h4>Requirements</h4><p>N/A</p></div>`;
    }

    const cards = requirements.map((r) => {
      const label  = r.label || r.key;
      const method = (r.method || '').toLowerCase();
      const url    = r.url || null;
      const isPDF  = url ? /\.pdf(?:[#?].*)?$/i.test(url) : false;

      // Method visuals
      const methodBadge =
        method === 'upload' ? `<span class="badge badge-upload"><i class="fas fa-upload"></i> Upload</span>` :
        method === 'hall'   ? `<span class="badge badge-hall"><i class="fas fa-building"></i> Bring to Hall</span>` :
                              `<span class="badge"><i class="fas fa-question-circle"></i> Unknown</span>`;

      // Preview/link
      let preview = `<p class="muted">No file provided</p>`;
      if (method === 'upload' && url) {
        preview = isPDF
          ? `<p><a class="pdf-link" href="${url}" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Open PDF</a></p>`
          : `<div class="thumb"><img src="${url}" alt="${label}"></div>`;
      }

      return `
        <div class="req-card">
          <div class="req-card__header">
            <div class="req-card__title"><i class="fas fa-paperclip"></i> ${label}</div>
            ${methodBadge}
          </div>
          <div class="req-card__body">
            ${preview}
          </div>
        </div>
      `;
    }).join('');

    return `
      <div class="attachments-section">
        <h4>Requirements</h4>
        <div class="req-grid">
          ${cards}
        </div>
      </div>
    `;
  }

  /* ======================================
     4) Image/PDF lightbox event delegation
     ====================================== */
  // Works for any review modal content (even after re-render)
  reviewModal?.addEventListener('click', (e) => {
    // 4a) Image click → open image lightbox
    const img = e.target.closest('.attachments-section img, .req-grid img, .thumb img');
    if (img) {
      e.preventDefault();
      openImageLightbox(img.getAttribute('src'), img.getAttribute('alt') || 'Attachment');
      return;
    }

    // 4b) PDF link click → open PDF lightbox in overlay (instead of new tab)
    const a = e.target.closest('.attachments-section a, .req-grid a, .pdf-link');
    if (a) {
      const href = a.getAttribute('href') || '';
      // Detect PDFs either by extension or by link text/icon
      const looksPdf = /\.pdf(?:[#?].*)?$/i.test(href) ||
                       a.textContent.toLowerCase().includes('pdf') ||
                       (a.querySelector('i') && a.querySelector('i').className.includes('fa-file-pdf'));
      if (looksPdf) {
        e.preventDefault();
        openPdfLightbox(href);
      }
    }
  });

  /* =====================================
     5) Public: open the Review modal (API)
     ===================================== */
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
      if (!request) { alert('Request not found'); return; }

      const requirementsSection = renderRequirements(request.requirements, request.document_url);

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

            ${requirementsSection}
          </div>
          <div class="modal-actions">
            <button class="modal-btn close-btn" onclick="closeReviewModal()"><i class="fas fa-times"></i> Close</button>
            <button class="modal-btn reject-btn" onclick="openRejectConfirm('${request.reference_number}')"><i class="fas fa-times-circle"></i> Reject</button>
            <button class="modal-btn approve-btn" onclick="approveRequest('${request.reference_number}')"><i class="fas fa-check-circle"></i> Approve</button>
          </div>
        </div>
      `;

      if (!reviewModal) { alert('Review modal container not found in page.'); return; }

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

  window.refreshPage = function () { location.reload(); };
});
