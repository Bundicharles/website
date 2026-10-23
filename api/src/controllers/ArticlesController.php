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
                       a.content_type, a.video_provider, a.video_embed_id,
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
            "SELECT a.title, a.slug, a.excerpt, a.featured_image, a.featured_video, a.published_at,
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
    public function featured(): void {
        $db   = getDB();
        $stmt = $db->query(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image, a.featured_video,
                     a.content_type, a.published_at,
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
                 'articles_per_page'
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
}
