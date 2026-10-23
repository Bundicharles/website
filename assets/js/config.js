// ============================================================
// assets/js/config.js  –  Global Environment Configuration
// ============================================================
//
// This file MUST be loaded (via <script src>) on every page BEFORE
// the ES module scripts. It sets window.HABARI_CONFIG which the
// API client (api.js / admin-core.js) reads to determine the backend URL.
//
// ── Deployment Method A: Vercel Reverse Proxy (RECOMMENDED) ─
//   Vercel's vercel.json rewrites /api/* → your Truehost backend.
//   Leave apiBase as '' (empty). The app auto-resolves to '/api',
//   which Vercel proxies. Zero CORS, zero cookie issues.
//
// ── Deployment Method B: Direct Cross-Domain ─
//   If you skip the Vercel proxy and call Truehost directly:
//   Set apiBase to your Truehost API root, e.g.:
//     'https://api.yourdomain.co.ke/api'
//   Then add your Vercel URL to ALLOWED_ORIGINS in config.production.php
//   on Truehost.
//
window.HABARI_CONFIG = window.HABARI_CONFIG || {
  apiBase: ''
};
