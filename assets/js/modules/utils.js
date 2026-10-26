// ============================================================
// assets/js/modules/utils.js  –  Shared frontend utilities
// ============================================================
import { getAppBase, getApiBase } from './api.js';

export { getAppBase, getApiBase };

// ── Date formatting (Africa/Nairobi) ─────────────────────────
const dateFormatter = new Intl.DateTimeFormat('en-KE', {
  timeZone:    'Africa/Nairobi',
  year:        'numeric',
  month:       'long',
  day:         'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-KE', {
  timeZone:    'Africa/Nairobi',
  year:        'numeric',
  month:       'short',
  day:         'numeric',
  hour:        '2-digit',
  minute:      '2-digit',
});

const relativeFormatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export function formatDate(dateStr) {
  if (!dateStr) return '';
  try { return dateFormatter.format(new Date(dateStr)); }
  catch { return dateStr; }
}

export function formatDateTime(dateStr) {
  if (!dateStr) return '';
  try { return dateTimeFormatter.format(new Date(dateStr)); }
  catch { return dateStr; }
}

export function timeAgo(dateStr) {
  if (!dateStr) return '';
  try {
    const diff  = (new Date(dateStr).getTime() - Date.now()) / 1000;
    const abs   = Math.abs(diff);
    if (abs < 60)   return relativeFormatter.format(Math.round(diff), 'second');
    if (abs < 3600) return relativeFormatter.format(Math.round(diff / 60), 'minute');
    if (abs < 86400)return relativeFormatter.format(Math.round(diff / 3600), 'hour');
    if (abs < 2592000) return relativeFormatter.format(Math.round(diff / 86400), 'day');
    return formatDate(dateStr);
  } catch { return dateStr; }
}

// ── Image helpers ─────────────────────────────────────────────
export function imgSrc(path, fallback) {
  const base = getAppBase();
  const apiBase = getApiBase();
  const defaultFallback = `${base}/assets/images/placeholder.svg`;
  const fb = fallback || defaultFallback;
  if (!path) return fb;
  if (path.startsWith('http://') || path.startsWith('https://')) return path;

  // Handle uploaded images from backend
  if (path.startsWith('/api/uploads') || path.startsWith('/uploads')) {
    if (apiBase.startsWith('http://') || apiBase.startsWith('https://')) {
      const backendRoot = apiBase.replace(/\/api\/?$/, '');
      const cleanPath = path.startsWith('/api') ? path : `/api${path}`;
      return `${backendRoot}${cleanPath}`;
    }
    if (base && !path.startsWith(base + '/')) {
      return base + path;
    }
    return path;
  }

  if (path.startsWith('/')) {
    if (base && !path.startsWith(base + '/')) {
      return base + path;
    }
    return path;
  }
  return fb;
}

// ── YouTube embed URL ─────────────────────────────────────────
export function youtubeEmbedUrl(embedId) {
  if (!embedId) return null;
  return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(embedId)}?rel=0&modestbranding=1`;
}

// ── Parse a video URL from ANY source ────────────────────────────
// Supports YouTube (watch/short/embed), Vimeo, and direct file links
export function parseVideoUrl(url) {
  if (!url) return null;
  const u = url.trim();

  // YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, youtube.com/shorts/ID
  let m = u.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
  if (m) {
    return { type: 'embed', provider: 'youtube', embedUrl: `https://www.youtube-nocookie.com/embed/${m[1]}?rel=0&modestbranding=1` };
  }

  // Vimeo: vimeo.com/ID
  m = u.match(/vimeo\.com\/(\d+)/);
  if (m) {
    return { type: 'embed', provider: 'vimeo', embedUrl: `https://player.vimeo.com/video/${m[1]}` };
  }

  // Direct video file links (.mp4, .webm, .ogg, .mov, .mkv, .avi, .wmv)
  const extMatch = u.match(/\.(mp4|webm|ogg|ogv|mov|mkv|avi|wmv)(\?|#|$)/i);
  if (extMatch) {
    const mimeMap = { mp4:'video/mp4', webm:'video/webm', ogg:'video/ogg', ogv:'video/ogg', mov:'video/quicktime', mkv:'video/x-matroska' };
    return { type: 'direct', provider: 'direct', url: u, mimeType: mimeMap[extMatch[1].toLowerCase()] || '' };
  }

  return null;
}

// ── HTML escaping (for safe injection into innerHTML) ─────────
export function esc(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// ── Toast notifications ───────────────────────────────────────
export function toast(message, type = 'info', duration = 4000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  container.appendChild(el);
  setTimeout(() => el.remove(), duration);
}

// ── Slugify ───────────────────────────────────────────────────
export function slugify(str) {
  return str.toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// ── Debounce ──────────────────────────────────────────────────
export function debounce(fn, delay = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

// ── Query param helpers ───────────────────────────────────────
export function getParam(key) {
  return new URLSearchParams(window.location.search).get(key) ?? '';
}

export function setParam(key, value) {
  const params = new URLSearchParams(window.location.search);
  if (value) params.set(key, value); else params.delete(key);
  const newUrl = `${window.location.pathname}?${params}`;
  history.replaceState(null, '', newUrl);
}

// ── Skeleton builders ─────────────────────────────────────────
// ── Media helpers: silent video preview ──────────────────────────
export function getArticleVideo(article) {
  const sources = [
    article.featured_video ? imgSrc(article.featured_video) : null,
    article.video_url ? imgSrc(article.video_url) : null,
  ].filter(Boolean);

  for (const url of sources) {
    const parsed = parseVideoUrl(url);
    if (parsed) {
      if (parsed.type === 'direct') return parsed.url;
      if (parsed.type === 'embed') {
        return parsed.embedUrl + (parsed.embedUrl.includes('?') ? '&' : '?') + 'autoplay=1&mute=1';
      }
    }
  }
  return null;
}

export function isVideoContent(article) {
  return article.content_type === 'documentary' || !!article.featured_video || !!article.video_url;
}

export function cardMediaHtml(article, { preview = false } = {}) {
  const base = getAppBase();
  const thumb = imgSrc(article.featured_image);
  const title = esc(article.title);
  const videoUrl = preview ? getArticleVideo(article) : null;

  if (!videoUrl) {
    return `<img src="${thumb}" alt="${title}" loading="lazy" onerror="this.src='${base}/assets/images/placeholder.svg'">`;
  }

  const isDirect = videoUrl.match(/\.(mp4|webm|ogg|ogv|mov|mkv|avi|wmv)/i);
  if (isDirect) {
    return `<img src="${thumb}" alt="${title}" loading="lazy" onerror="this.src='${base}/assets/images/placeholder.svg'" class="media-img">
<video src="${esc(videoUrl)}" muted autoplay loop playsinline poster="${thumb}" class="media-video"></video>`;
  }

  return `<img src="${thumb}" alt="${title}" loading="lazy" onerror="this.src='${base}/assets/images/placeholder.svg'" class="media-img">
<iframe src="${esc(videoUrl)}" allow="autoplay; encrypted-media" class="media-video" title="${title}" loading="lazy"></iframe>`;
}

export function heroMediaHtml(article, { overlay = '' } = {}) {
  const base = getAppBase();
  const thumb = imgSrc(article.featured_image);
  const title = esc(article.title);
  const videoUrl = getArticleVideo(article);

  if (!videoUrl) {
    return `<div class="hero-card-img">
      <img src="${thumb}" alt="${title}" loading="eager" onerror="this.src='${base}/assets/images/placeholder.svg'">
      ${overlay}
    </div>`;
  }

  const isDirect = videoUrl.match(/\.(mp4|webm|ogg|ogv|mov|mkv|avi|wmv)/i);
  if (isDirect) {
    return `<div class="hero-card-img">
      <video src="${esc(videoUrl)}" muted autoplay loop playsinline poster="${thumb}" class="media-video-full"
             onerror="this.style.display='none'; document.querySelector('.media-poster').style.display='block'">
      </video>
      <img src="${thumb}" alt="${title}" loading="eager" class="media-poster"
           onerror="this.src='${base}/assets/images/placeholder.svg'" style="display:none">
      ${overlay}
    </div>`;
  }

  return `<div class="hero-card-img">
    <iframe src="${esc(videoUrl)}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture"
            loading="eager" title="${title}" class="media-video-full"></iframe>
    ${overlay}
  </div>`;
}

// ── Skeleton builders ─────────────────────────────────────────
export function skeletonCard() {
  return `<div class="skeleton-card card">
    <div class="skeleton skeleton-img card-img"></div>
    <div class="card-body">
      <div class="skeleton skeleton-line lg" style="margin:4px 0 8px 0;width:80%"></div>
      <div class="skeleton skeleton-line" style="margin:0 0 6px 0;width:95%"></div>
      <div class="skeleton skeleton-line sm" style="margin:0;width:50%"></div>
    </div>
  </div>`;
}

export function skeletonCards(n = 6) {
  return Array(n).fill(skeletonCard()).join('');
}

// ── Article card templates (Netflix Style) ─────────────────────
export function cardHtml(article) {
  const base  = getAppBase();
  const cat   = article.category_slug ? `<a href="${base}/pages/latest.html?category=${esc(article.category_slug)}" class="badge" style="background:${esc(article.category_color||'#C62828')}">${esc(article.category_name)}</a>` : '';
  const isVideo = !!article.featured_video;
  const slug  = esc(article.slug);
  const title = esc(article.title);
  const exerp = esc(article.excerpt || '');
  const date  = timeAgo(article.published_at);
  const isDoc = article.content_type === 'documentary' || !!article.video_url;
  const playBtn = isDoc || isVideo ? `
    <div class="netflix-play-btn" aria-label="${isVideo ? 'Watch video' : 'Play documentary'}">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><polygon points="6 3 20 12 6 21 6 3"/></svg>
    </div>` : '';
  const rankBadge = article.trending_rank ? `<span class="netflix-top-badge">#${article.trending_rank}</span>` : '';
  const media = cardMediaHtml(article, { preview: true });

  return `<article class="card">
    <a href="${base}/article.html?slug=${slug}" class="card-img" aria-label="${title}">
      ${media}
      <div class="card-img-overlay"></div>
      ${cat}
      ${rankBadge}
      ${playBtn}
    </a>
    <div class="card-body">
      <div class="card-meta">
        <time datetime="${esc(article.published_at)}">${date}</time>
        ${article.author_name ? `<span>· ${esc(article.author_name)}</span>` : ''}
      </div>
      <h3 class="card-title"><a href="${base}/article.html?slug=${slug}">${title}</a></h3>
      <p class="card-excerpt">${exerp}</p>
    </div>
  </article>`;
}

export function cardHHtml(article) {
  const base  = getAppBase();
  const slug  = esc(article.slug);
  const title = esc(article.title);
  const date  = timeAgo(article.published_at);
  const cat   = article.category_name ? `<span class="badge" style="background:${esc(article.category_color||'#C62828')};margin-bottom:4px">${esc(article.category_name)}</span>` : '';
  const isVideo = !!article.featured_video;
  const isDoc = article.content_type === 'documentary' || !!article.video_url;
  const playBtn = isDoc || isVideo ? `
    <div class="netflix-play-btn" aria-label="${isVideo ? 'Watch video' : 'Play documentary'}">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><polygon points="6 3 20 12 6 21 6 3"/></svg>
    </div>` : '';
  const media = cardMediaHtml(article, { preview: true });

  return `<article class="card-h">
    <a href="${base}/article.html?slug=${slug}" class="card-h-img">
      ${media}
      ${playBtn}
    </a>
    <div class="card-h-body">
      ${cat}
      <h4 class="card-h-title"><a href="${base}/article.html?slug=${slug}">${title}</a></h4>
      <time style="font-size:0.75rem;color:var(--text-muted)">${date}</time>
    </div>
  </article>`;
}

// ── Pagination ────────────────────────────────────────────────
export function paginationHtml(meta, onPage) {
  if (!meta || meta.total_pages <= 1) return '';
  const { page, total_pages } = meta;

  const show = new Set();
  [1, total_pages, page, page - 1, page + 1].forEach(p => {
    if (p >= 1 && p <= total_pages) show.add(p);
  });
  const sorted = [...show].sort((a, b) => a - b);

  let html = '<div class="pagination">';
  html += `<button class="pagination-btn" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''} aria-label="Previous page">‹</button>`;

  let prev = null;
  for (const p of sorted) {
    if (prev && p - prev > 1) html += '<span class="pagination-btn" style="cursor:default">…</span>';
    html += `<button class="pagination-btn ${p === page ? 'active' : ''}" data-page="${p}">${p}</button>`;
    prev = p;
  }

  html += `<button class="pagination-btn" data-page="${page + 1}" ${page === total_pages ? 'disabled' : ''} aria-label="Next page">›</button>`;
  html += '</div>';

  setTimeout(() => {
    document.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
      btn.addEventListener('click', () => {
        const p = parseInt(btn.dataset.page);
        if (!isNaN(p) && p >= 1 && p <= total_pages) onPage(p);
      });
    });
  }, 0);

  return html;
}

// ── Empty / error state ───────────────────────────────────────
export function emptyState(message = 'No content found.') {
  return `<div class="state-empty">
    <svg width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-3-3v6m-7 3h14a2 2 0 002-2V8l-6-6H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
    </svg>
    <p>${esc(message)}</p>
  </div>`;
}

export function errorState(message = 'Failed to load content. Please try again.') {
  return `<div class="state-error">
    <svg width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <p>${esc(message)}</p>
  </div>`;
}

// ── SEO helpers ───────────────────────────────────────────────
export function setPageMeta({ title, description, image, canonical }) {
  const siteName = 'DK';
  if (title) {
    document.title = `${title} | ${siteName}`;
    document.querySelector('meta[property="og:title"]')?.setAttribute('content', `${title} | ${siteName}`);
  }
  if (description) {
    document.querySelector('meta[name="description"]')?.setAttribute('content', description);
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', description);
  }
  if (image) {
    document.querySelector('meta[property="og:image"]')?.setAttribute('content', image);
  }
  if (canonical) {
    let link = document.querySelector('link[rel="canonical"]');
    if (!link) { link = document.createElement('link'); link.rel = 'canonical'; document.head.appendChild(link); }
    link.href = canonical;
    document.querySelector('meta[property="og:url"]')?.setAttribute('content', canonical);
  }
}
