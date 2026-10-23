<?php
// ============================================================
// api/config/config.local.example.php
// Copy this file to config.local.php (which is ignored by Git)
// and adjust your local or environment-specific values.
// ============================================================

if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
if (!defined('DB_NAME'))    define('DB_NAME',    'habarihub_local');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    ''); // Empty password on default XAMPP
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('APP_ENV'))    define('APP_ENV',    'local');
if (!defined('APP_DEBUG'))  define('APP_DEBUG',  true);

if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1,http://localhost:5500,http://127.0.0.1:5500,http://localhost:3000');
}
