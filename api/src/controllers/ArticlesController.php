<?php
// ============================================================
// api/src/controllers/ArticlesController.php
// ============================================================
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__)    . '/middleware/response.php';

class ArticlesController {

    // ── GET /api/articles ─────────────────────────────────────
    public function index(): void {
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 12);
        $catSlug = trim($_GET['category']  ?? '');
        $type    = trim($_GET['type']      ?? '');

        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db   = getDB();
        $where  = ['a.status = ?'];
        $params = ['published'];

        if ($catSlug !== '') {
            $where[]  = 'c.slug = ?';
            $params[] = $catSlug;
        }
        if ($type !== '') {
            $where[]  = 'a.content_type = ?';
            $params[] = $type;
        }

        $whereStr = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM articles a
                     JOIN categories c ON c.id = a.category_id
                     WHERE $whereStr AND a.published_at <= NOW()";
        $stmt  = $db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video,
                       a.content_type, a.video_provider, a.video_url, a.video_embed_id,
                       a.source_name, a.is_featured, a.is_trending,
                       a.published_at, a.updated_at,
                       u.display_name AS author_name,
                       c.name AS category_name, c.slug AS category_slug, c.color AS category_color
                FROM articles a
                JOIN users u      ON u.id = a.author_id
                JOIN categories c ON c.id = a.category_id
                WHERE $whereStr AND a.published_at <= NOW()
                ORDER BY a.published_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        jsonPaginated($items, $total, $page, $perPage);
    }

    // ── GET /api/articles/{slug} ──────────────────────────────
    public function show(string $slug): void {
        $db  = getDB();
        $sql = "SELECT a.*, u.display_name AS author_name,
                       c.name AS category_name, c.slug AS category_slug, c.color AS category_color
                FROM articles a
                JOIN users u      ON u.id = a.author_id
                JOIN categories c ON c.id = a.category_id
                WHERE a.slug = ? AND a.status = 'published' AND a.published_at <= NOW()
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$slug]);
        $article = $stmt->fetch();

        if (!$article) jsonError(404, 'Article not found.');

        // Related articles
        $rel = $db->prepare(
            "SELECT a.title, a.slug, a.excerpt, a.featured_image, a.featured_video, a.video_url, a.published_at,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.id != ? AND a.category_id = ?
               AND a.published_at <= NOW()
             ORDER BY a.published_at DESC LIMIT 4"
        );
        $rel->execute([$article['id'], $article['category_id']]);
        $article['related'] = $rel->fetchAll();

        jsonSuccess($article);
    }

    // ── GET /api/featured ─────────────────────────────────────
    public function like(string $slug): void {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, likes_count FROM articles WHERE slug=? AND status='published'");
        $stmt->execute([$slug]);
        $art = $stmt->fetch();
        if (!$art) jsonError(404, 'Article not found.');
        $articleId = (int)$art['id'];
        $visitorId = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 64);
        $exists = $db->prepare("SELECT id FROM article_likes WHERE article_id=? AND visitor_id=?");
        $exists->execute([$articleId, $visitorId]);
        if ($exists->fetch()) {
            $db->prepare("DELETE FROM article_likes WHERE article_id=? AND visitor_id=?")->execute([$articleId, $visitorId]);
            $db->prepare("UPDATE articles SET likes_count = GREATEST(likes_count-1,0) WHERE id=?")->execute([$articleId]);
            $liked = false;
        } else {
            $db->prepare("INSERT INTO article_likes (article_id, visitor_id) VALUES (?,?)")->execute([$articleId, $visitorId]);
            $db->prepare("UPDATE articles SET likes_count = likes_count+1 WHERE id=?")->execute([$articleId]);
            $liked = true;
        }
        $stmt = $db->prepare("SELECT likes_count FROM articles WHERE id=?");
        $stmt->execute([$articleId]);
        $count = (int)$stmt->fetchColumn();
        jsonSuccess(['liked' => $liked, 'likes_count' => $count], $liked ? 'Liked.' : 'Unliked.');
    }

    public function comments(string $slug): void {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM articles WHERE slug=? AND status='published'");
        $stmt->execute([$slug]);
        $art = $stmt->fetch();
        if (!$art) jsonError(404, 'Article not found.');
        $articleId = (int)$art['id'];
        $rows = $db->prepare("SELECT id, parent_id, author_name, body, is_admin, created_at FROM article_comments WHERE article_id=? AND is_approved=1 ORDER BY created_at ASC");
        $rows->execute([$articleId]);
        $all = $rows->fetchAll();
        $byParent = [];
        foreach ($all as $r) { $byParent[$r['parent_id'] ?? 0][] = $r; }
        $build = function($parentId) use (&$build, $byParent) {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $r) {
                $r['replies'] = $build((int)$r['id']);
                $out[] = $r;
            }
            return $out;
        };
        jsonSuccess($build(0));
    }

    public function addComment(string $slug): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Method not allowed.');
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM articles WHERE slug=? AND status='published'");
        $stmt->execute([$slug]);
        $art = $stmt->fetch();
        if (!$art) jsonError(404, 'Article not found.');
        $articleId = (int)$art['id'];
        $body = getJsonBody();
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $text = trim($body['body'] ?? '');
        $parentId = isset($body['parent_id']) ? (int)$body['parent_id'] : null;
        if ($name === '' || mb_strlen($name) > 120) jsonError(422, 'Valid name required.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError(422, 'Invalid email.');
        if (mb_strlen($text) < 1) jsonError(422, 'Comment cannot be empty.');
        if (mb_strlen($text) > 2000) jsonError(422, 'Comment too long.');
        if ($parentId) {
            $p = $db->prepare("SELECT id, article_id FROM article_comments WHERE id=?");
            $p->execute([$parentId]);
            $prow = $p->fetch();
            if (!$prow || (int)$prow['article_id'] !== $articleId) jsonError(422, 'Invalid parent comment.');
        }
        $db->prepare("INSERT INTO article_comments (article_id, parent_id, author_name, author_email, body, is_admin, is_approved) VALUES (?,?,?,?,?,?,1)")
            ->execute([$articleId, $parentId, $name, $email ?: null, $text, 0]);
        $db->prepare("UPDATE articles SET comments_count = comments_count+1 WHERE id=?")->execute([$articleId]);
        jsonSuccess(['id' => (int)$db->lastInsertId()], 'Comment posted.', 201);
    }

    public function featured(): void {
        $db   = getDB();
        $stmt = $db->query(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video,
                     a.content_type, a.video_url, a.published_at,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.is_featured = 1 AND a.published_at <= NOW()
             ORDER BY a.published_at DESC LIMIT 5"
        );
        jsonSuccess($stmt->fetchAll());
    }

    // ── GET /api/trending ─────────────────────────────────────
    public function trending(): void {
        $db   = getDB();
        $stmt = $db->query(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video,
                     a.content_type, a.video_url, a.trending_rank, a.published_at,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.is_trending = 1 AND a.published_at <= NOW()
             ORDER BY a.trending_rank ASC, a.published_at DESC LIMIT 20"
        );
        jsonSuccess($stmt->fetchAll());
    }

    // ── GET /api/latest ───────────────────────────────────────
    public function latest(): void {
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 12);
        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db    = getDB();
        $total = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status='published' AND published_at <= NOW()")->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    a.content_type, a.video_url, a.published_at,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.published_at <= NOW()
             ORDER BY a.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── GET /api/breaking ─────────────────────────────────────
    public function breaking(): void {
        $db   = getDB();
        $stmt = $db->query(
            "SELECT a.title, a.slug, c.color AS category_color
             FROM articles a
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.is_breaking = 1 AND a.published_at <= NOW()
             ORDER BY a.published_at DESC LIMIT 8"
        );
        jsonSuccess($stmt->fetchAll());
    }

    // ── GET /api/documentaries ────────────────────────────────
    public function documentaries(): void {
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 9);
        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db   = getDB();
        $total = (int)$db->query(
            "SELECT COUNT(*) FROM articles a
             JOIN categories c ON c.id = a.category_id
             WHERE a.status='published' AND a.content_type='documentary' AND a.published_at <= NOW()"
        )->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video,
                     a.video_provider, a.video_url, a.video_embed_id,
                    a.published_at,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.content_type = 'documentary' AND a.published_at <= NOW()
             ORDER BY a.is_featured DESC, a.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── GET /api/politics ─────────────────────────────────────
    public function politics(): void {
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 12);
        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db    = getDB();
        $total = (int)$db->query(
            "SELECT COUNT(*) FROM articles a
             JOIN categories c ON c.id = a.category_id
             WHERE a.status='published' AND c.slug='politics' AND a.published_at <= NOW()"
        )->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video,
                     a.content_type, a.video_url, a.published_at, a.is_featured,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND c.slug = 'politics' AND a.published_at <= NOW()
             ORDER BY a.is_featured DESC, a.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── GET /api/reports ────────────────────────────────────────
    public function reports(): void {
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 12);
        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db    = getDB();
        $total = (int)$db->query(
            "SELECT COUNT(*) FROM articles a
             JOIN categories c ON c.id = a.category_id
             WHERE a.status='published' AND c.slug='reports' AND a.published_at <= NOW()"
        )->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video, a.featured_document,
                     a.content_type, a.video_url, a.published_at, a.is_featured,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND c.slug = 'reports' AND a.published_at <= NOW()
             ORDER BY a.is_featured DESC, a.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── GET /api/search ───────────────────────────────────────
    public function search(): void {
        $q       = trim($_GET['q'] ?? '');
        $page    = (int)($_GET['page']     ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 12);

        if (mb_strlen($q) < 2) jsonError(422, 'Query must be at least 2 characters.');
        if (mb_strlen($q) > 200) jsonError(422, 'Query too long.');

        [$page, $perPage, $offset] = paginate(0, $page, $perPage);

        $db    = getDB();
        $like  = '%' . $q . '%';

        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM articles a
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.published_at <= NOW()
               AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ? OR c.name LIKE ?)"
        );
        $countStmt->execute([$like, $like, $like, $like]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    a.content_type, a.video_url, a.published_at,
                    u.display_name AS author_name,
                    c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN users u      ON u.id = a.author_id
             JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published' AND a.published_at <= NOW()
               AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ? OR c.name LIKE ?)
             ORDER BY a.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$like, $like, $like, $like, $perPage, $offset]);
        jsonPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── GET /api/categories ───────────────────────────────────
    public function categories(): void {
        $stmt = getDB()->query(
            "SELECT id, name, slug, description, color, sort_order
             FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        jsonSuccess($stmt->fetchAll());
    }

    // ── GET /settings ───────────────────────────────────────
    public function settings(): void {
        $rows = getDB()->query(
            "SELECT setting_key, setting_value FROM site_settings
             WHERE setting_key IN (
                 'site_name','site_tagline','site_description',
                 'site_email','site_phone','site_address',
                 'twitter_url','facebook_url','youtube_url','instagram_url',
                 'footer_about','seo_default_title','seo_default_desc','og_image',
                 'articles_per_page','about_content','contact_intro'
             )"
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
        jsonSuccess($out);
    }

    // ── GET /health ───────────────────────────────────────
    public function health(): void {
        try {
            getDB()->query('SELECT 1');
            $dbStatus = 'connected';
        } catch (Throwable) {
            $dbStatus = 'error';
        }
        jsonSuccess([
            'status'  => 'ok',
            'db'      => $dbStatus,
            'version' => APP_VERSION,
            'time'    => date('c'),
        ]);
    }

    // ── GET /api/uploads/images/{filename} ─────────────────
    public function serveImage(string $filename): void {
        if (!defined('UPLOAD_PATH')) {
            jsonError(500, 'Upload path not configured.');
        }

        $filepath = UPLOAD_PATH . '/' . $filename;

        if (!file_exists($filepath) || !is_file($filepath)) {
            jsonError(404, 'File not found.');
        }

        $mimeType = mime_content_type($filepath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filepath));
        header('X-Content-Type-Options: nosniff');
        readfile($filepath);
        exit;
    }
}
