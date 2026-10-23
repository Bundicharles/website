# Habari Hub – Deployment & Hosting Tutorial

This guide walks you through deploying **Habari Hub** with:

1. **Frontend** (static HTML, CSS, ES modules) on **Vercel** — global Edge CDN, free SSL, instant cache invalidation.
2. **Backend** (PHP REST API + MySQL) on **Truehost Kenya** — cPanel, Apache/LiteSpeed, PHP 8.x, MySQL/MariaDB.

---

## Architecture Overview

```
                         +--------------------------+
  [Browser]  ───────►   |   Vercel Edge CDN        |
                         |  - index.html, article.html|
                         |  - pages/*.html          |
                         |  - admin/*.html          |
                         |  - assets/css/, js/, img |
                         |  - vercel.json rewrites   |
                         +──────────┬──────────────+
                                    │ /api/* (rewrite)
                                    ▼
                         +--------------------------+
                         |  Truehost Kenya (cPanel)  |
                         |  - PHP 8.x REST API       |
                         |  - MySQL / MariaDB        |
                         |  - Media uploads          |
                         |  - Session storage        |
                         +--------------------------+
```

### How the API proxy works (recommended)

`vercel.json` contains a rewrite rule:

```json
{
  "rewrites": [
    {
      "source": "/api/(.*)",
      "destination": "https://api.yourdomain.co.ke/api/$1"
    }
  ]
}
```

Every request from the Vercel frontend to `/api/*` is **transparently proxied** to your Truehost backend. The browser sees same-origin requests, so:

- **No CORS headaches** — cookies, CSRF tokens, and Authorization headers work natively.
- **Session cookies** persist correctly because they appear first-party to the browser.
- **Uploaded images** at `/api/uploads/images/xxx.jpg` are served through the same proxy.

---

## Pre-Deployment Checklist (Codebase Prep)

Before deploying, verify the following fixes are in place:

| File | Status | Notes |
|------|--------|-------|
| `index.html` at project root | Ready | This is the Vercel entry point |
| `config.js` loaded on all pages | Ready | `<script src="...config.js">` placed before module scripts on every HTML page |
| `vercel.json` | Ready | Proxy rewrite in place (update the destination URL) |
| `.gitignore` | Ready | Excludes `config.local.php`, `config.production.php`, `api/setup/`, uploads |
| Admin sidebar links | Fixed | All admin pages now have consistent sidebar (Dashboard, Articles, New Article, Categories, Media, Settings, Messages) |

---

## Part 1: Truehost Backend Setup

### Step 1 — Verify PHP version

1. Log in to your **Truehost cPanel**.
2. Go to **Software → Select PHP Version**.
3. Ensure **PHP 8.1+** is selected (8.2 or 8.3 recommended).
4. Set these PHP values:
   - `upload_max_filesize` = `20M`
   - `post_max_size` = `25M`
   - `max_execution_time` = `60`
   - `max_input_vars` = `3000`

### Step 2 — Create the MySQL database & user

1. In cPanel, click **MySQL Databases**.
2. Create a database: `yourcpaneluser_habarihub`
3. Create a user: `yourcpaneluser_habari` with a strong password. **Save this password.**
4. Add the user to the database with **ALL PRIVILEGES**.

### Step 3 — Import the database schema & seed data

1. In cPanel, open **phpMyAdmin**.
2. Select your new database in the left sidebar.
3. Click **Import** → choose file → select `database/schema.sql` → **Go**.
4. Click **Import** again → choose file → select `database/seed.sql` → **Go**.

### Step 4 — Upload the API files

**Option A — Subdomain (Recommended):**
1. In cPanel, go to **Subdomains**.
2. Create `api.yourdomain.co.ke` pointing to `public_html/api` (or a dedicated folder).
3. Upload the `api/` directory to `public_html/api/` via **File Manager** or **FTP** (FileZilla).

**Option B — Subfolder:**
Upload the `api/` directory to `public_html/api/`.

> **Important:** Make sure `api/.htaccess` is uploaded — it handles URL rewriting and CORS headers.

### Step 5 — Create the production config

1. In `api/config/`, copy `config.production.example.php` to `config.production.php`.
2. Edit `config.production.php` with your Truehost credentials:

```php
<?php
// api/config/config.production.php
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'yourcpaneluser_habarihub');
if (!defined('DB_USER'))    define('DB_USER',    'yourcpaneluser_habari');
if (!defined('DB_PASS'))    define('DB_PASS',    'YourTruehostPasswordHere');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('APP_ENV'))    define('APP_ENV',    'production');
if (!defined('APP_DEBUG'))  define('APP_DEBUG',  false);

// Add your Vercel domain(s) here for CORS (only needed if NOT using the Vercel proxy)
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', 'https://habarihub.vercel.app,https://yourdomain.co.ke');
}
```

3. **Set the APP_ENV environment variable** (recommended):
   - In cPanel → **Software → PHP Environment Variables**
   - Add `APP_ENV` = `production`

   This ensures `config.local.php` (if accidentally uploaded) is **never** loaded in production.

### Step 6 — Set file permissions

| Path | Permissions | Why |
|------|-------------|-----|
| `api/*.php` | `644` | Readable by web server, not world-writable |
| `api/` directories | `755` | Executable directory |
| `api/uploads/` | `755` | Writable by PHP for uploads |
| `api/sessions/` | `755` | Writable by PHP for sessions |
| `api/.htaccess` | `644` | Apache config protection |

### Step 7 — Test the API

Visit:

```
https://api.yourdomain.co.ke/api/health
```

You should see:

```json
{
  "success": true,
  "message": "OK",
  "data": { "status": "ok", "db": "connected", "version": "1.0.0", "time": "..." }
}
```

If `db` shows `"error"`, check your `config.production.php` credentials and MySQL status.

### Step 8 — Create your first admin user

1. Set an environment variable `SETUP_TOKEN` with a random value (e.g. via cPanel PHP Environment Variables).
2. Open `api/setup/create_admin.php` and find the token check, set the token.
3. Navigate to: `https://api.yourdomain.co.ke/api/setup/create_admin.php?token=YOUR_TOKEN`
4. Enter username `admin` and a strong password.
5. **Delete `api/setup/` directory entirely** after creating the admin.

> **Default credentials (local only):** Username: `admin`, Password: `Admin@2024`

### Step 9 — Test admin login

Visit `https://yourdomain.co.ke/admin/` (Vercel) or use the Vercel preview URL.

---

## Part 2: Vercel Frontend Deployment

### Step 1 — Push to GitHub

1. Initialize a git repo (if not already done):

```bash
git init
git add .
git commit -m "Ready for deployment"
```

2. Create a new repository on **GitHub** and push:

```bash
git remote add origin git@github.com:yourusername/habarihub.git
git branch -M main
git push -u origin main
```

> **Verify your `.gitignore` excludes these:**
> - `api/config/config.local.php`
> - `api/config/config.production.php`
> - `api/setup/`
> - `api/uploads/images/*` (except `.gitkeep`)
> - `api/sessions/sess_*` (session files)

### Step 2 — Import to Vercel

1. Go to [vercel.com](https://vercel.com) and sign in with GitHub.
2. Click **Add New Project** → **Import**.
3. Select your `habarihub` repository.
4. In **Framework Preset**, select **Other**.
5. **Root Directory**: leave as `./` (project root).
6. **Build Command** and **Output Directory**: leave empty (static site, no build step).
7. Click **Deploy**.

### Step 3 — Update the Vercel rewrite proxy

After the first deploy, edit `vercel.json` and replace the placeholder with your Truehost API URL:

```json
{
  "rewrites": [
    {
      "source": "/api/(.*)",
      "destination": "https://api.yourdomain.co.ke/api/$1"
    }
  ]
}
```

Commit and push. Vercel will redeploy automatically.

### Step 4 — Add your custom domain (optional)

1. In the Vercel dashboard, select your project → **Settings** → **Domains**.
2. Enter your domain (e.g., `habarihub.co.ke`) and click **Add**.
3. Follow the DNS instructions — update your domain's **Nameservers** at Truehost/Registrar to:

```
ns1.vercel.com
ns2.vercel.com
```

Or add a **CNAME** (for `www`) and **ALIAS/ANAME** (for root) record.
Vercel provides automatic SSL/TLS.

### Step 5 — Verify everything works

| URL | Expected Result |
|-----|-----------------|
| `https://habarihub.co.ke/` | Homepage loads with featured, latest, trending, documentary, politics |
| `https://habarihub.co.ke/article.html?slug=your-slug` | Article detail page |
| `https://habarihub.co.ke/pages/latest.html` | Latest news listing |
| `https://habarihub.co.ke/pages/search.html?q=kenya` | Search results |
| `https://habarihub.co.ke/pages/about.html` | About page |
| `https://habarihub.co.ke/admin/` | Admin login page |
| `https://habarihub.co.ke/api/health` | API health check (proxied) |

---

## Part 3: Configuration Reference

### Method A — Vercel Reverse Proxy (Recommended)

No frontend configuration needed. The `vercel.json` rewrite handles everything:

- Frontend calls `fetch('/api/articles')` → Vercel proxies to `https://api.yourdomain.co.ke/api/articles`
- Session cookies work as first-party (same domain from browser's perspective)
- CSRF tokens work natively
- Uploaded images at `/api/uploads/images/xxx.jpg` are served through the proxy

**Just update `vercel.json`** with your Truehost domain. That's it.

### Method B — Direct Cross-Domain (Alternative)

If you skip the Vercel proxy and call your Truehost API directly:

1. Edit `assets/js/config.js`:

```javascript
window.HABARI_CONFIG = {
  apiBase: 'https://api.yourdomain.co.ke/api'  // Your Truehost API domain
};
```

2. On Truehost, add your Vercel URL to `ALLOWED_ORIGINS` in `config.production.php`:

```php
define('ALLOWED_ORIGINS', 'https://habarihub.vercel.app,https://habarihub.co.ke');
```

3. The backend uses **Bearer tokens** for authentication (stored in `sessionStorage`), which work across domains even when third-party cookies are blocked.

> **Note:** Method A (proxy) is strongly recommended. Method B requires careful CORS + cookie configuration and may have issues with `credentials: 'include'` on cross-origin requests in some browsers.

### Environment Variables (Truehost cPanel)

| Variable | Local Value | Production Value |
|----------|-------------|-------------------|
| `APP_ENV` | `local` | `production` |
| `DB_HOST` | `127.0.0.1` | `localhost` |
| `DB_NAME` | `habarihub_local` | `yourcpaneluser_habarihub` |
| `DB_USER` | `root` | `yourcpaneluser_habari` |
| `DB_PASS` | *(empty)* | Your Truehost DB password |
| `ALLOWED_ORIGINS` | `http://localhost,...` | `https://habarihub.vercel.app,...` |

Set these in **cPanel → Select PHP Version → PHP Environment Variables** (or use a `config.production.php` file).

---

## Part 4: Admin Access

### Default Admin Credentials

| URL | Local | Production |
|-----|-------|-------------|
| Admin | `http://localhost/website/admin/` | `https://habarihub.co.ke/admin/` |
| Username | `admin` | `admin` (after setup) |
| Password | `Admin@2024` | *(set during setup)* |

### Post-Login Security Checklist

1. Navigate to **Admin → Settings** and verify site name, tagline, contact email, and social links.
2. Update your admin password via the database or a password reset script.
3. Ensure `APP_DEBUG = false` in `config.production.php` on Truehost.
4. Remove or restrict `api/setup/` directory.
5. Verify `.htaccess` files are present in `api/`, `api/uploads/`, and `api/sessions/`.

---

## Part 5: Sitemap & SEO

### sitemap.xml

Update `sitemap.xml` with your production domain:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://habarihub.co.ke/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
  <url><loc>https://habarihub.co.ke/pages/latest.html</loc><changefreq>hourly</changefreq><priority>0.9</priority></url>
  <url><loc>https://habarihub.co.ke/pages/trending.html</loc><changefreq>daily</changefreq><priority>0.8</priority></url>
  <url><loc>https://habarihub.co.ke/pages/politics.html</loc><changefreq>hourly</changefreq><priority>0.8</priority></url>
  <url><loc>https://habarihub.co.ke/pages/documentary.html</loc><changefreq>daily</changefreq><priority>0.7</priority></url>
  <url><loc>https://habarihub.co.ke/pages/about.html</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
  <url><loc>https://habarihub.co.ke/pages/contact.html</loc><changefreq>monthly</changefreq><priority>0.4</priority></url>
</urlset>
```

### robots.txt

```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/admin/
Disallow: /api/setup/

Sitemap: https://habarihub.co.ke/sitemap.xml
```

---

## Part 6: Troubleshooting

| Problem | Solution |
|---------|----------|
| **404 on `/api/health`** | Verify `api/.htaccess` is uploaded and `mod_rewrite` is enabled on Truehost |
| **CORS error in console** | Add your Vercel domain to `ALLOWED_ORIGINS` in `config.production.php`, or use the Vercel proxy (Method A) |
| **Admin login always fails** | Check that `config.local.php` was NOT uploaded to Truehost (it overrides production credentials). Ensure `APP_ENV=production`. |
| **Images 404 after upload** | Verify `UPLOAD_URL` = `/api/uploads/images` in `config.php`. Check file permissions on `api/uploads/images/` (`755`). |
| **Session lost immediately** | On Truehost with HTTPS + proxy, cookies use `SameSite=None; Secure`. Ensure your Vercel domain uses HTTPS (it does by default). |
| **ES module errors** | Must be served over HTTP(S), never `file://`. Use `http-server` locally for testing. |
| **`config.local.php` interfering** | It only loads when `APP_ENV !== 'production'`. Set `APP_ENV=production` on Truehost. |
| **Vercel redirect loops** | Ensure `cleanUrls` and `trailingSlash` are consistent. Current: `false` / `false`. |
| **500 Internal Server Error** | Check PHP error log in cPanel → Metrics → Errors. Ensure `APP_DEBUG=false` in production. |

---

## Part 7: Local Development Workflow

```bash
# 1. Start XAMPP (Apache + MySQL)
# 2. Create database: habarihub_local (utf8mb4_unicode_ci)
# 3. Import database/schema.sql and database/seed.sql

# 4. Create config.local.php (git-ignored):
#    DB_HOST=127.0.0.1, DB_NAME=habarihub_local, DB_USER=root, DB_PASS=''

# 5. Create first admin:
#    Set SETUP_TOKEN env var, visit http://localhost/website/api/setup/create_admin.php?token=...

# 6. Run frontend:
npx serve .  # or any static file server

# 7. Visit:
#    Frontend: http://localhost:5173/
#    Admin:    http://localhost:5173/admin/
#    API:      http://localhost/website/api/health
```

---

## File Upload Exclusion Checklist

When uploading to Truehost via FTP/File Manager, **do NOT upload**:

- `api/config/config.local.php` — local dev config (git-ignored)
- `api/config/config.production.php` — create this ON Truehost via cPanel file editor instead of uploading
- `api/setup/` — delete after creating admin (git-ignored)
- `api/sessions/sess_*` — session files (git-ignored, auto-regenerated)

---

## Security Checklist

- [ ] Admin password is **not** the default `Admin@2024`
- [ ] `APP_DEBUG = false` on Truehost
- [ ] `APP_ENV = production` on Truehost
- [ ] `api/setup/create_admin.php` deleted from Truehost
- [ ] `config.local.php` NOT uploaded to Truehost
- [ ] `config.production.php` NOT committed to GitHub
- [ ] CORS only allows your Vercel domain(s)
- [ ] `Allow from all` removed from `api/sessions/.htaccess`
- [ ] PHP execution blocked in `api/uploads/` (via `.htaccess`)
- [ ] All API queries use PDO prepared statements
- [ ] Session cookies: `HttpOnly=true`, `Secure=true` (HTTPS), `SameSite=None`
- [ ] CSRF token validated on all POST/PUT/DELETE admin endpoints
- [ ] Login rate-limited: 5 attempts per 15 minutes
