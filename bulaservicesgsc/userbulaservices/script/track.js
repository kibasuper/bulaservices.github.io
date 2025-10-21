// script/track.js — same-origin resolver + full renderer with “Handled By” block

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

// ---------------- Helpers ----------------
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({"&":"&amp;","<":"&gt;",">":"&lt;","\"":"&quot;","'":"&#039;"}[m]));
}

const progressWidth = {
  waiting_approval: "20%",
  approved: "40%",
  incoming: "65%",
  ongoing: "85%",
  processing: "60%",
  paid: "75%",
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

// Map <select> values in track.php to sorter names
function normalizeSortValue(val) {
  switch (String(val || '').toLowerCase()) {
    case 'newest': return 'Newest First';
    case 'oldest': return 'Oldest First';
    case 'type-az': return 'Request Type (A-Z)';
    case 'type-za': return 'Request Type (Z-A)';
    case 'status': return 'Status';
    default: return 'Newest First';
  }
}

// Map filter values to internal statuses
function normalizeFilterValue(val) {
  const v = String(val || '').toLowerCase();
  if (v === 'pending') return 'waiting_approval';
  if (v === 'processing') return 'processing';
  if (v === 'completed') return 'completed';
  if (v === 'rejected') return 'rejected';
  return 'all';
}

// ---------------- RENDER ----------------
function renderRequests(filterSelectVal = "all", searchTerm = "", sortSelectVal = "newest") {
  const container = document.getElementById('requests-list');
  if (!container) return;

  const sorter = normalizeSortValue(sortSelectVal);
  const filter = normalizeFilterValue(filterSelectVal);

  let data = [...requests];

  if (filter !== "all") {
    data = data.filter(r => {
      const s = (r.status || '').toLowerCase();
      if (filter === 'processing') {
        // include both legacy "processing" AND "paid" (awaiting release)
        return s === 'processing' || s === 'paid' || s === 'approved';
      }
      return s === filter;
    });
  }

  if (searchTerm && searchTerm.trim() !== '') {
    const q = searchTerm.toLowerCase();
    data = data.filter(r => JSON.stringify(r).toLowerCase().includes(q));
  }

  switch (sorter) {
    case "Newest First":
      data.sort((a,b)=> (new Date(b.submitted||0)) - (new Date(a.submitted||0)));
      break;
    case "Oldest First":
      data.sort((a,b)=> (new Date(a.submitted||0)) - (new Date(b.submitted||0)));
      break;
    case "Request Type (A-Z)":
      data.sort((a,b)=> (a.type||'').localeCompare(b.type||'')); break;
    case "Request Type (Z-A)":
      data.sort((a,b)=> (b.type||'').localeCompare(a.type||'')); break;
    case "Status":
      const rank = s => ({waiting_approval:1, approved:2, processing:3, paid:4, ongoing:5, incoming:6, completed:7, rejected:9})[(s||'').toLowerCase()] || 99;
      data.sort((a,b)=> rank(a.status) - rank(b.status)); break;
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

    // ---- NEW: “Handled By” block ----
    const staffHtml = `
      <div class="details-grid staff-grid">
        <div class="detail-item">
          <h3>Approved By</h3>
          <p>${escapeHtml(request.approved_by_name || '— not yet')}</p>
        </div>
        <div class="detail-item">
          <h3>Processed By</h3>
          <p>${escapeHtml(request.processed_by_name || '— not yet')}</p>
        </div>
        <div class="detail-item">
          <h3>Released By</h3>
          <p>${escapeHtml(request.released_by_name || '— not yet')}</p>
        </div>
      </div>
    `;

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
              </div>` : ``}
            <div class="detail-item">
              <h3>Assigned Officer</h3>
              <p>${escapeHtml(officer)}</p>
            </div>
            ${reference ? `
              <div class="detail-item">
                <h3>Reference Number</h3>
                <p>${escapeHtml(reference)}</p>
              </div>` : ``}
          </div>

          <div class="documents-list">
            <h3>${s === 'completed' ? 'Issued Document' : 'Submitted Documents'}</h3>
            <div class="documents-grid">
              ${docsHtml}
            </div>
          </div>

          <div class="section">
            <h3 class="section-title"><i class="fas fa-user-check"></i> Handled By</h3>
            ${staffHtml}
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
    return `<div class="hint">This request was rejected. You may submit a new request.</div>`;
  }
  if (s === 'incoming') {
    return `<div class="hint">Payment received. Please wait for your schedule / release.</div>`;
  }
  if (s === 'approved') {
    return `<div class="hint">Approved. Please proceed to payment when instructed.</div>`;
  }
  if (s === 'ongoing') {
    return `<div class="hint">In progress.</div>`;
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

  const filterSel   = document.getElementById('filter-status');   // values: all, pending, processing, completed, rejected
  const searchInput = document.getElementById('search-input');    // track.php input id
  const sortSel     = document.getElementById('sort-by');         // values: newest, oldest, type-az, type-za, status

  const rerender = () => {
    renderRequests(
      filterSel ? filterSel.value : "all",
      searchInput ? searchInput.value : "",
      sortSel ? sortSel.value : "newest"
    );
  };

  filterSel && filterSel.addEventListener('change', rerender);
  searchInput && searchInput.addEventListener('input', rerender);
  sortSel && sortSel.addEventListener('change', rerender);
});
