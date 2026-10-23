<?php
// ============================================================
// api/src/middleware/response.php  –  JSON helpers
// ============================================================

function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonSuccess(mixed $data = null, string $message = 'OK', int $code = 200): never {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $code);
}

function jsonError(int $code, string $message, array $errors = []): never {
    $body = ['success' => false, 'message' => $message];
    if ($errors) $body['errors'] = $errors;
    jsonResponse($body, $code);
}

function jsonPaginated(array $items, int $total, int $page, int $perPage): never {
    jsonResponse([
        'success' => true,
        'data'    => $items,
        'meta'    => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ],
    ]);
}

function getJsonBody(): array {
    static $body = null;
    if ($body !== null) return $body;
    $raw  = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?? []) : [];
    return $body;
}

function sanitizeString(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Allow only a safe subset of HTML for article body (server-side strip)
function sanitizeHtml(string $html): string {
    // Strip dangerous tags; allow inline + block formatting
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><figure><figcaption><img><table><thead><tbody><tr><th><td>';
    $stripped = strip_tags($html, $allowed);
    // Strip on* event attributes, javascript: hrefs
    $stripped = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $stripped);
    $stripped = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $stripped);
    return $stripped;
}

function slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function paginate(int $total, int $page, int $perPage): array {
    $page    = max(1, $page);
    $perPage = min(50, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
}
