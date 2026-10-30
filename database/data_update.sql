-- ============================================================
-- Data Integrity Update Query
-- Fixes: typos, mojibake encoding, empty fields, test data
-- Run on the habarihub_local database
-- ============================================================
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Fix seo_default_title: remove garbage prefix and fix
--    double-encoded em-dash (mojibake "ÔÇ–" -> proper "—")
-- --------------------------------------------------------
UPDATE site_settings
SET setting_value = 'Habari Hub — Kenyan News & Politics'
WHERE setting_key = 'seo_default_title';

-- --------------------------------------------------------
-- 2. Fix site_tagline: fix double-encoded em-dash
-- --------------------------------------------------------
UPDATE site_settings
SET setting_value = 'Kenya\'s Pulse — News, Politics & Docs'
WHERE setting_key = 'site_tagline';

-- --------------------------------------------------------
-- 3. Fix site_email: typo habarihuyub -> habarihub
-- --------------------------------------------------------
UPDATE site_settings
SET setting_value = 'info@habarihub.co.ke'
WHERE setting_key = 'site_email';

-- --------------------------------------------------------
-- 4. Fix media.media_type: empty values for docx uploads
--    (3 entries with .docx files but NULL/empty media_type)
-- --------------------------------------------------------
UPDATE media
SET media_type = 'document'
WHERE media_type = '' OR media_type IS NULL;

-- --------------------------------------------------------
-- 5. Fix categories: normalize hex color to uppercase
--    (category 9 has lowercase #c62828 while others are #C62828)
-- --------------------------------------------------------
UPDATE categories
SET color = UPPER(color)
WHERE BINARY color != UPPER(color);

-- --------------------------------------------------------
-- 6. Fix article_categories: cleanup test/placeholder article
--    Article 18 has title "iiii", slug "iiii", body "jjjj" —
--    test data that should be removed
-- --------------------------------------------------------
DELETE FROM article_likes
WHERE article_id = 18;

DELETE FROM article_categories
WHERE article_id = 18;

DELETE FROM article_comments
WHERE article_id = 18;

DELETE FROM articles
WHERE id = 18;

-- --------------------------------------------------------
-- 7. Fix missing media catalog entries for featured images
--    referenced in articles but not registered in media table
-- --------------------------------------------------------
INSERT INTO media (uploader_id, filename, original_name, mime_type, media_type, file_size, width, height, path)
VALUES
(1, 'ba0bde119cf76cdc8e6eb47ba0797409.jpg', 'RAO.jpg', 'image/jpeg', 'image', 16964, NULL, NULL, '/api/uploads/images/ba0bde119cf76cdc8e6eb47ba0797409.jpg')
ON DUPLICATE KEY UPDATE
  filename = VALUES(filename),
  original_name = VALUES(original_name),
  mime_type = VALUES(mime_type),
  media_type = VALUES(media_type),
  file_size = VALUES(file_size),
  path = VALUES(path);

-- --------------------------------------------------------
-- 8. Fix missing media catalog entries for sample images
--    referenced in articles 19 and 20
-- --------------------------------------------------------
INSERT INTO media (uploader_id, filename, original_name, mime_type, media_type, file_size, width, height, path)
VALUES
(1, 'sample-counties.jpg', 'Sample Counties', 'image/jpeg', 'image', 10329, NULL, NULL, '/api/uploads/images/sample-counties.jpg'),
(1, 'sample-documentary.jpg', 'Sample Documentary', 'image/jpeg', 'image', 12044, NULL, NULL, '/api/uploads/images/sample-documentary.jpg')
ON DUPLICATE KEY UPDATE
  filename = VALUES(filename),
  original_name = VALUES(original_name),
  mime_type = VALUES(mime_type),
  media_type = VALUES(media_type),
  file_size = VALUES(file_size),
  path = VALUES(path);

-- --------------------------------------------------------
-- 9. Ensure all published_at timestamps are set (no NULL)
--    for published articles
-- --------------------------------------------------------
UPDATE articles
SET published_at = created_at
WHERE status = 'published' AND published_at IS NULL;

-- --------------------------------------------------------
-- 10. Ensure likes_count, shares_count, comments_count
--     are accurate (sync from actual table counts)
-- --------------------------------------------------------
UPDATE articles a
SET likes_count = (
    SELECT COUNT(*) FROM article_likes al WHERE al.article_id = a.id
)
WHERE a.likes_count != (
    SELECT COUNT(*) FROM article_likes al WHERE al.article_id = a.id
);

UPDATE articles a
SET comments_count = (
    SELECT COUNT(*) FROM article_comments ac WHERE ac.article_id = a.id AND ac.is_approved = 1
)
WHERE a.comments_count != (
    SELECT COUNT(*) FROM article_comments ac WHERE ac.article_id = a.id AND ac.is_approved = 1
);

-- --------------------------------------------------------
-- 11. Verify: list all site_settings for review
-- --------------------------------------------------------
SELECT setting_key, setting_value FROM site_settings ORDER BY setting_key;

-- --------------------------------------------------------
-- 12. Verify: list all media with media_type
-- --------------------------------------------------------
SELECT id, filename, original_name, media_type, mime_type, path FROM media ORDER BY id;

-- --------------------------------------------------------
-- 13. Verify: list all articles (title, slug, content_type, status)
-- --------------------------------------------------------
SELECT id, title, slug, content_type, status, is_featured, is_trending FROM articles ORDER BY id;

-- --------------------------------------------------------
-- 14. Verify: list all categories (normalized colors)
-- --------------------------------------------------------
SELECT id, name, slug, color, sort_order FROM categories ORDER BY sort_order;

SELECT 'Data integrity update complete.' AS status;
