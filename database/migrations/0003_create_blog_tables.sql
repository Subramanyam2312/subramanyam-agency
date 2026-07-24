-- Blog: categories, tags, posts and the tag pivot.

CREATE TABLE `categories` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(120) NOT NULL,
  `slug`             VARCHAR(180) NOT NULL,
  `description`      VARCHAR(500) NULL DEFAULT NULL,
  `meta_title`       VARCHAR(180) NULL DEFAULT NULL,
  `meta_description` VARCHAR(300) NULL DEFAULT NULL,
  `sort_order`       INT NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categories_slug` (`slug`),
  KEY `idx_categories_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tags` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- content       : sanitized HTML from the editor
-- content_text  : plain-text mirror written on save. It backs FULLTEXT search and
--                 the reading-time estimate, so neither has to strip tags at query time.
CREATE TABLE `posts` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`             VARCHAR(200) NOT NULL,
  `slug`              VARCHAR(200) NOT NULL,
  `excerpt`           VARCHAR(500) NULL DEFAULT NULL,
  `content`           LONGTEXT NULL,
  `content_text`      LONGTEXT NULL,
  `featured_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `category_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `author_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
  `status`            ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  `published_at`      DATETIME NULL DEFAULT NULL,
  `reading_time`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `views`             INT UNSIGNED NOT NULL DEFAULT 0,
  `is_featured`       TINYINT(1) NOT NULL DEFAULT 0,
  `meta_title`        VARCHAR(180) NULL DEFAULT NULL,
  `meta_description`  VARCHAR(300) NULL DEFAULT NULL,
  `og_media_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `canonical_url`     VARCHAR(255) NULL DEFAULT NULL,
  `noindex`           TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_posts_slug` (`slug`),
  -- The hot path: every public listing filters on status and orders by published_at.
  KEY `idx_posts_status_published` (`status`, `published_at`),
  KEY `idx_posts_category` (`category_id`),
  KEY `idx_posts_author` (`author_id`),
  KEY `idx_posts_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_posts_search` (`title`, `excerpt`, `content_text`),
  CONSTRAINT `fk_posts_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_og_media` FOREIGN KEY (`og_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_tags` (
  `post_id` BIGINT UNSIGNED NOT NULL,
  `tag_id`  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `tag_id`),
  KEY `idx_post_tags_tag` (`tag_id`),
  CONSTRAINT `fk_post_tags_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
