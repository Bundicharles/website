<?php
// ============================================================
// api/index.php  –  Front controller / router
// ============================================================
declare(strict_types=1);

// Absolute root of api/ folder
define('API_ROOT', __DIR__);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/cors.php';
require_once __DIR__ . '/src/middleware/response.php';
require_once __DIR__ . '/src/middleware/auth.php';
require_once __DIR__ . '/src/controllers/ArticlesController.php';
require_once __DIR__ . '/src/controllers/AdminController.php';
require_once __DIR__ . '/src/controllers/ContactController.php';

// Set timezone for display
date_default_timezone_set(TIMEZONE);

// Global error handler (no stack traces in production)
set_error_handler(function(int $errno, string $errstr) {
    if (APP_DEBUG) {
        error_log("PHP Error [$errno]: $errstr");
    }
    return false;
});
set_exception_handler(function(Throwable $e) {
    error_log('Uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        jsonError(500, APP_DEBUG ? $e->getMessage() : 'Internal server error.');
    }
});

// CORS headers
handleCors();

// Parse path
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$path       = '/' . ltrim(substr($requestUri, strlen($scriptDir)), '/');
$path       = rtrim($path, '/') ?: '/';
$method     = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Extract segments
$segments = array_values(array_filter(explode('/', $path)));

// If 'api' is the leading segment (e.g. if scriptDir did not include /api), strip it
if (!empty($segments) && strtolower($segments[0]) === 'api') {
    array_shift($segments);
}

$resource = $segments[0] ?? '';
$sub      = $segments[1] ?? '';
$id       = $segments[2] ?? '';

$articles = new ArticlesController();
$admin    = new AdminController();
$contact  = new ContactController();

// ── Public Routes ─────────────────────────────────────────────
if ($resource !== 'admin') {
    match (true) {
        $method === 'GET'  && $resource === 'health'                       => $articles->health(),
        $method === 'GET'  && $resource === 'articles' && $sub === ''      => $articles->index(),
        $method === 'GET'  && $resource === 'articles' && $sub !== ''      => $articles->show($sub),
        $method === 'GET'  && $resource === 'featured'                     => $articles->featured(),
        $method === 'GET'  && $resource === 'trending'                     => $articles->trending(),
        $method === 'GET'  && $resource === 'latest'                       => $articles->latest(),
        $method === 'GET'  && $resource === 'breaking'                     => $articles->breaking(),
        $method === 'GET'  && $resource === 'documentaries'                => $articles->documentaries(),
        $method === 'GET'  && $resource === 'politics'                     => $articles->politics(),
        $method === 'GET'  && $resource === 'reports'                      => $articles->reports(),
        $method === 'GET'  && $resource === 'search'                       => $articles->search(),
        $method === 'GET'  && $resource === 'categories'                   => $articles->categories(),
        $method === 'GET'  && $resource === 'settings'                     => $articles->settings(),
        $method === 'POST' && $resource === 'contact'                      => $contact->submit(),
        default => jsonError(404, 'Endpoint not found.'),
    };
    // Public route handled — stop here
    exit;
}

// ── Admin Routes (/api/admin/...) ─────────────────────────────
match (true) {
    // Auth
    $method === 'POST' && $sub === 'login'                                 => $admin->login(),
    $method === 'POST' && $sub === 'logout'                                => $admin->logout(),
    $method === 'GET'  && $sub === 'me'                                     => $admin->me(),

    // Dashboard
    $method === 'GET'  && $sub === 'dashboard'                              => $admin->dashboard(),

    // Articles CRUD
    $method === 'GET'    && $sub === 'articles' && $id === ''              => $admin->listArticles(),
    $method === 'POST'   && $sub === 'articles' && $id === ''              => $admin->createArticle(),
    $method === 'GET'    && $sub === 'articles' && is_numeric($id)         => $admin->getArticle((int)$id),
    $method === 'PUT'    && $sub === 'articles' && is_numeric($id)         => $admin->updateArticle((int)$id),
    $method === 'DELETE' && $sub === 'articles' && is_numeric($id)         => $admin->deleteArticle((int)$id),

    // Categories CRUD
    $method === 'GET'    && $sub === 'categories' && $id === ''            => $admin->listCategories(),
    $method === 'POST'   && $sub === 'categories' && $id === ''            => $admin->createCategory(),
    $method === 'PUT'    && $sub === 'categories' && is_numeric($id)       => $admin->updateCategory((int)$id),
    $method === 'DELETE' && $sub === 'categories' && is_numeric($id)       => $admin->deleteCategory((int)$id),

    // Media
    $method === 'GET'  && $sub === 'media'                                  => $admin->listMedia(),
    $method === 'POST' && $sub === 'media'                                  => $admin->uploadMedia(),

    // Settings
    $method === 'GET'  && $sub === 'settings'                               => $admin->getSettings(),
    $method === 'POST' && $sub === 'settings'                               => $admin->updateSettings(),

    // Messages
    $method === 'GET'  && $sub === 'messages' && $id === ''                => $admin->listMessages(),
    $method === 'GET'  && $sub === 'messages' && is_numeric($id)           => $admin->getMessage((int)$id),

    default => jsonError(404, 'Admin endpoint not found.'),
};
