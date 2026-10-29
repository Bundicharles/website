-- ============================================================
-- DK  –  Database Migration: Update old DB to current state
-- Run AFTER schema.sql (or on an existing old database)
-- Safe to run once on an old database.
-- ============================================================
SET time_zone = "+00:00";

-- -------------------------------------------------------
-- 1. articles: new engagement + document columns
-- -------------------------------------------------------
ALTER TABLE `articles`
  ADD COLUMN IF NOT EXISTS `featured_document` VARCHAR(500) NULL
    COMMENT 'Relative path to locally uploaded document or external URL' AFTER `featured_video`,
  ADD COLUMN IF NOT EXISTS `likes_count` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `shares_count` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `comments_count` INT UNSIGNED NOT NULL DEFAULT 0;

-- -------------------------------------------------------
-- 2. articles: extend content_type ENUM
-- -------------------------------------------------------
ALTER TABLE `articles`
  MODIFY COLUMN `content_type` ENUM('article','video','documentary','report','document')
    NOT NULL DEFAULT 'article';

-- -------------------------------------------------------
-- 3. media: extend media_type ENUM
-- -------------------------------------------------------
ALTER TABLE `media`
  MODIFY COLUMN `media_type` ENUM('image','video','document')
    NOT NULL DEFAULT 'image';

-- -------------------------------------------------------
-- 4. New table: article_likes
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `article_likes` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED  NOT NULL,
  `visitor_id` VARCHAR(64)   NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_visitor` (`article_id`, `visitor_id`),
  KEY `idx_article` (`article_id`),
  CONSTRAINT `fk_likes_article` FOREIGN KEY (`article_id`)
    REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 5. New table: article_comments (threaded comments + replies)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `article_comments` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `article_id`   INT UNSIGNED  NOT NULL,
  `parent_id`    INT UNSIGNED  NULL,
  `author_name`  VARCHAR(120)  NOT NULL,
  `author_email` VARCHAR(255)  NULL,
  `body`         TEXT          NOT NULL,
  `is_admin`     TINYINT(1)    NOT NULL DEFAULT 0,
  `is_approved`  TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article` (`article_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_comments_article` FOREIGN KEY (`article_id`)
    REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `article_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 6. site_settings: new keys (About & Contact pages)
-- -------------------------------------------------------
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
  ('about_content', '<p>DK is an independent Kenyan digital media platform committed to delivering accurate, fair, and timely news, political analysis, and documentary journalism.</p><p>We cover breaking news, parliamentary affairs, county government, economic stories, and produce long-form documentary content that goes beyond the headlines.</p>', 'general'),
  ('contact_intro', 'We would love to hear from you. Send us a message, a story tip, or a correction request.', 'general')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- -------------------------------------------------------
-- 7. Seed sample report + documentary articles (optional)
--    Skip if you already have data; safe to re-run (slugs are unique).
-- -------------------------------------------------------
INSERT INTO `articles`
  (`title`, `slug`, `excerpt`, `body`, `featured_image`, `author_id`, `category_id`,
   `content_type`, `status`, `is_featured`, `is_trending`, `is_breaking`, `trending_rank`, `published_at`)
SELECT * FROM (
  SELECT
    'Independent Audit Report: County Revenue Collection Gaps' AS title,
    'independent-audit-report-county-revenue-collection-gaps' AS slug,
    'An investigative report exposing systemic gaps in county revenue collection, weak audit trails, and unremitted funds across several devolved units.' AS excerpt,
    '<p>A comprehensive audit of county revenue systems reveals significant shortfalls in collection, weak internal controls, and billions of shillings in unremitted funds. The report calls for urgent reforms in financial management and oversight.</p><p>County governments have been urged to adopt digital revenue collection systems and strengthen audit trails to curb leakages.</p>' AS body,
    '/api/uploads/images/sample-counties.jpg' AS featured_image,
    1 AS author_id,
    9 AS category_id,
    'article' AS content_type,
    'published' AS status,
    1 AS is_featured,
    0 AS is_trending,
    0 AS is_breaking,
    0 AS trending_rank,
    DATE_SUB(NOW(), INTERVAL 3 HOUR) AS published_at
  UNION ALL
  SELECT
    'Documentary: Voices from the Margins of Kenya',
    'documentary-voices-from-the-margins-of-kenya',
    'A long-form documentary giving voice to communities often left out of mainstream national discourse, from arid northern counties to informal settlements.',
    '<p>This documentary explores the lived realities of Kenyans in marginalised regions, highlighting resilience, challenges, and aspirations. It features on-the-ground reporting and interviews with community leaders.</p>',
    '/api/uploads/images/sample-documentary.jpg',
    1, 5, 'documentary', 'published', 0, 1, 0, 0,
    DATE_SUB(NOW(), INTERVAL 1 DAY)
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE slug = tmp.slug);

-- -------------------------------------------------------
-- Done
-- -------------------------------------------------------
SELECT 'Migration complete' AS status;
