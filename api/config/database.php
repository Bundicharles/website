<?php
// ============================================================
// api/config/database.php  –  PDO connection factory
// ============================================================
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/src/middleware/response.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        // Use Africa/Nairobi timezone to match PHP's date_default_timezone_set(TIMEZONE)
        // so that NOW() comparisons work correctly with published_at stored in local time
        $pdo->exec("SET time_zone = '+03:00'");
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            error_log('DB Connection failed: ' . $e->getMessage());
            jsonError(503, 'Database unavailable (' . $e->getMessage() . '). Please ensure MySQL is started in XAMPP and the database "' . DB_NAME . '" exists.');
        }
        jsonError(503, 'Database unavailable. Please try again later.');
    }
    return $pdo;
}
