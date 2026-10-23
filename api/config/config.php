<?php
// ============================================================
// api/config/config.php  –  Environment-aware configuration
// ============================================================

if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'local');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', APP_ENV === 'local');
}

// ── Load environment-specific overrides ────────────────────────
// config.production.php is ALWAYS loaded first (provides prod defaults)
$prodConfig = __DIR__ . '/config.production.php';
if (file_exists($prodConfig)) {
    require_once $prodConfig;
}

// config.local.php is ONLY loaded in non-production environments.
// This prevents an accidentally deployed config.local.php from
// overriding your Truehost production database credentials.
if (APP_ENV !== 'production') {
    $localConfig = __DIR__ . '/config.local.php';
    if (file_exists($localConfig)) {
        require_once $localConfig;
    }
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'Africa/Nairobi');
}

// ── 3. Database credentials ─────────────────────────────────
// Override via config.local.php or environment variables in cPanel
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'habarihub_local');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// ── 4. Paths ────────────────────────────────────────────────
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . '/uploads/images');
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', '/api/uploads/images');
}

// ── 5. Upload limits ────────────────────────────────────────
if (!defined('MAX_UPLOAD_BYTES')) {
    define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB (images)
}
if (!defined('MAX_VIDEO_BYTES')) {
    define('MAX_VIDEO_BYTES', 50 * 1024 * 1024); // 50 MB (videos)
}
if (!defined('ALLOWED_MIME')) {
    define('ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif',
                            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']);
}
if (!defined('ALLOWED_EXT')) {
    define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp', 'gif',
                           'mp4', 'webm', 'ogv', 'mov']);
}

// ── 6. CORS ─────────────────────────────────────────────────
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: 'http://localhost,http://127.0.0.1,http://localhost:5500,http://127.0.0.1:5500,http://localhost:3000');
}

// ── 7. Session ──────────────────────────────────────────────
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'habari_session');
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600 * 8); // 8 hours
}

// ── 8. Rate limiting (login) ────────────────────────────────
if (!defined('LOGIN_MAX_ATTEMPTS')) {
    define('LOGIN_MAX_ATTEMPTS', 5);
}
if (!defined('LOGIN_WINDOW_SECS')) {
    define('LOGIN_WINDOW_SECS', 900); // 15 minutes
}
