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

        // Self-heal: ensure required tables/columns exist (idempotent migration)
        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'article_comments'")->fetchColumn();
            if (!$exists) {
                $sqlFile = dirname(__DIR__, 2) . '/database/migration_update.sql';
                if (is_readable($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    // Strip SQL comments
                    $sql = preg_replace('/--.*$/m', '', $sql);
                    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                        if ($stmt !== '' && !preg_match('/^\s*SELECT\b/i', $stmt)) {
                            try { $pdo->exec($stmt); } catch (Throwable $e) { error_log('Migration stmt failed: ' . $e->getMessage()); }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Schema self-heal failed: ' . $e->getMessage());
        }
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            error_log('DB Connection failed: ' . $e->getMessage());
            jsonError(503, 'Database unavailable (' . $e->getMessage() . '). Please ensure MySQL is started in XAMPP and the database "' . DB_NAME . '" exists.');
        }
        jsonError(503, 'Database unavailable. Please try again later.');
    }
    return $pdo;
}
