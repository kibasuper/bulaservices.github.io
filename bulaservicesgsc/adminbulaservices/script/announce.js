/* =========================================================================
   Announcements Admin (Title + Image only) • 2025-10-26
   - Pretty toasts
   - Designed alert/confirm modal
   - Inline title edit, publish/unpublish/delete
   - Uses window.__ANNOUNCE_API__ or falls back to admin absolute URL
   ========================================================================= */

/* ---------- Alert / Confirm Manager ---------- */
class BXAlert {
  constructor() {
    this.overlay = document.getElementById('bx-alert-overlay');
    this.iconEl  = document.getElementById('bx-alert-icon');
    this.titleEl = document.getElementById('bx-alert-title');
    this.msgEl   = document.getElementById('bx-alert-message');
    this.actions = document.getElementById('bx-alert-actions');
    this.btnClose = document.getElementById('bx-alert-close');

    this.handleKey = this.handleKey.bind(this);
    this.btnClose?.addEventListener('click', () => this.hide());
  }

  setIcon(type='info'){
    this.iconEl.className = 'bx-alert__icon ' + (type || 'info');
    const map = { info:'fa-info-circle', success:'fa-check', warn:'fa-exclamation', error:'fa-times' };
    this.iconEl.innerHTML = `<i class="fas ${map[type] || map.info}"></i>`;
  }

  show({ title='Notice', message='', type='info', buttons=[] }){
    this.setIcon(type);
    this.titleEl.textContent = title;
    this.msgEl.textContent = message;

    this.actions.innerHTML = '';
    buttons.forEach(btn => {
      const b = document.createElement('button');
      b.className = 'bx-btn ' + (btn.variant || 'bx-btn--primary');
      b.textContent = btn.label || 'OK';
      b.addEventListener('click', () => {
        if (typeof btn.onClick === 'function') btn.onClick();
        this.hide();
      });
      this.actions.appendChild(b);
    });

    this.overlay.classList.add('active');
    document.addEventListener('keydown', this.handleKey);
  }

  hide(){
    this.overlay.classList.remove('active');
    document.removeEventListener('keydown', this.handleKey);
  }

  handleKey(e){
    if (e.key === 'Escape') this.hide();
  }

  alert(message, { title='Notice', type='info' } = {}){
    return new Promise(resolve => {
      this.show({
        title, message, type,
        buttons: [{ label:'OK', variant:'bx-btn--primary', onClick: () => resolve(true) }]
      });
    });
  }

  confirm(message, { title='Confirm', type='warn', okText='Yes', cancelText='Cancel' } = {}){
    return new Promise(resolve => {
      this.show({
        title, message, type,
        buttons: [
          { label: cancelText, variant:'bx-btn--ghost', onClick: () => resolve(false) },
          { label: okText,     variant:'bx-btn--danger', onClick: () => resolve(true)  }
        ]
      });
    });
  }
}
const bxAlert = new BXAlert();

/* ---------- Toasts ---------- */
function showToast(message, type = 'success') {
  const root = document.getElementById('toast-root') || document.body;
  const icons = {
    success: '<i class="fas fa-check-circle"></i>',
    error:   '<i class="fas fa-times-circle"></i>',
    info:    '<i class="fas fa-info-circle"></i>',
    warn:    '<i class="fas fa-exclamation-triangle"></i>'
  };
  const toast = document.createElement('div');
  toast.className = `bx-toast bx-toast--${type}`;
  toast.innerHTML = `
    <div class="bx-toast__icon">${icons[type] || icons.info}</div>
    <div class="bx-toast__body">${escapeHtml(message)}</div>
    <button class="bx-toast__close" aria-label="Close toast">
      <i class="fas fa-times"></i>
    </button>
    <div class="bx-toast__progress"></div>
  `;
  root.appendChild(toast);
  const remove = () => { toast.classList.add('bx-toast--out'); setTimeout(() => toast.remove(), 250); };
  let timer = setTimeout(remove, 4000);
  toast.addEventListener('mouseenter', () => clearTimeout(timer));
  toast.addEventListener('mouseleave', () => { timer = setTimeout(remove, 1200); });
  toast.querySelector('.bx-toast__close').addEventListener('click', remove);
  requestAnimationFrame(() => toast.classList.add('bx-toast--in'));
}

/* ---------- Utilities ---------- */
function escapeHtml(text){ const div = document.createElement('div'); div.textContent = text ?? ''; return div.innerHTML; }
function $(sel){ return document.querySelector(sel); }
function $$(sel){ return document.querySelectorAll(sel); }

/* ---------- Main Admin Class ---------- */
class AnnouncementAdmin {
  constructor() {
    const fallback = 'https://admin.bulaservicesgsc.com/php/announce_api.php';
    const cfg = (typeof window !== 'undefined' && window.__ANNOUNCE_API__) ? window.__ANNOUNCE_API__ : fallback;
    this.API_BASE = new URL(cfg, window.location.origin).href;

    this.announcements = [];
    this.init();
  }

  init() {
    this.bindEvents();
    this.checkAuthAndLoad();
  }

  async checkAuthAndLoad() {
    try {
      await this.apiCall({ action: 'list', scope: 'admin', limit: 1 }, null, true);
      await this.loadAnnouncements();
    } catch (e) {
      console.error('Auth failed:', e);
      this.showLoginPrompt();
      bxAlert.alert('Please log in to access announcements.', { title: 'Authentication Required', type: 'warn' });
    }
  }

  async apiCall(params = {}, body = null, silent = false) {
    const qs = new URLSearchParams(params).toString();
    const url = `${this.API_BASE}?${qs}`;
    const opts = {
      method: body ? 'POST' : 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: body ? JSON.stringify(body) : undefined
    };
    try {
      const res = await fetch(url, opts);
      if (res.status === 401) {
        if (!silent) showToast('Please log in to access announcements', 'error');
        throw new Error('Authentication required');
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'API request failed');
      return data;
    } catch (err) {
      if (!silent) showToast(err.message, 'error');
      throw err;
    }
  }

  bindEvents() {
    $('#addAnnouncementBtn')?.addEventListener('click', () => this.openModal());
    $('#modalCloseBtn')?.addEventListener('click', () => this.closeModal());
    $('#cancelBtn')?.addEventListener('click', () => this.closeModal());
    $('#addModal')?.addEventListener('click', (e) => { if (e.target === $('#addModal')) this.closeModal(); });
    $('#imageUpload')?.addEventListener('change', (e) => this.handleImageSelect(e));
    $('#fileUpload')?.addEventListener('click', () => $('#imageUpload')?.click());
    $('#fileUpload')?.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('#imageUpload')?.click(); } });
    $('#uploadBtn')?.addEventListener('click', () => this.handlePublish());
    $('#announcementTitle')?.addEventListener('input', () => this.validateForm());
  }

  async loadAnnouncements() {
    try {
      const data = await this.apiCall({ action: 'list', scope: 'admin', limit: 100 });
      this.announcements = data.data.items || [];
      this.renderAnnouncements();
    } catch {
      this.announcements = [];
      this.renderAnnouncements();
    }
  }

  renderAnnouncements() {
    const grid = $('#announcementsGrid');
    if (!grid) return;

    if (!this.announcements.length) {
      grid.innerHTML = `
        <div class="empty-state">
          <i class="fas fa-bullhorn"></i>
          <h3>No announcements yet</h3>
          <p>Create your first announcement to get started</p>
          <button class="btn btn-primary" onclick="announcementAdmin.openModal()">
            <i class="fas fa-plus"></i> Create Announcement
          </button>
        </div>
      `;
      return;
    }

    grid.innerHTML = this.announcements.map(ann => `
      <div class="announcement-card" data-id="${ann.id}">
        <div class="announcement-image-container">
          ${ann.image_url || ann.image_path
            ? `<img src="${ann.image_url || ann.image_path}" alt="${escapeHtml(ann.title)}" class="announcement-image" onerror="this.style.display='none'">`
            : `<div class="announcement-image-placeholder"><i class="fas fa-image"></i></div>`
          }
        </div>
        <div class="announcement-details">
          <div class="announcement-meta">
            <span class="status-badge ${ann.status}">${ann.status}</span>
            <span class="announcement-date">${this.formatDate(ann.published_at || ann.updated_at)}</span>
          </div>
          <h3 class="announcement-title" contenteditable="true" data-field="title">${escapeHtml(ann.title)}</h3>
        </div>
        <div class="announcement-actions">
          <button class="btn ${ann.status === 'published' ? 'btn-warning' : 'btn-success'} toggle-status"
                  data-status="${ann.status === 'published' ? 'draft' : 'published'}">
            <i class="fas ${ann.status === 'published' ? 'fa-eye-slash' : 'fa-eye'}"></i>
            ${ann.status === 'published' ? 'Unpublish' : 'Publish'}
          </button>
          <button class="btn btn-danger remove-announcement">
            <i class="fas fa-trash"></i> Delete
          </button>
        </div>
      </div>
    `).join('');

    // Inline title edit
    $$('.announcement-title').forEach(el => {
      let last = el.textContent;
      el.addEventListener('focus', () => { last = el.textContent; });
      el.addEventListener('blur', async (e) => {
        const txt = e.currentTarget.textContent.trim();
        if (txt === (last || '').trim()) return;
        const card = e.currentTarget.closest('.announcement-card');
        const id = card.dataset.id;
        try {
          await this.updateAnnouncement(id, { title: txt });
          showToast('Title updated', 'success');
        } catch (err) {
          bxAlert.alert(err?.message || 'Failed to update title.', { title: 'Error', type: 'error' });
          e.currentTarget.textContent = last;
        }
      });
    });

    // Toggle status
    $$('.toggle-status').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const button = e.currentTarget;
        const card = button.closest('.announcement-card');
        const id = card.dataset.id;
        const newStatus = button.dataset.status;
        try {
          await this.updateAnnouncement(id, { status: newStatus });
          showToast(`Announcement ${newStatus === 'published' ? 'published' : 'unpublished'}`, 'success');
          this.loadAnnouncements();
        } catch (err) {
          bxAlert.alert(err?.message || 'Failed to change status.', { title: 'Error', type: 'error' });
        }
      });
    });

    // Delete with designed confirm
    $$('.remove-announcement').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const id = e.currentTarget.closest('.announcement-card').dataset.id;
        const ok = await bxAlert.confirm(
          'Delete this announcement? This action cannot be undone.',
          { title: 'Delete Announcement', type: 'error', okText: 'Delete', cancelText: 'Cancel' }
        );
        if (!ok) return;
        try {
          await this.deleteAnnouncement(id);
          showToast('Announcement deleted', 'success');
          this.announcements = this.announcements.filter(a => a.id != id);
          this.renderAnnouncements();
        } catch (err) {
          bxAlert.alert(err?.message || 'Failed to delete. Please try again.', { title: 'Error', type: 'error' });
        }
      });
    });
  }

  openModal() {
    $('#addModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    this.resetForm();
  }
  closeModal() {
    $('#addModal').classList.remove('active');
    document.body.style.overflow = '';
    this.resetForm();
  }
  resetForm() {
    $('#announcementTitle').value = '';
    $('#imageUpload').value = '';
    $('#imagePreview').style.display = 'none';
    $('#imagePreview').src = '';
    $('#uploadBtn').disabled = true;
    $('#fileUpload').style.borderColor = '#e2e8f0';
  }

  handleImageSelect(e) {
    const f = e.target.files[0];
    if (!f) return;
    if (!f.type.startsWith('image/')) return showToast('Please select an image file', 'error');
    if (f.size > 2 * 1024 * 1024) return showToast('Image must be less than 2MB', 'error');

    const reader = new FileReader();
    reader.onload = (ev) => {
      $('#imagePreview').src = ev.target.result;
      $('#imagePreview').style.display = 'block';
      $('#fileUpload').style.borderColor = '#10b981';
      this.validateForm();
    };
    reader.readAsDataURL(f);
  }

  validateForm() {
    const title = $('#announcementTitle').value.trim();
    $('#uploadBtn').disabled = !title;
  }

  async handlePublish() {
    const title = $('#announcementTitle').value.trim();
    const imageFile = $('#imageUpload').files[0];

    if (!title) return showToast('Please enter a title', 'error');

    try {
      let imagePath = '';
      if (imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);
        const uploadRes = await fetch(`${this.API_BASE}?action=upload`, {
          method: 'POST',
          body: formData,
          credentials: 'include'
        });
        if (!uploadRes.ok) throw new Error(`Upload failed: HTTP ${uploadRes.status}`);
        const up = await uploadRes.json();
        if (!up.success) throw new Error(up.message || 'Image upload failed');
        imagePath = up.data.image_path;
      }

      await this.apiCall({ action: 'create' }, {
        title,
        image_path: imagePath,
        status: 'published'
      });

      showToast('Announcement published successfully!', 'success');
      this.closeModal();
      this.loadAnnouncements();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async updateAnnouncement(id, data) {
    return this.apiCall({ action: 'update' }, { id: Number(id), ...data });
  }
  async deleteAnnouncement(id) {
    return this.apiCall({ action: 'delete' }, { id: Number(id) });
  }

  formatDate(s) {
    if (!s) return '';
    const d = new Date((s || '').replace(' ', 'T'));
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  showLoginPrompt() {
    const grid = $('#announcementsGrid');
    if (!grid) return;
    grid.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-lock" style="color:#ef4444;"></i>
        <h3>Authentication Required</h3>
        <p>Please log in to manage announcements</p>
        <button class="btn btn-primary" onclick="window.location.href='index.php'">
          <i class="fas fa-sign-in-alt"></i> Go to Login
        </button>
      </div>
    `;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  window.announcementAdmin = new AnnouncementAdmin();
});
