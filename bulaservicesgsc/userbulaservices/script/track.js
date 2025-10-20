// script/track.js — robust same-origin resolver for track_api.php

// Build a prioritized candidate list ON THE CURRENT ORIGIN only.
// (No cross-domain requests; avoids CORS/CSP.)
const ORIGIN = window.location.origin;
const PATH_CANDIDATES = [
  // 1) sibling "php" next to track.php
  new URL('php/track_api.php', window.location.href).pathname,

  // 2) common user module paths (most likely one of these)
  '/userbulaservices/php/track_api.php',
  '/bulaservicesgsc/userbulaservices/php/track_api.php',

  // 3) fallback: root-level (some hosts map php/ directly under webroot)
  '/php/track_api.php'
];

let RESOLVED_API_PATH = null;
let requests = [];

// ---- Resolve a working path once, then reuse ----
async function apiList() {
  const body = JSON.stringify({ action: 'list' });
  let lastErr = null;

  for (const path of PATH_CANDIDATES) {
    const url = `${ORIGIN}${path}?action=list`;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body
      });

      // Hard 404? Try the next candidate.
      if (res.status === 404) {
        console.warn('[track] 404 at', url);
        continue;
      }

      const text = await res.text();
      if (!res.ok) throw new Error(text || `HTTP ${res.status}`);

      let json; try { json = JSON.parse(text); } catch { throw new Error(text); }
      if (!json.success) throw new Error(json.message || 'API success=false');

      RESOLVED_API_PATH = path;
      console.info('[track] API resolved:', `${ORIGIN}${RESOLVED_API_PATH}`);
      return json;
    } catch (e) {
      lastErr = e;
      console.warn('[track] candidate failed:', url, e.message || e);
      // try next candidate
    }
  }

  // None worked
  throw lastErr || new Error('Could not reach track_api.php on this origin.');
}

async function loadRequests() {
  const resp = await apiList();
  requests = (resp?.data?.items && Array.isArray(resp.data.items)) ? resp.data.items : [];
  renderRequests();
}

// ---------------- RENDER ----------------
function renderRequests(filterStatus = "All Statuses", searchTerm = "", sortBy = "Newest First") {
  const container = document.getElementById('requests-list');
  if (!container) return;

  let data = [...requests];

  if (filterStatus !== "All Statuses") {
    const target = filterStatus.toLowerCase();
    data = data.filter(r => (r.status || '').toLowerCase() === target);
  }

  if (searchTerm && searchTerm.trim() !== '') {
    const q = searchTerm.toLowerCase();
    data = data.filter(r => JSON.stringify(r).toLowerCase().includes(q));
  }

  switch (sortBy) {
    case "Newest First":
      data.sort((a,b)=> (new Date(b.submitted||0)) - (new Date(a.submitted||0)));
      break;
    case "Oldest First":
      data.sort((a,b)=> (new Date(a.submitted||0)) - (new Date(b.submitted||0)));
      break;
    case "Request Type (A-Z)":
      data.sort((a,b)=> (a.type||'').localeCompare(b.type||''));
      break;
    case "Request Type (Z-A)":
      data.sort((a,b)=> (b.type||'').localeCompare(a.type||''));
      break;
    case "Status":
      const rank = s => ({waiting_approval:1, processing:2, paid:3, completed:4, rejected:5})[(s||'').toLowerCase()] || 9;
      data.sort((a,b)=> rank(a.status) - rank(b.status));
      break;
  }

  const countEl = document.querySelector('.request-count strong');
  if (countEl) countEl.textContent = String(data.length);

  container.innerHTML = '';
  if (!data.length) {
    container.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h3>No requests found</h3>
        <p>If you recently submitted, try refreshing in a moment. You’ll only see <em>your</em> own requests here.</p>
      </div>`;
    return;
  }

  const progressWidth = {
  waiting_approval: "20%",
  approved: "40%",
  incoming: "65%",
  ongoing: "85%",
  processing: "60%",      // legacy service-requests path
  paid: "75%",            // legacy service-requests path
  completed: "100%",
  rejected: "100%"
};

const statusBadgeClass = {
  waiting_approval: "pending",
  approved: "approved",
  incoming: "incoming",
  ongoing: "ongoing",
  processing: "processing",
  paid: "processing",
  completed: "completed",
  rejected: "rejected"
};

const statusText = {
  waiting_approval: "Waiting for Approval",
  approved: "Approved",
  incoming: "Incoming",
  ongoing: "Ongoing",
  processing: "Processing",
  paid: "Awaiting Release",
  completed: "Completed",
  rejected: "Rejected"
};

const progressColor = {
  waiting_approval: "var(--secondary)",
  approved: "var(--primary)",
  incoming: "var(--primary)",
  ongoing: "var(--primary)",
  processing: "var(--primary)",
  paid: "var(--primary)",
  completed: "var(--success)",
  rejected: "var(--danger)"
};


  data.forEach(request => {
    const s = (request.status || 'waiting_approval').toLowerCase();
    const width = progressWidth[s] || "25%";
    const color = progressColor[s] || "var(--secondary)";
    const badge = statusBadgeClass[s] || "pending";
    const label = statusText[s] || "Waiting for Approval";

    const submitted = request.submitted || '—';
    const updated   = request.updated || submitted;
    const officer   = request.officer || 'Barangay Staff';
    const reference = request.reference || request.id || '—';
    const estimated = request.estimated || '';

    const docsHtml = Array.isArray(request.documents) && request.documents.length
      ? request.documents.map(doc => `
          <div class="document-item">
            <i class="fas fa-file-${doc.type === 'pdf' ? 'pdf' : doc.type === 'image' ? 'image' : 'alt'}"></i>
            <span>${escapeHtml(doc.name || 'Document')}</span>
          </div>
        `).join('')
      : `<div class="document-item"><i class="fas fa-file-alt"></i><span>No documents</span></div>`;

    const timelineHtml = Array.isArray(request.timeline) && request.timeline.length
      ? request.timeline.map(item => `
          <div class="timeline-item">
            <div class="timeline-date">${escapeHtml(item.date || '')}</div>
            <div class="timeline-content">${escapeHtml(item.content || '')}</div>
          </div>
        `).join('')
      : `<div class="timeline-item">
           <div class="timeline-date">${escapeHtml(submitted)}</div>
           <div class="timeline-content">Request submitted</div>
         </div>`;

    const card = document.createElement('div');
    card.className = 'request-card';
    card.innerHTML = `
      <div class="request-summary">
        <div class="request-type">${escapeHtml(request.type || 'Request')}</div>
        <div class="request-id">#${escapeHtml(reference)}</div>
        <div class="request-date">Submitted: ${escapeHtml(submitted)}</div>
        <div class="request-status">
          <span class="status-badge ${badge}">${label}</span>
          <div class="progress-container">
            <div class="progress-bar"><div class="progress" style="width:${width}; background-color:${color}"></div></div>
            <span>${s === 'rejected' ? 'Closed' : width}</span>
          </div>
        </div>
      </div>

      <div class="request-details">
        <div class="details-content">
          <div class="details-grid">
            <div class="detail-item">
              <h3>Submission Date</h3>
              <p>${escapeHtml(submitted)}</p>
            </div>
            <div class="detail-item">
              <h3>${s === 'completed' ? 'Completed On' : s === 'rejected' ? 'Closed On' : 'Last Updated'}</h3>
              <p>${escapeHtml(updated)}</p>
            </div>
            ${estimated ? `
              <div class="detail-item">
                <h3>Estimated Completion</h3>
                <p>${escapeHtml(estimated)}</p>
              </div>
            ` : ``}
            <div class="detail-item">
              <h3>Assigned Officer</h3>
              <p>${escapeHtml(officer)}</p>
            </div>
            ${reference ? `
              <div class="detail-item">
                <h3>Reference Number</h3>
                <p>${escapeHtml(reference)}</p>
              </div>
            ` : ``}
          </div>

          <div class="documents-list">
            <h3>${s === 'completed' ? 'Issued Document' : 'Submitted Documents'}</h3>
            <div class="documents-grid">
              ${docsHtml}
            </div>
          </div>

          <div class="timeline">
            <h3>Processing Timeline</h3>
            ${timelineHtml}
          </div>

          <div class="request-actions">
            ${renderActionsByStatus(s)}
          </div>
        </div>
      </div>
    `;
    container.appendChild(card);
  });

  document.querySelectorAll('.request-card').forEach(card => {
    card.addEventListener('click', function(e) {
      if (!e.target.closest('.action-btn') && !e.target.closest('a')) {
        this.classList.toggle('expanded');
      }
    });
  });
}

function renderActionsByStatus(s) {
  if (s === 'completed') {
    return `
      <button class="action-btn primary"><i class="fas fa-print"></i> Print Confirmation</button>
      <button class="action-btn secondary"><i class="fas fa-download"></i> Download Copy</button>
    `;
  }
  if (s === 'rejected') {
    return `<div class="hint">This reservation was rejected by the barangay. You may create a new reservation.</div>`;
  }
  if (s === 'incoming') {
    return `<div class="hint">Payment received. See you at your scheduled time.</div>`;
  }
  if (s === 'approved') {
    return `<div class="hint">Approved. Please proceed to payment to secure your schedule.</div>`;
  }
  if (s === 'ongoing') {
    return `<div class="hint">Enjoy your session! You can request an extension at the desk.</div>`;
  }
  if (s === 'paid') {
    return `<div class="hint">Paid at barangay. Please wait for release.</div>`;
  }
  if (s === 'processing') {
    return `<div class="hint">Please proceed to the barangay for over-the-counter payment when notified.</div>`;
  }
  return `<div class="hint">Your request is awaiting approval by barangay staff.</div>`;
}


// ---------------- UI hooks ----------------
document.addEventListener('DOMContentLoaded', () => {
  loadRequests().catch(err => {
    console.error(err);
    const container = document.getElementById('requests-list');
    if (container) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="fas fa-triangle-exclamation"></i>
          <h3>Couldn’t load your requests</h3>
        <p>${escapeHtml(err.message || 'Please try again later.')}</p>
        <pre style="white-space:pre-wrap;background:#f8f9fa;border:1px dashed #ddd;padding:8px;margin-top:8px;">
Tried:
${PATH_CANDIDATES.map(p => ` - ${ORIGIN}${p}`).join('\n')}
        </pre>
      </div>`;
    }
  });

  const filterSel  = document.getElementById('filter-status');
  const searchInput= document.querySelector('.search-box input');
  const sortSel    = document.getElementById('sort-by');

  const rerender = () => {
    renderRequests(
      filterSel ? filterSel.value : "All Statuses",
      searchInput ? searchInput.value : "",
      sortSel ? sortSel.value : "Newest First"
    );
  };

  filterSel && filterSel.addEventListener('change', rerender);
  searchInput && searchInput.addEventListener('input', rerender);
  sortSel && sortSel.addEventListener('change', rerender);
});

// small util
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));
}
