// ============================================================
// assets/js/modules/components.js  –  Shared UI components
// (header, footer, ticker, mobile menu, search)
// ============================================================
import { api, getAppBase } from './api.js';
import { esc } from './utils.js';

// ── Cached site settings ───────────────────────────────────────
let _siteSettings = null;

export async function getSiteSettings() {
  if (_siteSettings !== null) return _siteSettings;
  try {
    const res = await api.settings();
    _siteSettings = res.data ?? {};
  } catch {
    _siteSettings = {};
  }
  return _siteSettings;
}

// ── Inject header ──────────────────────────────────────────────
export async function renderHeader(activePage = '') {
  const settings = await getSiteSettings();
  const siteName = 'DK';
  const siteTagline = settings.site_tagline || '';
  const base = getAppBase();
  const navLinks = [
    { href: `${base}/`, label: 'DK', logo: `${base}/assets/images/logo-dk.svg`, home: true },
    { href: `${base}/pages/latest.html`,   label: 'Latest News' },
    { href: `${base}/pages/trending.html`, label: 'Trending' },
    { href: `${base}/pages/politics.html`, label: 'Politics' },
    { href: `${base}/pages/documentary.html`, label: 'DK' },
    { href: `${base}/pages/reports.html`,     label: 'Reports' },
  ];

  const currentPath = window.location.pathname.replace(/\/$/, '') || '/';

  const navHtml = navLinks.map(link => {
    const linkPath = link.href.replace(/\/$/, '') || '/';
    const isActive = link.label.toLowerCase() === activePage.toLowerCase()
                || (currentPath === linkPath)
                || (activePage === '' && (currentPath === base || currentPath === `${base}/index.html`));
    const logoImg = link.logo ? `<img src="${link.logo}" alt="${link.label} logo" class="nav-logo-img">` : '';
    return `<a href="${link.href}" class="nav-link${isActive ? ' active' : ''}${link.home ? ' nav-link-home' : ''}">${logoImg}${link.label}</a>`;
  }).join('');

  const mobileNavHtml = navLinks.map(link =>
    `<a href="${link.href}" class="mobile-nav-link${link.home ? ' mobile-nav-link-home' : ''}">${link.logo ? `<img src="${link.logo}" alt="${link.label} logo" class="nav-logo-img">` : ''}${link.label}</a>`
  ).join('');

  const header = document.getElementById('site-header');
  if (!header) return;

  header.innerHTML = `
    <div class="container header-inner">
     <nav class="primary-nav" aria-label="Main navigation">${navHtml}</nav>
     <div class="header-right">
        <button class="search-toggle" id="search-toggle" aria-label="Search">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>
        <button class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <nav class="mobile-menu" id="mobile-menu" aria-label="Mobile navigation">
      ${mobileNavHtml}
    </nav>

    <!-- Search overlay -->
    <div class="search-overlay" id="search-overlay" role="dialog" aria-label="Site search" aria-modal="true">
      <div class="search-box">
        <form id="search-form" action="${base}/pages/search.html" method="get">
          <input type="search" name="q" id="search-input" class="search-input"
                 placeholder="Search ${esc(siteName)}…" autocomplete="off" aria-label="Search query">
          <button type="submit" class="search-submit" aria-label="Submit search">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </button>
        </form>
      </div>
      <button class="search-close" id="search-close" aria-label="Close search">✕</button>
    </div>
  `;

  // Scroll shadow
  const scrollHandler = () => header.classList.toggle('scrolled', window.scrollY > 10);
  window.addEventListener('scroll', scrollHandler, { passive: true });
  scrollHandler();

  // Hamburger
  const hamburger   = document.getElementById('hamburger');
  const mobileMenu  = document.getElementById('mobile-menu');
  hamburger?.addEventListener('click', () => {
    const open = hamburger.classList.toggle('open');
    mobileMenu.classList.toggle('open', open);
    hamburger.setAttribute('aria-expanded', open);
    document.body.style.overflow = open ? 'hidden' : '';
  });

  // Search
  const searchToggle  = document.getElementById('search-toggle');
  const searchOverlay = document.getElementById('search-overlay');
  const searchClose   = document.getElementById('search-close');
  const searchInput   = document.getElementById('search-input');

  function openSearch() {
    searchOverlay.classList.add('open');
    searchInput?.focus();
    document.body.style.overflow = 'hidden';
  }
  function closeSearch() {
    searchOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  searchToggle?.addEventListener('click', openSearch);
  searchClose?.addEventListener('click', closeSearch);
  searchOverlay?.addEventListener('click', e => { if (e.target === searchOverlay) closeSearch(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSearch(); });
}

// ── Render footer ──────────────────────────────────────────────
export async function renderFooter() {
  const settings = await getSiteSettings();
  const siteName  = 'DK';
  const aboutText = settings.footer_about || 'Independent journalism from the heart of Kenya. Delivering trusted news, political analysis, and documentary stories.';
  const twitter   = settings.twitter_url || '#';
  const facebook  = settings.facebook_url || '#';
  const youtube   = settings.youtube_url || '#';
  const instagram = settings.instagram_url || '#';

  const footer = document.getElementById('site-footer');
  if (!footer) return;

  const base = getAppBase();
  const year = new Date().getFullYear();

  footer.innerHTML = `
    <div class="container">
      <div class="footer-main">
        <div class="footer-brand">
           <a href="${base}/" class="footer-logo" style="margin-bottom:1rem">
             <img src="${base}/assets/images/logo-dk.svg" alt="${esc(siteName)} logo" class="footer-logo-img">
             <span class="footer-logo-text">${esc(siteName)}</span>
           </a>
          <p class="footer-tagline">${esc(aboutText)}</p>
          <div class="social-links">
            <a href="${esc(twitter)}" class="social-link" aria-label="Twitter" id="ft-twitter" target="_blank" rel="noopener">𝕏</a>
            <a href="${esc(facebook)}" class="social-link" aria-label="Facebook" id="ft-facebook" target="_blank" rel="noopener">f</a>
            <a href="${esc(youtube)}" class="social-link" aria-label="YouTube" id="ft-youtube" target="_blank" rel="noopener">▶</a>
            <a href="${esc(instagram)}" class="social-link" aria-label="Instagram" id="ft-instagram" target="_blank" rel="noopener">◈</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Sections</h4>
          <div class="footer-links">
            <a href="${base}/pages/latest.html" class="footer-link">Latest News</a>
            <a href="${base}/pages/trending.html" class="footer-link">Trending</a>
            <a href="${base}/pages/politics.html" class="footer-link">Politics</a>
            <a href="${base}/pages/documentary.html" class="footer-link">Documentary</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Company</h4>
          <div class="footer-links">
            <a href="${base}/pages/about.html" class="footer-link">About Us</a>
            <a href="${base}/pages/contact.html" class="footer-link">Contact</a>
            <a href="${base}/pages/editorial-policy.html" class="footer-link">Editorial Policy</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Legal</h4>
          <div class="footer-links">
            <a href="${base}/pages/privacy.html" class="footer-link">Privacy Policy</a>
            <a href="${base}/pages/terms.html" class="footer-link">Terms of Service</a>
            <a href="${base}/pages/copyright.html" class="footer-link">Copyright</a>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <p class="footer-copy">© ${year} ${esc(siteName)}. All rights reserved.</p>
        <div class="footer-bottom-links">
          <a href="${base}/pages/privacy.html">Privacy</a>
          <a href="${base}/pages/terms.html">Terms</a>
          <a href="${base}/admin/" class="btn btn-primary btn-sm">Admin</a>
        </div>
      </div>
    </div>
  `;
}

// ── Breaking news ticker ───────────────────────────────────────
export async function renderTicker(containerId = 'ticker') {
  const el = document.getElementById(containerId);
  if (!el) return;

  const base = getAppBase();

  try {
    const res   = await api.breaking();
    const items = res.data ?? [];
    if (!items.length) { el.style.display = 'none'; return; }

    const itemsHtml = [...items, ...items].map(item =>
      `<a href="${base}/article.html?slug=${esc(item.slug)}" class="ticker-item">${esc(item.title)}</a>`
    ).join('');

    el.innerHTML = `
      <div class="container ticker-inner">
        <span class="ticker-label">Breaking</span>
        <div class="ticker-track" aria-live="polite" aria-label="Breaking news">
          <div class="ticker-items">${itemsHtml}</div>
        </div>
      </div>
    `;
  } catch {
    el.style.display = 'none';
  }
}
