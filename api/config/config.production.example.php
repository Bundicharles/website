<?php
// ============================================================
// api/config/config.production.php (Copy from this example on Truehost)
// Production Environment Configuration for Truehost cPanel
// ============================================================

// Database Configuration (from Truehost cPanel MySQL Databases)
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost'); // Usually localhost on cPanel
if (!defined('DB_NAME'))    define('DB_NAME',    'cpaneluser_habarihub'); // e.g. your_cpanel_db
if (!defined('DB_USER'))    define('DB_USER',    'cpaneluser_habariuser'); // e.g. your_cpanel_user
if (!defined('DB_PASS'))    define('DB_PASS',    'YourStrongDbPasswordHere');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Environment settings
if (!defined('APP_ENV'))    define('APP_ENV',    'production');
if (!defined('APP_DEBUG'))  define('APP_DEBUG',  false);

// CORS: Add your Vercel deployment URL and custom domain here
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', 'https://habarihub.vercel.app,https://yourdomain.co.ke,https://www.yourdomain.co.ke');
}
