// ============================================================
// assets/js/modules/components.js  –  Shared UI components
// (footer, ticker)
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

// ── Breaking news ticker (static) ───────────────────────────────
export async function renderTicker(containerId = 'breaking-ticker') {
  const el = document.getElementById(containerId);
  if (!el) return;

  const base = getAppBase();

  try {
    const res   = await api.breaking();
    const items = res.data ?? [];
    if (!items.length) { el.style.display = 'none'; return; }

    const itemsHtml = [...items, ...items].map(item =>
      `<a href="${base}/article.html?slug=${esc(item.slug)}" class="ticker-item" aria-label="Breaking news: ${esc(item.title)}">${esc(item.title)}</a>`
    ).join('');

    el.innerHTML = `
      <div class="container ticker-inner">
        <span class="ticker-label" aria-hidden="true">BREAKING</span>
        <div class="ticker-track" role="list">
          <div class="ticker-items">${itemsHtml}</div>
        </div>
      </div>
    `;
  } catch {
    el.style.display = 'none';
  }
}
