<?php
// ============================================================
// api/src/middleware/cors.php
// ============================================================
require_once dirname(__DIR__, 2) . '/config/config.php';

function handleCors(): void {
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_map('trim', explode(',', ALLOWED_ORIGINS));

    // Dynamic origin matching:
    // 1. Exact match in ALLOWED_ORIGINS
    // 2. Wildcard '*' in ALLOWED_ORIGINS
    // 3. Any *.vercel.app deployment (preview or production)
    // 4. Any localhost or 127.0.0.1 port (for local dev)
    $isAllowed = false;
    if ($origin !== '') {
        if (in_array('*', $allowed, true) || in_array($origin, $allowed, true)) {
            $isAllowed = true;
        } elseif (preg_match('/^https:\/\/[a-z0-9_.-]+\.vercel\.app$/i', $origin)) {
            $isAllowed = true;
        } elseif (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i', $origin)) {
            $isAllowed = true;
        }
    }

    if ($isAllowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
