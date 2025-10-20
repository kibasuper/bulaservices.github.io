// adminbulaservices/script/pricing.js
(function(){
  // API path relative to /adminbulaservices/Pricing.php
  const API = './php/pricing_api.php';

  // ------ utils ------
  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));
  function alertBox(msg, type='success') {
    const wrap = $('#alertWrap');
    if (!wrap) return;
    wrap.innerHTML =
      `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
       </div>`;
  }
  async function api(action, payload) {
    const url = `${API}?action=${encodeURIComponent(action)}`;
    const opt = payload ? {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    } : { credentials: 'same-origin' };

    const res = await fetch(url, opt);
    let data, raw;
    try { data = await res.clone().json(); } catch { raw = await res.text(); }

    if (res.status === 404) {
      console.error(`[pricing] 404 for URL: ${url}. Check that file exists at /adminbulaservices/php/pricing_api.php`);
    }
    if (!res.ok || !data || data.ok === false) {
      const msg = (data && (data.error || data.message)) || raw || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return data.data || {};
  }

  // ------ certificates table ------
  const certBody = $('#certBody');
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function rowTemplate({type_code='', name='', price=''}) {
    return `
      <tr>
        <td>
          <input type="text" class="form-control form-control-sm code-input" placeholder="e.g., bc"
                 value="${escapeHtml(type_code)}" maxlength="50">
  
        </td>
        <td>
          <input type="text" class="form-control form-control-sm name-input" placeholder="e.g., Barangay Clearance"
                 value="${escapeHtml(name)}" maxlength="255">
        </td>
        <td>
          <input type="number" step="0.01" min="0" class="form-control form-control-sm price-input"
                 value="${price !== '' ? Number(price) : ''}">
        </td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger del-row">Remove</button>
        </td>
      </tr>
    `;
  }
  function renderCerts(certs) {
    if (!certBody) return;
    certBody.innerHTML = (certs && certs.length)
      ? certs.map(c => rowTemplate(c)).join('')
      : rowTemplate({type_code:'bc', name:'Barangay Clearance', price:80});
  }
  function readCertsFromTable() {
    const rows = [];
    $$('#certBody tr').forEach(tr => {
      const code  = $('.code-input', tr)?.value.trim() || '';
      const name  = $('.name-input', tr)?.value.trim() || '';
      const price = $('.price-input', tr)?.value.trim();
      const p     = price === '' ? NaN : Number(price);
      rows.push({ type_code: code, name, price: p });
    });
    return rows;
  }
  function validateCerts(rows) {
    const seen = new Set();
    for (const r of rows) {
      if (!/^[a-z0-9_-]{2,50}$/.test(r.type_code)) {
        return `Invalid code "${r.type_code}". Use lowercase letters, numbers, dash/underscore (2–50 chars).`;
      }
      if (seen.has(r.type_code)) return `Duplicate code "${r.type_code}".`;
      seen.add(r.type_code);
      if (!r.name || r.name.length < 2) return `Name required for "${r.type_code}".`;
      if (!Number.isFinite(r.price) || r.price < 0) return `Invalid price for "${r.type_code}".`;
    }
    return null;
  }

  // ------ events ------
  $('#addCertRow')?.addEventListener('click', () => {
    certBody.insertAdjacentHTML('beforeend', rowTemplate({}));
  });
  certBody?.addEventListener('click', (e) => {
    if (e.target.matches('.del-row')) e.target.closest('tr')?.remove();
  });
  $('#saveCerts')?.addEventListener('click', async () => {
    const certs = readCertsFromTable();
    const err = validateCerts(certs);
    if (err) { alertBox(err, 'danger'); return; }
    try {
      await api('save_certs', { csrf: window.CSRF_TOKEN, certs });
      alertBox('Certificate prices saved.');
    } catch (e) {
      alertBox(e.message || 'Save failed', 'danger');
    }
  });
  $('#saveGym')?.addEventListener('click', async () => {
    const m = Number($('#morningRate')?.value);
    const e = Number($('#eveningRate')?.value);
    if (!Number.isFinite(m) || m < 0) { alertBox('Invalid Morning Rate', 'danger'); return; }
    if (!Number.isFinite(e) || e < 0) { alertBox('Invalid Evening Rate', 'danger'); return; }
    try {
      await api('save_gym', { csrf: window.CSRF_TOKEN, morning_rate: m, evening_rate: e });
      alertBox('Gym rates saved.');
    } catch (err) {
      alertBox(err.message || 'Save failed', 'danger');
    }
  });

  // ------ initial load ------
  (async function init(){
    try {
      const { certificates = [], gym = {} } = await api('load');
      renderCerts(certificates);
      if (gym) {
        const mr = $('#morningRate'), er = $('#eveningRate');
        if (mr) mr.value = (gym.morning_rate ?? '') !== '' ? Number(gym.morning_rate) : '';
        if (er) er.value = (gym.evening_rate ?? '') !== '' ? Number(gym.evening_rate) : '';
      }
    } catch (e) {
      alertBox(e.message || 'Failed to load pricing', 'danger');
      renderCerts([]);
    }
  })();
})();
