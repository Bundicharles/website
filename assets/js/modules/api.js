// ============================================================
// assets/js/modules/api.js  –  API client (singleton)
// ============================================================

export function getAppBase() {
  if (typeof window.HABARI_CONFIG?.appBase === 'string') {
    return window.HABARI_CONFIG.appBase.replace(/\/$/, '');
  }
  const pathname = window.location.pathname;
  const match = pathname.match(/^(.*?)\/(?:pages|admin|article\.html|404\.html|index\.html|$)/i);
  return (match && match[1]) ? match[1].replace(/\/$/, '') : '';
}

/**
 * Reads the API base URL from:
 *   1. window.HABARI_CONFIG.apiBase  (set inline in HTML)
 *   2. meta tag <meta name="api-base" content="...">
 *   3. Dynamic resolution based on app directory in web root
 */
function resolveApiBase() {
  const configured = window.HABARI_CONFIG?.apiBase?.replace(/\/$/, '');
  if (configured && configured !== '/api') return configured;
  const meta = document.querySelector('meta[name="api-base"]');
  if (meta?.content && meta.content !== '/api') return meta.content.replace(/\/$/, '');
  return `${getAppBase()}/api`;
}

const API_BASE = resolveApiBase();

export function getApiBase() {
  return API_BASE;
}

class ApiError extends Error {
  constructor(message, status, data = null) {
    super(message);
    this.status = status;
    this.data   = data;
  }
}

async function apiFetch(path, options = {}) {
  const url = `${API_BASE}${path}`;
  const defaults = {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
  };
  const config = {
    ...defaults,
    ...options,
    headers: { ...defaults.headers, ...(options.headers || {}) },
  };

  // Attach CSRF token for mutating requests
  const csrfToken = window.HABARI_CSRF || sessionStorage.getItem('habari_csrf');
  if (csrfToken && ['POST','PUT','DELETE','PATCH'].includes((config.method || 'GET').toUpperCase())) {
    config.headers['X-CSRF-Token'] = csrfToken;
  }

  try {
    const res  = await fetch(url, config);
    const json = await res.json().catch(() => ({ message: 'Invalid response from server', success: false }));
    if (!res.ok) throw new ApiError(json.message || `HTTP ${res.status}`, res.status, json);
    return json;
  } catch (err) {
    if (err instanceof ApiError) throw err;
    throw new ApiError('Network error. Please check your connection.', 0);
  }
}

// ── Public API ────────────────────────────────────────────────
export const api = {
  health:        ()               => apiFetch('/health'),
  articles:      (params = {})   => apiFetch('/articles?' + new URLSearchParams(params)),
  article:       (slug)          => apiFetch(`/articles/${encodeURIComponent(slug)}`),
  featured:      ()               => apiFetch('/featured'),
  trending:      ()               => apiFetch('/trending'),
  latest:        (params = {})   => apiFetch('/latest?' + new URLSearchParams(params)),
  breaking:      ()               => apiFetch('/breaking'),
  documentaries: (params = {})   => apiFetch('/documentaries?' + new URLSearchParams(params)),
  politics:      (params = {})   => apiFetch('/politics?' + new URLSearchParams(params)),
  reports:       (params = {})   => apiFetch('/reports?' + new URLSearchParams(params)),
  search:        (params = {})   => apiFetch('/search?' + new URLSearchParams(params)),
   categories:    ()               => apiFetch('/categories'),
   settings:      ()               => apiFetch('/settings'),

  // Contact
  contact: (data) => apiFetch('/contact', { method: 'POST', body: JSON.stringify(data) }),

  // Admin – auth
  adminLogin:  (data)  => apiFetch('/admin/login',  { method: 'POST', body: JSON.stringify(data) }),
  adminLogout: ()      => apiFetch('/admin/logout',  { method: 'POST', body: '{}' }),
  adminMe:     ()      => apiFetch('/admin/me'),

  // Admin – dashboard
  adminDashboard: () => apiFetch('/admin/dashboard'),

  // Admin – articles
  adminArticles:       (params = {}) => apiFetch('/admin/articles?' + new URLSearchParams(params)),
  adminArticle:        (id)          => apiFetch(`/admin/articles/${id}`),
  adminCreateArticle:  (data)        => apiFetch('/admin/articles',       { method: 'POST',   body: JSON.stringify(data) }),
  adminUpdateArticle:  (id, data)    => apiFetch(`/admin/articles/${id}`, { method: 'PUT',    body: JSON.stringify(data) }),
  adminDeleteArticle:  (id)          => apiFetch(`/admin/articles/${id}`, { method: 'DELETE' }),

  // Admin – categories
  adminCategories:      (params = {}) => apiFetch('/admin/categories?' + new URLSearchParams(params)),
  adminCreateCategory:  (data)        => apiFetch('/admin/categories',       { method: 'POST',   body: JSON.stringify(data) }),
  adminUpdateCategory:  (id, data)    => apiFetch(`/admin/categories/${id}`, { method: 'PUT',    body: JSON.stringify(data) }),
  adminDeleteCategory:  (id)          => apiFetch(`/admin/categories/${id}`, { method: 'DELETE' }),

  // Admin – media
  adminMedia:       (params = {}) => apiFetch('/admin/media?' + new URLSearchParams(params)),
  adminUploadMedia: (formData)    => apiFetch('/admin/media', {
    method:  'POST',
    body:    formData,
    headers: {},
  }),

  // Admin – settings
  adminSettings:       ()     => apiFetch('/admin/settings'),
  adminUpdateSettings: (data) => apiFetch('/admin/settings', { method: 'POST', body: JSON.stringify(data) }),

  // Admin – messages
  adminMessages:  ()   => apiFetch('/admin/messages'),
  adminMessage:   (id) => apiFetch(`/admin/messages/${id}`),
};

export { ApiError };
