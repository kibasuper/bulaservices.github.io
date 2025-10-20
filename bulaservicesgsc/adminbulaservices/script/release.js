// script/release.js

const relSearch = document.getElementById('relSearch');
const relBtn = document.getElementById('relSearchBtn');
const tbody = document.getElementById('relTbody');

document.addEventListener('DOMContentLoaded', () => {
  loadPaid(''); // load latest on page open
});

relBtn?.addEventListener('click', () => loadPaid(relSearch?.value || ''));
relSearch?.addEventListener('keydown', e => { if (e.key === 'Enter') loadPaid(relSearch.value || ''); });

async function loadPaid(query = '') {
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Loading…</td></tr>`;
  try {
    // cache-buster + no-store
    const res = await fetch(`./php/release_list.php?q=${encodeURIComponent(query)}&t=${Date.now()}`, {
      cache: 'no-store',
      credentials: 'same-origin'
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Load failed');

    const list = data.items || [];
    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#666;">No paid & unclaimed items found</td></tr>`;
      return;
    }

    tbody.innerHTML = '';
    for (const it of list) {
      const tr = document.createElement('tr');
      tr.dataset.source = it.source || 'service';
      tr.dataset.id = String(it.id);

      tr.innerHTML = `
        <td>${it.code}</td>
        <td><span class="type-badge">${it.type}</span></td>
        <td>${it.customer}</td>
        <td>${it.paid_at ? new Date(it.paid_at).toLocaleString() : '—'}</td>
        <td>₱${Number(it.amount || 0).toFixed(2)}</td>
        <td>
          <button class="claim-btn" data-src="${it.source}" data-id="${it.id}">
            <i class="fas fa-check"></i> Mark Claimed
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    }

    document.querySelectorAll('.claim-btn').forEach(btn => {
      btn.addEventListener('click', () => claimItem(btn.dataset.src, btn.dataset.id, btn));
    });

  } catch (e) {
    console.error(e);
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#c00;">Error loading</td></tr>`;
  }
}

async function claimItem(source, id, btnEl) {
  const { value: notes } = await Swal.fire({
    title: 'Confirm release',
    input: 'text',
    inputLabel: 'Notes (optional)',
    showCancelButton: true,
    confirmButtonText: 'Mark as Claimed'
  });
  if (notes === undefined) return;

  // Optimistic removal for instant feedback
  const row = btnEl?.closest('tr');
  const hadRow = !!row;
  if (row) row.remove();

  try {
    const res = await fetch('./php/release_claim.php?debug=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ items: [{ source, id: Number(id), notes: notes || '' }] })
    });
    const data = await res.json();
    console.log('Claim result:', data);

    if (!data.success) throw new Error(data.message || 'Claim failed');

    if (!data.updated) {
      // server says nothing updated; refresh and show message
      await loadPaid(relSearch?.value || '');
      const firstErr = (data.results && data.results[0] && data.results[0].error) || 'No rows updated.';
      throw new Error(firstErr);
    }

    await loadPaid(relSearch?.value || '');
    Swal.fire({ icon: 'success', title: 'Marked as claimed', timer: 1400, showConfirmButton: false });

  } catch (e) {
    console.error(e);
    Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'Claim failed' });
    // Re-sync UI from server
    await loadPaid(relSearch?.value || '');
  }
}
