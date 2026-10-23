<?php
// ============================================================
// api/src/middleware/auth.php
// ============================================================
require_once dirname(__DIR__, 2) . '/config/config.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // Use a dedicated writable session storage directory inside api/sessions/
    // to avoid Windows/XAMPP C:\xampp\tmp permission denied issues
    $sessionDir = dirname(__DIR__, 2) . '/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0755, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_name(SESSION_NAME);

    // Support Bearer Token / Header-based session for cross-domain Vercel -> Truehost requests
    // where browsers might block 3rd-party cookies
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+([a-zA-Z0-9,-]+)/', $authHeader, $matches)) {
        $passedToken = trim($matches[1]);
        if (!empty($passedToken) && preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $passedToken)) {
            session_id($passedToken);
        }
    }

    // On HTTPS (production Truehost with Vercel frontend), use SameSite=None + Secure for cross-origin cookies.
    // On HTTP (local XAMPP), browsers require SameSite=Lax (None without Secure is rejected).
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $isHttps ? 'None' : 'Lax',
    ]);

    @session_start();
}

function requireAdmin(): void {
    startSecureSession();
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        jsonError(401, 'Authentication required.');
    }
    if (!in_array($_SESSION['role'], ['admin', 'editor'], true)) {
        jsonError(403, 'Insufficient permissions.');
    }
}

function requireRole(string ...$roles): void {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        jsonError(401, 'Authentication required.');
    }
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        jsonError(403, 'Insufficient permissions.');
    }
}

function getCurrentUserId(): ?int {
    startSecureSession();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']);
}

// CSRF -------------------------------------------------------
function generateCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    startSecureSession();
    $stored = $_SESSION['csrf_token'] ?? '';
    return $stored !== '' && hash_equals($stored, $token);
}

function requireCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? (getJsonBody()['_csrf'] ?? '');
    if (!validateCsrfToken((string)$token)) {
        jsonError(403, 'Invalid or missing CSRF token.');
    }
}
