# Habari Hub – Kenyan News, Politics & Documentary Platform

A production-ready editorial news platform built for Kenyan content. Features a premium dark editorial design, full CMS, PHP REST API, and MySQL database.

## Architecture

```
website/
├── index.html              ← Frontend entry point
├── article.html            ← Article detail page
├── 404.html
├── assets/
│   ├── css/               ← Design system, layout, page styles
│   ├── js/modules/        ← ES module frontend (api.js, utils.js, components.js)
│   └── images/            ← Static images, placeholder, favicon
├── pages/                 ← Public pages (latest, trending, politics, documentary…)
├── admin/                 ← Admin CMS dashboard
│   ├── index.html         ← Login
│   ├── dashboard.html
│   ├── articles.html / article-edit.html
│   ├── categories.html / media.html / settings.html / messages.html
│   └── assets/            ← Admin CSS + JS
├── api/                   ← PHP REST API (runs on Truehost / XAMPP)
│   ├── index.php          ← Front controller / router
│   ├── config/            ← config.php, database.php
│   ├── src/
│   │   ├── controllers/   ← ArticlesController, AdminController, ContactController
│   │   └── middleware/    ← cors.php, auth.php, response.php
│   ├── uploads/images/    ← Uploaded media (excluded from git)
│   └── setup/             ← First-admin setup script (delete after use)
└── database/
    ├── schema.sql         ← Complete MySQL schema
    └── seed.sql           ← Demo data + default settings
```

## Quick Start – Local Development (XAMPP)

### Prerequisites
- XAMPP with Apache and MySQL running on Windows
- PHP 8.1+
- A browser with ES module support (all modern browsers)

### 1. Place files
Copy the entire project to: `C:\xampp\htdocs\website\`

### 2. Create the database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create database: `habarihub_local` (utf8mb4_unicode_ci)
3. Import `database/schema.sql`
4. Import `database/seed.sql`

### 3. Configure the API
Create `api/config/config.local.php`:
```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'habarihub_local');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default is empty
// Override allowed CORS origins:
define('ALLOWED_ORIGINS', 'http://localhost,http://localhost:5500,http://127.0.0.1:5500,http://127.0.0.1');
```

### 4. Create your first admin user
1. Set an environment variable or edit `api/setup/create_admin.php` temporarily
2. Navigate to: `http://localhost/website/api/setup/create_admin.php`
3. Enter the setup token and create your admin account
4. **Delete** `api/setup/create_admin.php` immediately after

### 5. Open the frontend
Open a browser and visit:
- **Homepage:** `http://localhost/website/`
- **Admin:** `http://localhost/website/admin/`
- **API Health:** `http://localhost/website/api/health`

> **Note:** Open via Apache (`http://localhost/website/`), not via `file://`. ES modules require HTTP.

---

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Server and DB status |
| GET | `/api/articles` | All published articles (paginated) |
| GET | `/api/articles/{slug}` | Single article |
| GET | `/api/featured` | Featured articles |
| GET | `/api/trending` | Trending articles |
| GET | `/api/latest` | Latest by date |
| GET | `/api/breaking` | Breaking news ticker items |
| GET | `/api/documentaries` | Documentary posts |
| GET | `/api/politics` | Political news |
| GET | `/api/search?q=keyword` | Full-text search |
| GET | `/api/categories` | Active categories |
| POST | `/api/contact` | Submit contact form |

### Admin (session-authenticated)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/admin/login` | Authenticate |
| POST | `/api/admin/logout` | End session |
| GET | `/api/admin/me` | Current user + CSRF token |
| GET | `/api/admin/dashboard` | Stats + recent activity |
| GET/POST | `/api/admin/articles` | List / create articles |
| GET/PUT/DELETE | `/api/admin/articles/{id}` | Get / update / delete |
| GET/POST | `/api/admin/categories` | List / create categories |
| PUT/DELETE | `/api/admin/categories/{id}` | Update / delete |
| GET/POST | `/api/admin/media` | List / upload images |
| GET/POST | `/api/admin/settings` | Get / update site settings |
| GET | `/api/admin/messages` | Contact messages |
| GET | `/api/admin/messages/{id}` | Single message (marks read) |

### Query Parameters
- `page` – page number (default: 1)
- `per_page` – items per page (max 50, default 12)
- `category` – filter by category slug
- `status` – admin only: draft / published / archived
- `search` – admin article search

---

## Truehost cPanel Deployment

### Backend (API)

1. **Check PHP version** in cPanel → Software → Select PHP Version. Ensure PHP 8.1+.

2. **Create MySQL database** in cPanel → MySQL Databases:
   - Database: `yourusername_habarihub`
   - User with privileges: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER

3. **Import schema** via phpMyAdmin → import `database/schema.sql` then `database/seed.sql`

4. **Upload API files** via File Manager or FTP to `public_html/api/` (or a subdomain)

5. **Configure credentials** – set these in cPanel → Software → PHP Environment Variables, or create `api/config/config.local.php` **outside public_html** if possible:
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'yourusername_habarihub');
   define('DB_USER', 'yourusername_dbuser');
   define('DB_PASS', 'your_secure_password');
   define('APP_ENV', 'production');
   define('ALLOWED_ORIGINS', 'https://habarihub.co.ke,https://www.habarihub.co.ke');
   ```

6. **Configure URL rewriting** – ensure mod_rewrite is enabled and `api/.htaccess` is in place.

7. **Test:** `https://api.yourdomain.co.ke/api/health`

8. **Secure uploads directory** – confirm `api/uploads/.htaccess` prevents PHP execution.

9. **File permissions:**
   - PHP files: `644`
   - Directories: `755`
   - Uploads directory: `755`

10. **Delete setup script:** `api/setup/create_admin.php` after first admin is created.

### Frontend (Vercel)

1. Push your project to a GitHub repository.

2. Import to Vercel. Set **Root Directory** to the project root (where `index.html` lives).

3. **Environment / API URL:** Edit `index.html` and all public pages. In the `<script>` block, replace the production branch of `apiBase` with your Truehost API URL:
   ```js
   window.HABARI_CONFIG = {
     apiBase: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
       ? '/api'
       : 'https://api.habarihub.co.ke/api'   // ← your Truehost API URL
   };
   ```
   Alternatively, update `vercel.json` → `rewrites` to proxy `/api/*` to your Truehost URL.

4. **Custom domain** – add your `.co.ke` domain in Vercel → Domains.

5. **HTTPS** – Vercel provides automatic TLS. Enable SSL on Truehost via cPanel → SSL/TLS.

---

## Security Checklist

- [ ] Admin password uses `password_hash()` with cost ≥ 12
- [ ] Session cookies are `HttpOnly`, `Secure` (on HTTPS), `SameSite=Lax`
- [ ] CSRF token validated on all admin state-changing requests
- [ ] Login rate-limited (5 attempts / 15 min per IP)
- [ ] CORS only allows explicitly configured origins
- [ ] No `*` wildcard in credentialed CORS responses
- [ ] All DB queries use PDO prepared statements
- [ ] File uploads validated by MIME type (finfo), extension, and size
- [ ] PHP execution blocked in uploads directory via `.htaccess`
- [ ] No stack traces in production error responses
- [ ] `DB_PASS` and other secrets not committed to git
- [ ] `api/setup/create_admin.php` deleted after use
- [ ] `APP_DEBUG` is `false` in production
- [ ] Audit log records sensitive admin actions without storing passwords
- [ ] SEO metadata does not expose internal API responses

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `403 Forbidden` on API | Check `.htaccess` and mod_rewrite is enabled |
| `DB unavailable` | Verify DB credentials in `config.local.php` |
| CORS error | Add your frontend origin to `ALLOWED_ORIGINS` |
| Login always fails | Check password hash – regenerate with setup script |
| Images 404 | Verify `UPLOAD_URL` constant in `config.php` |
| ES module errors | Serve via HTTP, not `file://` |
| Admin session lost | Check cookie domain/path; ensure HTTPS for Secure cookies |

---

## Known Limitations & Future Work

1. **No real-time analytics** – Trending is admin-selected, not algorithmic.
2. **No email notifications** – Contact form stores to DB only; add PHP mailer or SMTP integration.
3. **No comment system** – Can be added as a separate module.
4. **No automated RSS aggregation** – Manual publishing only (by design for copyright compliance).
5. **Rich text editor** – Uses browser `contentEditable`; replace with TipTap or Quill for production.
6. **No image CDN** – Images served from Truehost; consider Cloudinary for production scale.
7. **Static sitemap** – Consider generating `/sitemap.xml` dynamically from the API for large sites.
8. **No scheduled publishing** – `scheduled_at` column exists; requires a cron job on Truehost.
9. **Single admin user** – Multi-user roles (editor, author) are schema-ready but need frontend UI.
10. **No 2FA** – Recommended before handling sensitive editorial data in production.
