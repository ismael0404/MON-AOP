/* ═══════════════════════════════════════
   KLINIK — JS Global (Auto-init)
═══════════════════════════════════════ */

const KlinikUI = {
  showAlert(container, message, type = 'error') {
    const icons = { success: 'check_circle', error: 'error', warning: 'warning' };
    container.innerHTML = `<div class="alert alert-${type}"><span class="material-icons">${icons[type]}</span>${message}</div>`;
    setTimeout(() => container.innerHTML = '', 4000);
  },
  setLoading(btn, loading) {
    if (loading) { btn.dataset.originalText = btn.innerHTML; btn.innerHTML = '<span class="material-icons" style="animation:spin .7s linear infinite;display:inline-block">sync</span> Chargement...'; btn.disabled = true; }
    else { btn.innerHTML = btn.dataset.originalText; btn.disabled = false; }
  },
  formatDate(dateStr) { if (!dateStr) return '—'; return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }); },
  initSidebar() {
    const sidebar = document.getElementById('sidebar'), toggle = document.getElementById('sidebarToggle');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    const cur = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item[href]').forEach(link => { if (link.getAttribute('href')?.split('/').pop() === cur) { document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active')); link.classList.add('active'); } });
  },
  fillUserInfo(user) {
    const n = document.getElementById('sidebarUserName'), e = document.getElementById('sidebarUserEmail'), a = document.getElementById('topbarAvatar');
    if (n) n.textContent = `${user.prenom} ${user.nom}`;
    if (e) e.textContent = user.email;
    if (a) a.textContent = ((user.prenom?.[0]||'')+(user.nom?.[0]||'')).toUpperCase();
  }
};

const KlinikValidate = {
  email(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); },
  phone(v) { return /^[\d\s+\-().]{8,15}$/.test(v); },
  required(v) { return v && v.trim().length > 0; },
  minLen(v, n) { return v && v.trim().length >= n; },
  highlightField(input, valid) { input.classList.toggle('error', !valid); return valid; }
};

/* ── Helper: detect base path to /api/ ── */
function _klinikApiBase() {
  const p = window.location.pathname;
  const parts = p.split('/');
  // Find the role folder (admin, medecin, patient, laborantin, caissier)
  const roles = ['admin','medecin','patient','laborantin','caissier'];
  for (let i = parts.length - 1; i >= 0; i--) {
    if (roles.includes(parts[i])) {
      return parts.slice(0, i).join('/') + '/api/';
    }
  }
  return '../api/';
}
const KLINIK_API = _klinikApiBase();

/* ══════════════════════════════════════
   NOTIFICATIONS — Auto-init system
══════════════════════════════════════ */
const KlinikNotifications = {
  _interval: null, _badgeEl: null, _btnEl: null, _dropdownEl: null,

  init() {
    // Find the notification button by icon content
    this._btnEl = this._findIconBtn('notifications');
    if (!this._btnEl) return;
    this._btnEl.style.position = 'relative';
    this._btnEl.style.cursor = 'pointer';

    // Ensure badge exists
    this._badgeEl = this._btnEl.querySelector('.notif-badge');
    if (!this._badgeEl) {
      this._badgeEl = document.createElement('span');
      this._badgeEl.className = 'notif-badge';
      this._badgeEl.style.display = 'none';
      this._btnEl.appendChild(this._badgeEl);
    }
    this._badgeEl.style.display = 'none';

    // Create dropdown
    this._createDropdown();

    // Click handler
    this._btnEl.addEventListener('click', (e) => { e.stopPropagation(); this._toggleDropdown(); });
    document.addEventListener('click', () => this._closeDropdown());

    // Start polling
    this.fetchCount();
    this._interval = setInterval(() => this.fetchCount(), 5000);
  },

  _findIconBtn(iconName) {
    const icons = document.querySelectorAll('.topbar-icon-btn .material-icons');
    for (const ic of icons) {
      if (ic.textContent.trim() === iconName) return ic.closest('.topbar-icon-btn');
    }
    return null;
  },

  _createDropdown() {
    const dd = document.createElement('div');
    dd.className = 'klinik-notif-dropdown';
    dd.innerHTML = '<div class="knd-header"><span>Notifications</span><button class="knd-mark-all">Tout marquer lu</button></div><div class="knd-body"><p class="knd-empty">Chargement...</p></div>';
    dd.addEventListener('click', e => e.stopPropagation());
    dd.querySelector('.knd-mark-all').addEventListener('click', () => this._markAllRead());
    this._btnEl.appendChild(dd);
    this._dropdownEl = dd;
  },

  async fetchCount() {
    try {
      const r = await fetch(KLINIK_API + 'notifications.php?action=count');
      const d = await r.json();
      if (d.success) this._updateBadge(d.count);
    } catch(e) {}
  },

  _updateBadge(count) {
    if (!this._badgeEl) return;
    if (count > 0) { this._badgeEl.textContent = count > 99 ? '99+' : count; this._badgeEl.style.display = 'flex'; }
    else { this._badgeEl.style.display = 'none'; }
  },

  async _toggleDropdown() {
    if (!this._dropdownEl) return;
    const open = this._dropdownEl.classList.toggle('open');
    // Close messages dropdown if open
    if (KlinikMessages._dropdownEl) KlinikMessages._dropdownEl.classList.remove('open');
    if (open) await this._fetchList();
  },

  _closeDropdown() { if (this._dropdownEl) this._dropdownEl.classList.remove('open'); },

  async _fetchList() {
    try {
      const r = await fetch(KLINIK_API + 'notifications.php?action=fetch');
      const d = await r.json();
      const body = this._dropdownEl.querySelector('.knd-body');
      if (!d.success || !d.notifications || d.notifications.length === 0) {
        body.innerHTML = '<p class="knd-empty">Aucune notification</p>'; return;
      }
      body.innerHTML = d.notifications.slice(0, 10).map(n => {
        const colors = { info:'#2563eb', success:'#059669', warning:'#d97706', danger:'#dc2626' };
        const icons = { info:'info', success:'check_circle', warning:'warning', danger:'error' };
        return `<div class="knd-item${n.lue?'':' unread'}" data-id="${n.id}">
          <div class="knd-icon" style="color:${colors[n.type]||'#2563eb'}"><span class="material-icons">${icons[n.type]||'info'}</span></div>
          <div class="knd-content"><div class="knd-title">${this._esc(n.titre)}</div><div class="knd-msg">${this._esc(n.message)}</div><div class="knd-time">${this._ago(n.created_at)}</div></div>
        </div>`;
      }).join('');
      body.querySelectorAll('.knd-item').forEach(el => {
        el.addEventListener('click', () => this._markRead(parseInt(el.dataset.id)));
      });
    } catch(e) {}
  },

  async _markRead(id) {
    try {
      await fetch(KLINIK_API + 'notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'markRead', id}) });
      this.fetchCount(); this._fetchList();
    } catch(e) {}
  },

  async _markAllRead() {
    try {
      await fetch(KLINIK_API + 'notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'markAllRead'}) });
      this.fetchCount(); this._fetchList();
    } catch(e) {}
  },

  _ago(d) { const s=(Date.now()-new Date(d).getTime())/1000; if(s<60)return"À l'instant"; if(s<3600)return Math.floor(s/60)+' min'; if(s<86400)return Math.floor(s/3600)+' h'; return Math.floor(s/86400)+' j'; },
  _esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; },
  stop() { if(this._interval)clearInterval(this._interval); }
};

/* ══════════════════════════════════════
   MESSAGES — Auto-init system
══════════════════════════════════════ */
const KlinikMessages = {
  _interval: null, _badgeEl: null, _btnEl: null, _dropdownEl: null,

  init() {
    this._btnEl = this._findIconBtn('mail_outline') || this._findIconBtn('mail') || this._findIconBtn('chat');
    if (!this._btnEl) return;
    this._btnEl.style.position = 'relative';
    this._btnEl.style.cursor = 'pointer';

    // Ensure badge
    this._badgeEl = this._btnEl.querySelector('.msg-badge, .notif-badge');
    if (!this._badgeEl) {
      this._badgeEl = document.createElement('span');
      this._badgeEl.className = 'notif-badge msg-badge';
      this._badgeEl.style.display = 'none';
      this._btnEl.appendChild(this._badgeEl);
    }
    this._badgeEl.style.display = 'none';

    // Create dropdown
    this._createDropdown();

    this._btnEl.addEventListener('click', (e) => { e.stopPropagation(); this._toggleDropdown(); });

    this.fetchCount();
    this._interval = setInterval(() => this.fetchCount(), 10000);
  },

  _findIconBtn(iconName) {
    const icons = document.querySelectorAll('.topbar-icon-btn .material-icons');
    for (const ic of icons) { if (ic.textContent.trim() === iconName) return ic.closest('.topbar-icon-btn'); }
    return null;
  },

  _createDropdown() {
    const dd = document.createElement('div');
    dd.className = 'klinik-msg-dropdown';
    dd.innerHTML = '<div class="knd-header"><span>Messages</span></div><div class="knd-body"><p class="knd-empty">Chargement...</p></div>';
    dd.addEventListener('click', e => e.stopPropagation());
    this._btnEl.appendChild(dd);
    this._dropdownEl = dd;
  },

  async fetchCount() {
    try {
      const r = await fetch(KLINIK_API + 'messages.php?action=count');
      const d = await r.json();
      if (d.success) this._updateBadge(d.count);
    } catch(e) {}
  },

  _updateBadge(count) {
    if (!this._badgeEl) return;
    if (count > 0) { this._badgeEl.textContent = count > 99 ? '99+' : count; this._badgeEl.style.display = 'flex'; }
    else { this._badgeEl.style.display = 'none'; }
  },

  async _toggleDropdown() {
    if (!this._dropdownEl) return;
    const open = this._dropdownEl.classList.toggle('open');
    if (KlinikNotifications._dropdownEl) KlinikNotifications._dropdownEl.classList.remove('open');
    if (open) await this._fetchInbox();
  },

  async _fetchInbox() {
    try {
      const r = await fetch(KLINIK_API + 'messages.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'inbox'}) });
      const d = await r.json();
      const body = this._dropdownEl.querySelector('.knd-body');
      if (!d.success || !d.messages || d.messages.length === 0) {
        body.innerHTML = '<p class="knd-empty">Aucun message</p>'; return;
      }
      body.innerHTML = d.messages.slice(0, 8).map(m => {
        const initials = ((m.exp_prenom?.[0]||'')+(m.exp_nom?.[0]||'')).toUpperCase();
        return `<div class="knd-item${m.lu?'':' unread'}" data-id="${m.id}">
          <div class="knd-avatar">${initials}</div>
          <div class="knd-content"><div class="knd-title">${this._esc(m.exp_prenom+' '+m.exp_nom)}</div><div class="knd-msg">${this._esc(m.contenu).substring(0,60)}${m.contenu.length>60?'...':''}</div><div class="knd-time">${KlinikNotifications._ago(m.created_at)}</div></div>
        </div>`;
      }).join('');
      body.querySelectorAll('.knd-item').forEach(el => {
        el.addEventListener('click', async () => {
          const id = parseInt(el.dataset.id);
          await fetch(KLINIK_API + 'messages.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'markRead', id}) });
          el.classList.remove('unread');
          this.fetchCount();
        });
      });
    } catch(e) {}
  },

  _esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; },
  stop() { if(this._interval)clearInterval(this._interval); }
};

/* ── Inject dropdown styles + spin animation ── */
(function(){
  const s = document.createElement('style');
  s.textContent = `
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes kndFadeIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.klinik-notif-dropdown,.klinik-msg-dropdown{position:absolute;top:calc(100% + 10px);right:-10px;width:360px;background:#fff;border-radius:14px;box-shadow:0 12px 48px rgba(0,0,0,.18);border:1px solid #eef0f6;z-index:9999;display:none;overflow:hidden}
.klinik-notif-dropdown.open,.klinik-msg-dropdown.open{display:block;animation:kndFadeIn .2s ease}
.knd-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #eef0f6;font-weight:700;font-size:.9rem;color:#1a3a6e}
.knd-mark-all{background:none;border:none;color:#2563eb;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:background .15s}
.knd-mark-all:hover{background:#e8f0fe}
.knd-body{max-height:380px;overflow-y:auto}
.knd-empty{padding:28px;text-align:center;color:#9ca3af;font-size:.85rem}
.knd-item{display:flex;gap:12px;padding:14px 18px;border-bottom:1px solid #f5f7fb;cursor:pointer;transition:background .12s}
.knd-item:hover{background:#f8faff}
.knd-item.unread{background:#f0f5ff}
.knd-item:last-child{border-bottom:none}
.knd-icon .material-icons{font-size:22px;margin-top:2px}
.knd-avatar{width:36px;height:36px;border-radius:50%;background:#1e3a5f;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;font-family:'Oswald',sans-serif}
.knd-content{flex:1;min-width:0}
.knd-title{font-size:.84rem;font-weight:600;color:#1a3a6e}
.knd-msg{font-size:.78rem;color:#6b7280;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.knd-time{font-size:.7rem;color:#9ca3af;margin-top:4px}
`;
  document.head.appendChild(s);
})();

/* ══════════════════════════════════════
   AUTO-INIT on DOMContentLoaded
   Works on ALL pages automatically
══════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  // Only init if we have a topbar (= user is logged in on a dashboard/module page)
  if (document.querySelector('.topbar-right')) {
    KlinikNotifications.init();
    KlinikMessages.init();
  }
});
