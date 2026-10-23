// ============================================================
// admin/assets/js/admin-core.js  –  Shared admin utilities
// ============================================================

export function getAppBase() {
  if (typeof window.HABARI_CONFIG?.appBase === 'string') {
    return window.HABARI_CONFIG.appBase.replace(/\/$/, '');
  }
  const pathname = window.location.pathname;
  const match = pathname.match(/^(.*?)\/admin/i);
  return (match && match[1]) ? match[1].replace(/\/$/, '') : '';
}

function resolveApiBase() {
  const configured = window.HABARI_CONFIG?.apiBase?.replace(/\/$/, '');
  if (configured && configured !== '/api') return configured;
  const meta = document.querySelector('meta[name="api-base"]');
  if (meta?.content && meta.content !== '/api') return meta.content.replace(/\/$/, '');
  return `${getAppBase()}/api`;
}

const _apiBase = resolveApiBase();
let _csrfToken = sessionStorage.getItem('habari_csrf') || '';
let _authToken = sessionStorage.getItem('habari_token') || '';

export function setAuthToken(token) {
  _authToken = token || '';
  if (_authToken) sessionStorage.setItem('habari_token', _authToken);
  else sessionStorage.removeItem('habari_token');
}

export function setCsrfToken(csrf) {
  _csrfToken = csrf || '';
  if (_csrfToken) {
    sessionStorage.setItem('habari_csrf', _csrfToken);
    window.HABARI_CSRF = _csrfToken;
  } else {
    sessionStorage.removeItem('habari_csrf');
  }
}

// ── Admin API fetch ────────────────────────────────────────────
export async function adminFetch(path, options = {}) {
  const url = `${_apiBase}${path}`;
  const isFormData = (typeof FormData !== 'undefined' && options.body instanceof FormData);
  const headers = isFormData
    ? { ...(options.headers || {}) }
    : { 'Content-Type': 'application/json', ...(options.headers || {}) };

  const currentToken = _authToken || sessionStorage.getItem('habari_token') || '';
  if (currentToken) {
    headers['Authorization'] = `Bearer ${currentToken}`;
  }

  const config = {
    credentials: 'include',
    headers,
    ...options,
  };
  const currentCsrf = _csrfToken || sessionStorage.getItem('habari_csrf') || '';
  if (currentCsrf && ['POST','PUT','DELETE','PATCH'].includes((config.method||'GET').toUpperCase())) {
    config.headers['X-CSRF-Token'] = currentCsrf;
  }
  const res  = await fetch(url, config);
  const json = await res.json().catch(() => ({ success: false, message: 'Invalid response from server' }));
  if (!res.ok) throw Object.assign(new Error(json.message || `HTTP ${res.status}`), { status: res.status, data: json });
  return json;
}

// ── Auth guard ─────────────────────────────────────────────────
export async function requireAuth() {
  try {
    const res = await adminFetch('/admin/me');
    if (res.data?.csrf_token) {
      setCsrfToken(res.data.csrf_token);
    }
    if (res.data?.token) {
      setAuthToken(res.data.token);
    }
    return res.data;
  } catch (err) {
    setAuthToken('');
    setCsrfToken('');
    window.location.replace(`${getAppBase()}/admin/index.html?unauth=1`);
    throw new Error('Unauthenticated');
  }
}

// ── Logout ─────────────────────────────────────────────────────
export async function logout() {
  try { await adminFetch('/admin/logout', { method: 'POST', body: '{}' }); } catch {}
  setAuthToken('');
  setCsrfToken('');
  sessionStorage.clear();
  window.location.replace(`${getAppBase()}/admin/index.html`);
}

// ── Toast ──────────────────────────────────────────────────────
export function toast(message, type = 'info', ms = 4000) {
  let c = document.getElementById('toast-container');
  if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  c.appendChild(el);
  setTimeout(() => el.remove(), ms);
}

// ── Escape HTML ─────────────────────────────────────────────────
export function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Date format ─────────────────────────────────────────────────
const dtFmt = new Intl.DateTimeFormat('en-KE', { timeZone:'Africa/Nairobi', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
export function fmtDate(s) {
  if (!s) return '—';
  try { return dtFmt.format(new Date(s)); } catch { return s; }
}

// ── Status badge ───────────────────────────────────────────────
export function statusBadge(s) {
  return `<span class="status-badge ${esc(s)}">${esc(s)}</span>`;
}

// ── Pagination ─────────────────────────────────────────────────
export function renderPagination(container, meta, callback) {
  if (!meta || meta.total_pages <= 1) { container.innerHTML = ''; return; }
  const { page, total_pages } = meta;
  const show = new Set([1, total_pages, page, page-1, page+1].filter(p => p>=1 && p<=total_pages));
  const sorted = [...show].sort((a,b)=>a-b);
  let html = '<div class="pagination">';
  html += `<button class="page-btn" data-p="${page-1}" ${page===1?'disabled':''}>‹</button>`;
  let prev = null;
  for (const p of sorted) {
    if (prev && p-prev>1) html += '<span class="page-btn" style="cursor:default">…</span>';
    html += `<button class="page-btn ${p===page?'active':''}" data-p="${p}">${p}</button>`;
    prev = p;
  }
  html += `<button class="page-btn" data-p="${page+1}" ${page===total_pages?'disabled':''}>›</button>`;
  html += '</div>';
  container.innerHTML = html;
  container.querySelectorAll('.page-btn[data-p]').forEach(btn => {
    btn.addEventListener('click', () => { const p=parseInt(btn.dataset.p); if(!isNaN(p)&&p>=1&&p<=total_pages) callback(p); });
  });
}

// ── Confirm dialog ──────────────────────────────────────────────
export function confirm(msg) {
  return window.confirm(msg);
}

// ── Sidebar nav ─────────────────────────────────────────────────
export function initSidebar(user) {
  const current = window.location.pathname;

  // Highlight active sidebar item
  document.querySelectorAll('.sidebar-link').forEach(el => {
    const rawHref = el.getAttribute('href') || '';
    const cleanHref = rawHref.replace(/^\.\//, '').replace(/^\/admin\//, '');
    const isActive = cleanHref && current.endsWith(cleanHref);
    el.classList.toggle('active', !!isActive);
  });

  if (user) {
    const nameEl = document.getElementById('sidebar-user-name');
    const roleEl = document.getElementById('sidebar-user-role');
    const avEl   = document.getElementById('sidebar-avatar');
    if (nameEl) nameEl.textContent = user.display_name || user.username;
    if (roleEl) roleEl.textContent = user.role;
    if (avEl)   avEl.textContent   = (user.display_name || user.username || '?')[0].toUpperCase();
  }

  // Mobile sidebar toggle
  const toggle  = document.getElementById('sidebar-toggle');
  const sidebar = document.getElementById('admin-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  toggle?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
  });
  overlay?.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });

  // Logout
  document.getElementById('logout-btn')?.addEventListener('click', logout);
}

// ── Slug from title ─────────────────────────────────────────────
export function slugify(str) {
  return str.toLowerCase().trim().replace(/[^\w\s-]/g,'').replace(/[\s_-]+/g,'-').replace(/^-+|-+$/g,'');
}
