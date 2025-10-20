/* =========================================================================
   Announcement Admin - COMPLETE FIXED VERSION (2025-10-14)
   - API_BASE points to admin subdomain
   - Proper POST body handling (apiCall(params, body, silent))
   - Robust event.currentTarget usage
   ========================================================================= */

class AnnouncementAdmin {
    constructor() {
        // API is hosted on the ADMIN subdomain
        this.API_BASE = 'https://admin.bulaservicesgsc.com/php/announce_api.php';
        this.announcements = [];
        this.init();
    }

    $(sel) { return document.querySelector(sel); }
    $$(sel) { return document.querySelectorAll(sel); }

    init() {
        this.bindEvents();
        this.checkAuthAndLoad();
    }

    async checkAuthAndLoad() {
        try {
            // Silent probe for admin auth
            await this.apiCall({ action: 'list', scope: 'admin', limit: 1 }, null, true);
            await this.loadAnnouncements();
        } catch (e) {
            console.error('Auth failed:', e);
            this.showLoginPrompt();
        }
    }

    /**
     * apiCall helper
     * @param {Object} params - querystring params
     * @param {Object|null} body - JSON body for POST; null => GET
     * @param {Boolean} silent - suppress toast on error
     */
    async apiCall(params = {}, body = null, silent = false) {
        const qs = new URLSearchParams(params).toString();
        const url = `${this.API_BASE}?${qs}`;

        const opts = {
            method: body ? 'POST' : 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include', // admin session required
            body: body ? JSON.stringify(body) : undefined
        };

        try {
            const res = await fetch(url, opts);
            if (res.status === 401) {
                if (!silent) this.showToast('Please log in to access announcements', 'error');
                throw new Error('Authentication required');
            }
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'API request failed');
            return data;
        } catch (err) {
            if (!silent) this.showToast(err.message, 'error');
            throw err;
        }
    }

    showLoginPrompt() {
        const grid = this.$('#announcementsGrid');
        if (!grid) return;
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-lock" style="color:#ef4444;"></i>
                <h3>Authentication Required</h3>
                <p>Please log in to manage announcements</p>
                <button class="btn btn-primary" onclick="window.location.href='/admin/login'">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </button>
            </div>
        `;
    }

    bindEvents() {
        this.$('#addAnnouncementBtn')?.addEventListener('click', () => this.openModal());
        this.$('#modalCloseBtn')?.addEventListener('click', () => this.closeModal());
        this.$('#cancelBtn')?.addEventListener('click', () => this.closeModal());
        this.$('#addModal')?.addEventListener('click', (e) => {
            if (e.target === this.$('#addModal')) this.closeModal();
        });
        this.$('#imageUpload')?.addEventListener('change', (e) => this.handleImageSelect(e));
        this.$('#fileUpload')?.addEventListener('click', () => this.$('#imageUpload')?.click());
        this.$('#uploadBtn')?.addEventListener('click', () => this.handlePublish());
        this.$('#announcementTitle')?.addEventListener('input', () => this.validateForm());
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
        const grid = this.$('#announcementsGrid');
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
                        ? `<img src="${ann.image_url || ann.image_path}" alt="${this.escapeHtml(ann.title)}" class="announcement-image" onerror="this.style.display='none'">`
                        : `<div class="announcement-image-placeholder"><i class="fas fa-image"></i></div>`
                    }
                </div>
                <div class="announcement-details">
                    <div class="announcement-meta">
                        <span class="status-badge ${ann.status}">${ann.status}</span>
                        <span class="announcement-date">${this.formatDate(ann.published_at || ann.updated_at)}</span>
                    </div>
                    <h3 class="announcement-title" contenteditable="true" data-field="title">${this.escapeHtml(ann.title)}</h3>
                    <div class="announcement-content" contenteditable="true" data-field="content">${this.escapeHtml(ann.content || '')}</div>
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

        // Inline edit (save on blur, skip if unchanged)
        this.$$('.announcement-title, .announcement-content').forEach(el => {
            let last = el.textContent;
            el.addEventListener('focus', () => { last = el.textContent; });
            el.addEventListener('blur', (e) => {
                const txt = e.currentTarget.textContent.trim();
                if (txt === (last || '').trim()) return;
                const card = e.currentTarget.closest('.announcement-card');
                const id = card.dataset.id;
                const field = e.currentTarget.dataset.field;
                this.updateAnnouncement(id, { [field]: txt });
            });
        });

        // Toggle status
        this.$$('.toggle-status').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const button = e.currentTarget;
                const card = button.closest('.announcement-card');
                const id = card.dataset.id;
                const newStatus = button.dataset.status;
                try {
                    await this.updateAnnouncement(id, { status: newStatus });
                    this.showToast(`Announcement ${newStatus === 'published' ? 'published' : 'unpublished'}`, 'success');
                    this.loadAnnouncements();
                } catch {}
            });
        });

        // Delete
        this.$$('.remove-announcement').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = e.currentTarget.closest('.announcement-card').dataset.id;
                if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) return;
                try {
                    await this.deleteAnnouncement(id);
                    this.showToast('Announcement deleted', 'success');
                    this.announcements = this.announcements.filter(a => a.id != id);
                    this.renderAnnouncements();
                } catch {}
            });
        });
    }

    openModal() {
        this.$('#addModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        this.resetForm();
    }
    closeModal() {
        this.$('#addModal').classList.remove('active');
        document.body.style.overflow = '';
        this.resetForm();
    }
    resetForm() {
        this.$('#announcementTitle').value = '';
        this.$('#announcementContent').value = '';
        this.$('#imageUpload').value = '';
        this.$('#imagePreview').style.display = 'none';
        this.$('#imagePreview').src = '';
        this.$('#uploadBtn').disabled = true;
        this.$('#fileUpload').style.borderColor = '#e2e8f0';
    }

    handleImageSelect(e) {
        const f = e.target.files[0];
        if (!f) return;
        if (!f.type.startsWith('image/')) return this.showToast('Please select an image file', 'error');
        if (f.size > 2 * 1024 * 1024) return this.showToast('Image must be less than 2MB', 'error');

        const reader = new FileReader();
        reader.onload = (ev) => {
            this.$('#imagePreview').src = ev.target.result;
            this.$('#imagePreview').style.display = 'block';
            this.$('#fileUpload').style.borderColor = '#10b981';
            this.validateForm();
        };
        reader.readAsDataURL(f);
    }

    validateForm() {
        const title = this.$('#announcementTitle').value.trim();
        this.$('#uploadBtn').disabled = !title;
    }

    async handlePublish() {
        const title = this.$('#announcementTitle').value.trim();
        const content = this.$('#announcementContent').value.trim();
        const imageFile = this.$('#imageUpload').files[0];

        if (!title) return this.showToast('Please enter a title', 'error');

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
                content,
                image_path: imagePath,
                status: 'published'
            });

            this.showToast('Announcement published successfully!', 'success');
            this.closeModal();
            this.loadAnnouncements();
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    }

    async updateAnnouncement(id, data) {
        return this.apiCall({ action: 'update' }, { id: Number(id), ...data });
    }
    async deleteAnnouncement(id) {
        return this.apiCall({ action: 'delete' }, { id: Number(id) });
    }

    showToast(msg, type = 'success') {
        document.querySelectorAll('.toast').forEach(t => t.remove());
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.textContent = msg;
        el.style.cssText = `
            position: fixed; top: 20px; right: 20px; background: ${type === 'success' ? '#10b981' : '#ef4444'};
            color: white; padding: 12px 20px; border-radius: 6px; z-index: 10000; animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-weight: 500;
        `;
        document.body.appendChild(el);
        setTimeout(() => {
            if (el.parentNode) {
                el.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => el.remove(), 300);
            }
        }, 4000);
    }

    formatDate(s) {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }
}

// Inline styles for toasts / cards (unchanged)
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
.empty-state { text-align:center; padding:3rem 1rem; color:#64748b; grid-column:1/-1; }
.empty-state i { font-size:3rem; margin-bottom:1rem; color:#cbd5e1; }
.btn { padding:.5rem 1rem; border:none; border-radius:4px; cursor:pointer; font-weight:500; transition:.2s; display:inline-flex; align-items:center; gap:.5rem; }
.btn-primary{ background:#3b82f6; color:#fff; } .btn-success{ background:#10b981; color:#fff; } .btn-warning{ background:#f59e0b; color:#fff; } .btn-danger{ background:#ef4444; color:#fff; }
.btn:hover { opacity:.9; transform: translateY(-1px); }
.announcement-card{ border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff; transition:.2s; }
.announcement-card:hover{ transform: translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.1); }
.announcement-image-container{ height:200px; overflow:hidden; background:#f8fafc; }
.announcement-image{ width:100%; height:100%; object-fit:cover; }
.announcement-image-placeholder{ height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:2rem; }
.announcement-details{ padding:1rem; }
.announcement-meta{ display:flex; justify-content:space-between; align-items:center; margin-bottom:.5rem; gap:.5rem; }
.status-badge{ padding:.25rem .5rem; border-radius:4px; font-size:.75rem; font-weight:600; text-transform:uppercase; }
.status-badge.published{ background:#dcfce7; color:#166534; }
.status-badge.draft{ background:#fef3c7; color:#92400e; }
.announcement-title{ font-size:1.125rem; font-weight:600; margin-bottom:.5rem; color:#1e293b; }
.announcement-content{ color:#64748b; line-height:1.5; }
.announcement-actions{ padding:1rem; border-top:1px solid #f1f5f9; display:flex; gap:.5rem; }
`;
document.head.appendChild(style);

document.addEventListener('DOMContentLoaded', () => {
    window.announcementAdmin = new AnnouncementAdmin();
});
