-- ============================================================
-- DK - Kenyan News, Politics & Documentary Platform
-- Database Schema  |  MySQL / MariaDB
-- Timezone: UTC (display in Africa/Nairobi via application)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -------------------------------------------------------
-- Drop tables in reverse dependency order
-- -------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `article_categories`;
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `articles`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `users`;

-- -------------------------------------------------------
-- users
-- -------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(80)     NOT NULL,
  `email`         VARCHAR(255)    NOT NULL,
  `password_hash` VARCHAR(255)    NOT NULL,
  `display_name`  VARCHAR(120)    NOT NULL DEFAULT '',
  `role`          ENUM('admin','editor','author') NOT NULL DEFAULT 'author',
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `last_login_at` DATETIME        NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- categories
-- -------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)   NOT NULL,
  `slug`        VARCHAR(120)   NOT NULL,
  `description` TEXT           NULL,
  `color`       VARCHAR(20)    NOT NULL DEFAULT '#C62828',
  `sort_order`  SMALLINT       NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- articles (posts of all types)
-- -------------------------------------------------------
CREATE TABLE `articles` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(500)    NOT NULL,
  `slug`            VARCHAR(520)    NOT NULL,
  `excerpt`         TEXT            NULL,
  `body`            LONGTEXT        NULL,
  `featured_image`  VARCHAR(500)    NULL  COMMENT 'Relative path under /api/uploads/ or external URL',
  `featured_video`  VARCHAR(500)    NULL  COMMENT 'Relative path to locally uploaded video or external URL',
  `featured_document` VARCHAR(500)   NULL  COMMENT 'Relative path to locally uploaded document or external URL',
  `likes_count`       INT UNSIGNED   NOT NULL DEFAULT 0,
  `shares_count`      INT UNSIGNED   NOT NULL DEFAULT 0,
  `comments_count`    INT UNSIGNED   NOT NULL DEFAULT 0,
  `author_id`       INT UNSIGNED    NOT NULL,
  `category_id`     INT UNSIGNED    NOT NULL,
  `content_type`    ENUM('article','video','documentary','report','document') NOT NULL DEFAULT 'article',
  `video_provider`  VARCHAR(50)     NULL  COMMENT 'youtube | vimeo | other',
  `video_url`       VARCHAR(1000)   NULL,
  `video_embed_id`  VARCHAR(200)    NULL,
  `source_name`     VARCHAR(255)    NULL,
  `source_url`      VARCHAR(1000)   NULL,
  `status`          ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `is_featured`     TINYINT(1)      NOT NULL DEFAULT 0,
  `is_trending`     TINYINT(1)      NOT NULL DEFAULT 0,
  `is_breaking`     TINYINT(1)      NOT NULL DEFAULT 0,
  `trending_rank`   SMALLINT        NOT NULL DEFAULT 0,
  `seo_title`       VARCHAR(200)    NULL,
  `seo_description` VARCHAR(500)    NULL,
  `published_at`    DATETIME        NULL,
  `scheduled_at`    DATETIME        NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_status_published`   (`status`, `published_at`),
  KEY `idx_category`           (`category_id`),
  KEY `idx_author`             (`author_id`),
  KEY `idx_is_featured`        (`is_featured`),
  KEY `idx_is_trending`        (`is_trending`),
  KEY `idx_is_breaking`        (`is_breaking`),
  KEY `idx_content_type`       (`content_type`),
  FULLTEXT KEY `ft_search`     (`title`, `excerpt`, `body`),
  CONSTRAINT `fk_articles_author`   FOREIGN KEY (`author_id`)   REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- article_likes (visitor likes per article)
-- -------------------------------------------------------
CREATE TABLE `article_likes` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED  NOT NULL,
  `visitor_id` VARCHAR(64)   NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_visitor` (`article_id`, `visitor_id`),
  KEY `idx_article` (`article_id`),
  CONSTRAINT `fk_likes_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- article_comments (comments + nested replies)
-- -------------------------------------------------------
CREATE TABLE `article_comments` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `article_id`  INT UNSIGNED  NOT NULL,
  `parent_id`   INT UNSIGNED  NULL,
  `author_name` VARCHAR(120)  NOT NULL,
  `author_email` VARCHAR(255) NULL,
  `body`        TEXT          NOT NULL,
  `is_admin`    TINYINT(1)    NOT NULL DEFAULT 0,
  `is_approved` TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article` (`article_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_comments_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `article_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- article_categories (many-to-many secondary tags)
-- -------------------------------------------------------
CREATE TABLE `article_categories` (
  `article_id`  INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`, `category_id`),
  CONSTRAINT `fk_ac_article`   FOREIGN KEY (`article_id`)  REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ac_category`  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- media  (uploaded files catalog)
-- -------------------------------------------------------
CREATE TABLE `media` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `uploader_id` INT UNSIGNED   NOT NULL,
  `filename`    VARCHAR(300)   NOT NULL,
  `original_name` VARCHAR(300) NOT NULL,
  `mime_type`   VARCHAR(100)   NOT NULL,
  `media_type`  ENUM('image','video','document') NOT NULL DEFAULT 'image',
  `file_size`   INT UNSIGNED   NOT NULL DEFAULT 0,
  `width`       SMALLINT UNSIGNED NULL,
  `height`      SMALLINT UNSIGNED NULL,
  `duration`    INT UNSIGNED   NULL  COMMENT 'Duration in seconds (video only)',
  `path`        VARCHAR(500)   NOT NULL,
  `alt_text`    VARCHAR(500)   NULL,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uploader` (`uploader_id`),
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- site_settings  (key-value store for site configuration)
-- -------------------------------------------------------
CREATE TABLE `site_settings` (
  `setting_key`   VARCHAR(100)  NOT NULL,
  `setting_value` MEDIUMTEXT    NULL,
  `setting_group` VARCHAR(50)   NOT NULL DEFAULT 'general',
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- contact_messages
-- -------------------------------------------------------
CREATE TABLE `contact_messages` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(200)  NOT NULL,
  `email`      VARCHAR(255)  NOT NULL,
  `subject`    VARCHAR(300)  NOT NULL,
  `message`    TEXT          NOT NULL,
  `ip_address` VARCHAR(45)   NULL,
  `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- audit_logs
-- -------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `entity_type` VARCHAR(50)     NULL,
  `entity_id`   INT UNSIGNED    NULL,
  `details`     JSON            NULL,
  `ip_address`  VARCHAR(45)     NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`       (`user_id`),
  KEY `idx_action`     (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
