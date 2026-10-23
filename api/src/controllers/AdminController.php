<?php
// ============================================================
// api/src/controllers/AdminController.php
// Admin-protected CRUD operations
// ============================================================
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__)    . '/middleware/response.php';
require_once dirname(__DIR__)    . '/middleware/auth.php';

class AdminController {

    // ── POST /api/admin/login ─────────────────────────────────
    public function login(): void {
        startSecureSession();
        $body  = getJsonBody();
        $uname = trim($body['username'] ?? '');
        $pass  = $body['password'] ?? '';

        if ($uname === '' || $pass === '') {
            jsonError(422, 'Username and password are required.');
        }

        // Rate limit by IP
        $ip  = $this->getClientIp();
        $key = 'login_attempts_' . md5($ip);
        $attempts = (int)($_SESSION[$key . '_count'] ?? 0);
        $window   = (int)($_SESSION[$key . '_window'] ?? 0);

        if (time() < $window) {
            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                jsonError(429, 'Too many failed login attempts. Try again in 15 minutes.');
            }
        } else {
            $_SESSION[$key . '_count']  = 0;
            $_SESSION[$key . '_window'] = time() + LOGIN_WINDOW_SECS;
            $attempts = 0;
        }

        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, username, password_hash, display_name, role, is_active
             FROM users WHERE username = ? LIMIT 1"
        );
        $stmt->execute([$uname]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password_hash']) || !$user['is_active']) {
            $_SESSION[$key . '_count'] = $attempts + 1;
            $this->auditLog(null, 'login_failed', 'user', null, ['username' => $uname], $ip);
            jsonError(401, 'Invalid credentials.');
        }

        // Reset rate limit
        unset($_SESSION[$key . '_count'], $_SESSION[$key . '_window']);

        // Regenerate session
        session_regenerate_id(true);
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['csrf_token']   = bin2hex(random_bytes(32));

        $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
        $this->auditLog($user['id'], 'login', 'user', $user['id'], [], $ip);

        jsonSuccess([
            'user'       => ['id' => $user['id'], 'username' => $user['username'],
                             'display_name' => $user['display_name'], 'role' => $user['role']],
            'csrf_token' => $_SESSION['csrf_token'],
            'token'      => session_id(),
        ], 'Login successful.');
    }

    // ── POST /api/admin/logout ────────────────────────────────
    public function logout(): void {
        startSecureSession();
        $uid = $_SESSION['user_id'] ?? null;
        $this->auditLog($uid, 'logout', 'user', $uid, [], $this->getClientIp());
        session_unset();
        session_destroy();
        jsonSuccess(null, 'Logged out successfully.');
    }

    // ── GET /api/admin/me ─────────────────────────────────────
    public function me(): void {
        requireAdmin();
        jsonSuccess([
            'id'           => $_SESSION['user_id'],
            'username'     => $_SESSION['username'],
            'display_name' => $_SESSION['display_name'],
            'role'         => $_SESSION['role'],
            'csrf_token'   => generateCsrfToken(),
            'token'        => session_id(),
        ]);
    }

    // ── GET /api/admin/dashboard ──────────────────────────────
    public function dashboard(): void {
        requireAdmin();
        $db = getDB();

        $stats = [
            'total_articles'   => (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
            'published'        => (int)$db->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn(),
            'drafts'           => (int)$db->query("SELECT COUNT(*) FROM articles WHERE status='draft'")->fetchColumn(),
            'trending'         => (int)$db->query("SELECT COUNT(*) FROM articles WHERE is_trending=1 AND status='published'")->fetchColumn(),
            'documentaries'    => (int)$db->query("SELECT COUNT(*) FROM articles WHERE content_type='documentary' AND status='published'")->fetchColumn(),
            'politics'         => (int)$db->query("SELECT COUNT(*) FROM articles a JOIN categories c ON c.id=a.category_id WHERE c.slug='politics' AND a.status='published'")->fetchColumn(),
            'contact_messages' => (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
        ];

        $recent = $db->query(
            "SELECT a.id, a.title, a.status, a.published_at, a.created_at,
                    c.name AS category_name
             FROM articles a JOIN categories c ON c.id=a.category_id
             ORDER BY a.updated_at DESC LIMIT 10"
        )->fetchAll();

        $logs = $db->query(
            "SELECT al.action, al.entity_type, al.entity_id, al.created_at, u.display_name
             FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id
             ORDER BY al.created_at DESC LIMIT 20"
        )->fetchAll();

        jsonSuccess(['stats' => $stats, 'recent_articles' => $recent, 'audit_logs' => $logs]);
    }

    // ── GET /api/admin/articles ───────────────────────────────
    public function listArticles(): void {
        requireAdmin();
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $status  = trim($_GET['status']    ?? '');
        $search  = trim($_GET['search']    ?? '');
        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db     = getDB();
        $where  = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[]  = 'a.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[]  = '(a.title LIKE ? OR a.excerpt LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereStr = implode(' AND ', $where);

        $cs = $db->prepare("SELECT COUNT(*) FROM articles a WHERE $whereStr");
        $cs->execute($params);
        $total = (int)$cs->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.slug, a.status, a.content_type, a.is_featured,
                    a.is_trending, a.is_breaking, a.published_at, a.created_at, a.updated_at,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE $whereStr
             ORDER BY a.updated_at DESC
             LIMIT ? OFFSET ?"
        );
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── GET /api/admin/articles/{id} ──────────────────────────
    public function getArticle(int $id): void {
        requireAdmin();
        $stmt = getDB()->prepare(
            "SELECT a.*, u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if (!$article) jsonError(404, 'Article not found.');
        jsonSuccess($article);
    }

    // ── POST /api/admin/articles ──────────────────────────────
    public function createArticle(): void {
        requireAdmin();
        requireCsrf();
        $body = getJsonBody();
        $data = $this->validateArticleBody($body);
        $db   = getDB();

        // Ensure unique slug
        $slug = $this->uniqueSlug($db, $data['slug'], null);

        $sql = "INSERT INTO articles
                (title, slug, excerpt, body, featured_image, featured_video, author_id, category_id,
                 content_type, video_provider, video_url, video_embed_id,
                 source_name, source_url, status, is_featured, is_trending, is_breaking,
                 trending_rank, seo_title, seo_description, published_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['title'], $slug, $data['excerpt'], $data['body'],
            $data['featured_image'], $data['featured_video'], getCurrentUserId(), $data['category_id'],
            $data['content_type'], $data['video_provider'], $data['video_url'], $data['video_embed_id'],
            $data['source_name'], $data['source_url'], $data['status'],
            $data['is_featured'], $data['is_trending'], $data['is_breaking'],
            $data['trending_rank'], $data['seo_title'], $data['seo_description'],
            $data['status'] === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
        $newId = (int)$db->lastInsertId();
        $this->auditLog(getCurrentUserId(), 'article_created', 'article', $newId,
                        ['title' => $data['title']], $this->getClientIp());

        jsonSuccess(['id' => $newId, 'slug' => $slug], 'Article created.', 201);
    }

    // ── PUT /api/admin/articles/{id} ──────────────────────────
    public function updateArticle(int $id): void {
        requireAdmin();
        requireCsrf();
        $db  = getDB();
        $old = $db->prepare("SELECT id, slug, status FROM articles WHERE id = ?");
        $old->execute([$id]);
        $existing = $old->fetch();
        if (!$existing) jsonError(404, 'Article not found.');

        $body = getJsonBody();
        $data = $this->validateArticleBody($body, true);
        $slug = $this->uniqueSlug($db, $data['slug'], $id);

        // Determine published_at
        $publishedAt = null;
        if ($data['status'] === 'published' && $existing['status'] !== 'published') {
            $publishedAt = date('Y-m-d H:i:s');
        } elseif ($data['status'] === 'published') {
            // Keep existing published_at
            $paRow = $db->prepare("SELECT published_at FROM articles WHERE id=?");
            $paRow->execute([$id]);
            $publishedAt = $paRow->fetchColumn();
        }

        $sql = "UPDATE articles SET
                title=?, slug=?, excerpt=?, body=?, featured_image=?, featured_video=?,
                category_id=?, content_type=?, video_provider=?, video_url=?, video_embed_id=?,
                source_name=?, source_url=?, status=?, is_featured=?, is_trending=?,
                is_breaking=?, trending_rank=?, seo_title=?, seo_description=?, published_at=?
                WHERE id=?";
        $db->prepare($sql)->execute([
            $data['title'], $slug, $data['excerpt'], $data['body'],
            $data['featured_image'], $data['featured_video'], $data['category_id'],
            $data['content_type'], $data['video_provider'], $data['video_url'], $data['video_embed_id'],
            $data['source_name'], $data['source_url'], $data['status'],
            $data['is_featured'], $data['is_trending'], $data['is_breaking'],
            $data['trending_rank'], $data['seo_title'], $data['seo_description'],
            $publishedAt, $id,
        ]);
        $this->auditLog(getCurrentUserId(), 'article_updated', 'article', $id,
                        ['title' => $data['title']], $this->getClientIp());

        jsonSuccess(['id' => $id, 'slug' => $slug], 'Article updated.');
    }

    // ── DELETE /api/admin/articles/{id} ───────────────────────
    public function deleteArticle(int $id): void {
        requireAdmin();
        requireCsrf();
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, title FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if (!$article) jsonError(404, 'Article not found.');

        $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
        $this->auditLog(getCurrentUserId(), 'article_deleted', 'article', $id,
                        ['title' => $article['title']], $this->getClientIp());
        jsonSuccess(null, 'Article deleted.');
    }

    // ── POST /api/admin/media ─────────────────────────────────
    public function uploadMedia(): void {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Method not allowed.');

        $file = $_FILES['image'] ?? ($_FILES['file'] ?? null);
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            jsonError(422, 'No valid file uploaded.');
        }

        // Validate MIME type (read from file, not header)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_MIME, true)) {
            jsonError(415, 'Unsupported file type.');
        }

        // Determine media type and size limit
        $isVideo   = str_starts_with($mimeType, 'video/');
        $mediaType = $isVideo ? 'video' : 'image';
        $maxBytes  = $isVideo ? MAX_VIDEO_BYTES : MAX_UPLOAD_BYTES;
        $maxLabel  = $isVideo ? '50 MB' : '5 MB';

        // Validate size
        if ($file['size'] > $maxBytes) {
            jsonError(413, "File exceeds maximum size of {$maxLabel}.");
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) {
            jsonError(415, 'Unsupported file extension.');
        }

        // Safe filename
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!is_dir(UPLOAD_PATH)) {
            @mkdir(UPLOAD_PATH, 0755, true);
        }
        $destPath     = UPLOAD_PATH . '/' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            jsonError(500, 'Failed to save uploaded file.');
        }

        // Get dimensions (images only)
        $width = null; $height = null; $duration = null;
        if (!$isVideo) {
            $dims   = @getimagesize($destPath);
            $width  = $dims[0] ?? null;
            $height = $dims[1] ?? null;
        } else {
            // Try to get video duration via getID3 or shell
            $duration = $this->getVideoDuration($destPath);
        }

        $db = getDB();
        $db->prepare(
            "INSERT INTO media (uploader_id, filename, original_name, mime_type, media_type, file_size, width, height, duration, path)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            getCurrentUserId(), $safeFilename, basename($file['name']),
            $mimeType, $mediaType, $file['size'], $width, $height, $duration,
            UPLOAD_URL . '/' . $safeFilename,
        ]);

        $mediaId = (int)$db->lastInsertId();
        $this->auditLog(getCurrentUserId(), 'media_uploaded', 'media', $mediaId,
                        ['filename' => $safeFilename, 'media_type' => $mediaType], $this->getClientIp());

        jsonSuccess([
            'id'         => $mediaId,
            'url'        => UPLOAD_URL . '/' . $safeFilename,
            'name'        => $safeFilename,
            'media_type'  => $mediaType,
            'mime_type'   => $mimeType,
        ], 'File uploaded.', 201);
    }

    // ── GET /api/admin/media ──────────────────────────────────
    public function listMedia(): void {
        requireAdmin();
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 24);
        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db    = getDB();
        $total = (int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn();
        $stmt  = $db->prepare(
            "SELECT m.*, u.display_name AS uploader_name
             FROM media m JOIN users u ON u.id = m.uploader_id
             ORDER BY m.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── Helper: try to get video duration in seconds ────────────
    private function getVideoDuration(string $path): ?int {
        // Try ffprobe (if available on the server)
        $cmds = [
            "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path),
            "ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg($path),
        ];
        foreach ($cmds as $cmd) {
            $output = @shell_exec($cmd);
            if ($output !== null && trim($output) !== '' && is_numeric(trim($output))) {
                return (int)trim($output);
            }
        }
        return null;
    }

    // ── Category CRUD ─────────────────────────────────────────
    public function listCategories(): void {
        requireAdmin();
        $stmt = getDB()->query(
            "SELECT id, name, slug, description, color, sort_order, is_active, created_at
             FROM categories ORDER BY sort_order ASC, name ASC"
        );
        jsonSuccess($stmt->fetchAll());
    }

    public function createCategory(): void {
        requireAdmin();
        requireCsrf();
        $body = getJsonBody();
        $name = trim($body['name'] ?? '');
        if ($name === '') jsonError(422, 'Category name is required.');

        $slug  = $this->uniqueCategorySlug(getDB(), slugify($name));
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $body['color'] ?? '') ? $body['color'] : '#C62828';

        getDB()->prepare(
            "INSERT INTO categories (name, slug, description, color, sort_order, is_active)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            $name, $slug, trim($body['description'] ?? ''), $color,
            (int)($body['sort_order'] ?? 0), 1,
        ]);
        jsonSuccess(['id' => (int)getDB()->lastInsertId(), 'slug' => $slug], 'Category created.', 201);
    }

    public function updateCategory(int $id): void {
        requireAdmin();
        requireCsrf();
        $body = getJsonBody();
        $name = trim($body['name'] ?? '');
        if ($name === '') jsonError(422, 'Category name is required.');

        $db   = getDB();
        $slug = $this->uniqueCategorySlug($db, slugify($name), $id);
        $db->prepare(
            "UPDATE categories SET name=?, slug=?, description=?, color=?, sort_order=?, is_active=?
             WHERE id=?"
        )->execute([
            $name, $slug, trim($body['description'] ?? ''),
            preg_match('/^#[0-9a-fA-F]{6}$/', $body['color'] ?? '') ? $body['color'] : '#C62828',
            (int)($body['sort_order'] ?? 0), (int)(bool)($body['is_active'] ?? 1), $id,
        ]);
        jsonSuccess(null, 'Category updated.');
    }

    public function deleteCategory(int $id): void {
        requireAdmin();
        requireCsrf();
        $db = getDB();
        $cs = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id=?");
        $cs->execute([$id]);
        if ((int)$cs->fetchColumn() > 0) jsonError(409, 'Cannot delete category with existing articles.');
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        jsonSuccess(null, 'Category deleted.');
    }

    // ── Site settings ─────────────────────────────────────────
    public function getSettings(): void {
        requireAdmin();
        $rows = getDB()->query("SELECT setting_key, setting_value, setting_group FROM site_settings")->fetchAll();
        $out  = [];
        foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
        jsonSuccess($out);
    }

    public function updateSettings(): void {
        requireAdmin();
        requireCsrf();
        $body = getJsonBody();
        $db   = getDB();

        $allowedKeys = ['site_name','site_tagline','site_description','site_email','site_phone',
                        'site_address','twitter_url','facebook_url','youtube_url','instagram_url',
                        'footer_about','seo_default_title','seo_default_desc','articles_per_page'];
        foreach ($allowedKeys as $key) {
            if (isset($body[$key])) {
                $val  = (string)$body[$key];
                $stmt = $db->prepare(
                    "INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"
                );
                $stmt->execute([$key, $val]);
            }
        }
        $this->auditLog(getCurrentUserId(), 'settings_updated', null, null, [], $this->getClientIp());
        jsonSuccess(null, 'Settings updated.');
    }

    // ── Contact messages ──────────────────────────────────────
    public function listMessages(): void {
        requireAdmin();
        $stmt = getDB()->query(
            "SELECT id, name, email, subject, is_read, created_at
             FROM contact_messages ORDER BY created_at DESC LIMIT 50"
        );
        jsonSuccess($stmt->fetchAll());
    }

    public function getMessage(int $id): void {
        requireAdmin();
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id=?");
        $stmt->execute([$id]);
        $msg = $stmt->fetch();
        if (!$msg) jsonError(404, 'Message not found.');
        $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
        jsonSuccess($msg);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function validateArticleBody(array $b, bool $update = false): array {
        $title = trim($b['title'] ?? '');
        if ($title === '') jsonError(422, 'Title is required.');
        if (mb_strlen($title) > 500) jsonError(422, 'Title too long (max 500 characters).');

        $type = $b['content_type'] ?? 'article';
        if (!in_array($type, ['article','video','documentary'], true)) {
            jsonError(422, 'Invalid content type.');
        }

        $status = $b['status'] ?? 'draft';
        if (!in_array($status, ['draft','published','archived'], true)) {
            jsonError(422, 'Invalid status.');
        }

        $catId = (int)($b['category_id'] ?? 0);
        if ($catId < 1) jsonError(422, 'A valid category is required.');

        $videoUrl = '';
        $embedId  = '';
        $provider = '';
        if (!empty($b['video_url'])) {
            $videoUrl = filter_var(trim($b['video_url']), FILTER_SANITIZE_URL);
            // Extract YouTube embed ID
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $m)) {
                $embedId  = $m[1];
                $provider = 'youtube';
            } elseif (str_contains($videoUrl, 'vimeo.com')) {
                $provider = 'vimeo';
                preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m);
                $embedId = $m[1] ?? '';
            }
        }

        return [
            'title'          => sanitizeString($title),
            'slug'           => slugify($b['slug'] ?? $title),
            'excerpt'        => sanitizeString(substr($b['excerpt'] ?? '', 0, 1000)),
            'body'           => sanitizeHtml($b['body'] ?? ''),
            'featured_image' => sanitizeString(substr($b['featured_image'] ?? '', 0, 500)),
            'featured_video' => sanitizeString(substr($b['featured_video'] ?? '', 0, 500)),
            'category_id'    => $catId,
            'content_type'   => $type,
            'video_provider' => $provider,
            'video_url'      => substr($videoUrl, 0, 1000),
            'video_embed_id' => substr($embedId, 0, 200),
            'source_name'    => sanitizeString(substr($b['source_name'] ?? '', 0, 255)),
            'source_url'     => filter_var(trim($b['source_url'] ?? ''), FILTER_SANITIZE_URL),
            'status'         => $status,
            'is_featured'    => (int)(bool)($b['is_featured'] ?? false),
            'is_trending'    => (int)(bool)($b['is_trending'] ?? false),
            'is_breaking'    => (int)(bool)($b['is_breaking'] ?? false),
            'trending_rank'  => (int)($b['trending_rank'] ?? 0),
            'seo_title'      => sanitizeString(substr($b['seo_title'] ?? '', 0, 200)),
            'seo_description'=> sanitizeString(substr($b['seo_description'] ?? '', 0, 500)),
        ];
    }

    private function uniqueSlug(PDO $db, string $slug, ?int $excludeId): string {
        $base = $slug ?: ('article-' . time());
        $slug = $base;
        $i    = 1;
        while (true) {
            $stmt = $db->prepare("SELECT id FROM articles WHERE slug=?");
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['id'] === $excludeId) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function uniqueCategorySlug(PDO $db, string $slug, ?int $excludeId = null): string {
        $base = $slug;
        $i    = 1;
        while (true) {
            $stmt = $db->prepare("SELECT id FROM categories WHERE slug=?");
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['id'] === $excludeId) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function getClientIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                return explode(',', $_SERVER[$k])[0];
            }
        }
        return '0.0.0.0';
    }

    private function auditLog(?int $userId, string $action, ?string $entityType,
                               ?int $entityId, array $details, string $ip): void {
        try {
            getDB()->prepare(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$userId, $action, $entityType, $entityId, json_encode($details), $ip]);
        } catch (Throwable) {}
    }
}
