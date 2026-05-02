/* ═══════════════════════════════════════
   KLINIK — Utilitaires JavaScript
   Notifications polling + Messagerie + UI
═══════════════════════════════════════ */

const KlinikUI = {

  showAlert(container, message, type = 'error') {
    const icons = { success: 'check_circle', error: 'error', warning: 'warning' };
    container.innerHTML = `
      <div class="alert alert-${type}">
        <span class="material-icons">${icons[type]}</span>
        ${message}
      </div>`;
    setTimeout(() => container.innerHTML = '', 4000);
  },

  setLoading(btn, loading) {
    if (loading) {
      btn.dataset.originalText = btn.innerHTML;
      btn.innerHTML = '<span class="material-icons" style="animation:spin .7s linear infinite;display:inline-block">sync</span> Chargement...';
      btn.disabled = true;
    } else {
      btn.innerHTML = btn.dataset.originalText;
      btn.disabled = false;
    }
  },

  formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric'
    });
  },

  initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarToggle');
    if (toggle && sidebar) {
      toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }
    // Mark active nav item
    const currentPath = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item[href]').forEach(link => {
      const linkPath = link.getAttribute('href')?.split('/').pop();
      if (linkPath === currentPath) {
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        link.classList.add('active');
      }
    });
  },

  fillUserInfo(user) {
    const nameEl  = document.getElementById('sidebarUserName');
    const emailEl = document.getElementById('sidebarUserEmail');
    const initEl  = document.getElementById('topbarAvatar');
    if (nameEl)  nameEl.textContent  = `${user.prenom} ${user.nom}`;
    if (emailEl) emailEl.textContent = user.email;
    if (initEl)  initEl.textContent  = ((user.prenom?.[0]||'')+(user.nom?.[0]||'')).toUpperCase();
  }
};

/* ── Validation ── */
const KlinikValidate = {
  email(val)     { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val); },
  phone(val)     { return /^[\d\s+\-().]{8,15}$/.test(val); },
  required(val)  { return val && val.trim().length > 0; },
  minLen(val, n) { return val && val.trim().length >= n; },
  highlightField(input, valid) {
    input.classList.toggle('error', !valid);
    return valid;
  }
};

/* ── Notifications Polling ── */
const KlinikNotifications = {
  _interval: null,
  _badgeEl: null,
  _dropdownEl: null,

  init(options = {}) {
    const pollInterval = options.interval || 30000; // 30 secondes
    this._badgeEl = document.querySelector('.notif-badge');
    this._dropdownEl = document.getElementById('notifDropdown');

    // Premier fetch immédiat
    this.fetchCount();
    // Polling
    this._interval = setInterval(() => this.fetchCount(), pollInterval);

    // Clic sur l'icône
    const notifBtn = document.querySelector('.topbar-icon-btn');
    if (notifBtn) {
      notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.toggleDropdown();
      });
    }

    // Fermer dropdown au clic dehors
    document.addEventListener('click', () => this.closeDropdown());
  },

  async fetchCount() {
    try {
      const res = await fetch('../api/notifications.php?action=count');
      const data = await res.json();
      if (data.success) {
        this.updateBadge(data.count);
      }
    } catch (e) { /* silencieux */ }
  },

  updateBadge(count) {
    if (!this._badgeEl) return;
    if (count > 0) {
      this._badgeEl.textContent = count > 99 ? '99+' : count;
      this._badgeEl.style.display = 'flex';
    } else {
      this._badgeEl.style.display = 'none';
    }
  },

  async toggleDropdown() {
    if (!this._dropdownEl) {
      this.createDropdown();
    }
    const isOpen = this._dropdownEl.classList.toggle('open');
    if (isOpen) {
      await this.fetchNotifications();
    }
  },

  closeDropdown() {
    if (this._dropdownEl) this._dropdownEl.classList.remove('open');
  },

  createDropdown() {
    const dd = document.createElement('div');
    dd.id = 'notifDropdown';
    dd.className = 'notif-dropdown';
    dd.innerHTML = '<div class="notif-dropdown-header"><span>Notifications</span><button onclick="KlinikNotifications.markAllRead()">Tout lire</button></div><div class="notif-dropdown-body"><p style="padding:20px;text-align:center;color:#9ca3af;font-size:.85rem">Chargement...</p></div>';
    dd.addEventListener('click', e => e.stopPropagation());
    const parent = document.querySelector('.topbar-icon-btn');
    if (parent) parent.style.position = 'relative';
    parent?.appendChild(dd);
    this._dropdownEl = dd;
  },

  async fetchNotifications() {
    try {
      const res = await fetch('../api/notifications.php?action=fetch');
      const data = await res.json();
      if (data.success && this._dropdownEl) {
        const body = this._dropdownEl.querySelector('.notif-dropdown-body');
        if (!data.notifications || data.notifications.length === 0) {
          body.innerHTML = '<p style="padding:20px;text-align:center;color:#9ca3af;font-size:.85rem">Aucune notification</p>';
          return;
        }
        body.innerHTML = data.notifications.slice(0, 8).map(n => {
          const typeColors = { info: '#2563eb', success: '#059669', warning: '#d97706', danger: '#dc2626' };
          const icons = { info: 'info', success: 'check_circle', warning: 'warning', danger: 'error' };
          const ago = this.timeAgo(n.created_at);
          return `<div class="notif-item${n.lue ? '' : ' unread'}" onclick="KlinikNotifications.markRead(${n.id})">
            <div class="notif-icon" style="color:${typeColors[n.type]||'#2563eb'}"><span class="material-icons">${icons[n.type]||'info'}</span></div>
            <div class="notif-content"><div class="notif-title">${this.escHtml(n.titre)}</div><div class="notif-msg">${this.escHtml(n.message)}</div><div class="notif-time">${ago}</div></div>
          </div>`;
        }).join('');
      }
    } catch (e) { /* silencieux */ }
  },

  async markRead(id) {
    try {
      await fetch('../api/notifications.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'markRead', id })
      });
      this.fetchCount();
      this.fetchNotifications();
    } catch (e) { /* silencieux */ }
  },

  async markAllRead() {
    try {
      await fetch('../api/notifications.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'markAllRead' })
      });
      this.fetchCount();
      this.fetchNotifications();
    } catch (e) { /* silencieux */ }
  },

  timeAgo(dateStr) {
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60) return 'À l\'instant';
    if (diff < 3600) return Math.floor(diff/60) + ' min';
    if (diff < 86400) return Math.floor(diff/3600) + ' h';
    return Math.floor(diff/86400) + ' j';
  },

  escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  },

  stop() { if (this._interval) clearInterval(this._interval); }
};

/* ── Messages Count Polling ── */
const KlinikMessages = {
  _interval: null,

  init() {
    this.fetchCount();
    this._interval = setInterval(() => this.fetchCount(), 45000);
  },

  async fetchCount() {
    try {
      const res = await fetch('../api/messages.php?action=count');
      const data = await res.json();
      if (data.success) {
        const badges = document.querySelectorAll('.msg-badge');
        badges.forEach(b => {
          if (data.count > 0) { b.textContent = data.count; b.style.display = 'flex'; }
          else { b.style.display = 'none'; }
        });
      }
    } catch (e) { /* silencieux */ }
  },

  stop() { if (this._interval) clearInterval(this._interval); }
};

/* ── Animation spin ── */
const style = document.createElement('style');
style.textContent = `
@keyframes spin { to { transform: rotate(360deg); } }
.notif-dropdown { position:absolute; top:calc(100% + 8px); right:0; width:340px; background:#fff; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.15); border:1px solid #eef0f6; z-index:300; display:none; overflow:hidden; }
.notif-dropdown.open { display:block; animation:fadeUp .2s ease; }
.notif-dropdown-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #eef0f6; font-weight:700; font-size:.88rem; color:#1a3a6e; }
.notif-dropdown-header button { background:none; border:none; color:#2563eb; font-size:.78rem; font-weight:600; cursor:pointer; }
.notif-dropdown-body { max-height:350px; overflow-y:auto; }
.notif-item { display:flex; gap:10px; padding:12px 16px; border-bottom:1px solid #f5f7fb; cursor:pointer; transition:background .15s; }
.notif-item:hover { background:#f8faff; }
.notif-item.unread { background:#f0f7ff; }
.notif-icon .material-icons { font-size:20px; margin-top:2px; }
.notif-content { flex:1; min-width:0; }
.notif-title { font-size:.82rem; font-weight:600; color:#1a3a6e; }
.notif-msg { font-size:.76rem; color:#6b7280; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.notif-time { font-size:.68rem; color:#9ca3af; margin-top:3px; }
`;
document.head.appendChild(style);
