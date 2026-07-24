-- Standalone content modules: FAQ, testimonials, case studies, timeline,
-- client logos and the editable page copy blocks.

CREATE TABLE `faqs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question`   VARCHAR(300) NOT NULL,
  `answer`     TEXT NOT NULL,
  `group_name` VARCHAR(80) NOT NULL DEFAULT 'General',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_faqs_group_order` (`group_name`, `sort_order`),
  KEY `idx_faqs_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `testimonials` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quote`       TEXT NOT NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `author_role` VARCHAR(120) NULL DEFAULT NULL,
  `company`     VARCHAR(120) NULL DEFAULT NULL,
  `media_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `rating`      TINYINT UNSIGNED NULL DEFAULT NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_testimonials_active_order` (`is_active`, `sort_order`),
  KEY `idx_testimonials_deleted` (`deleted_at`),
  CONSTRAINT `fk_testimonials_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- metrics: [{"label":"Organic traffic","value":"+240%"}] — the results tiles.
-- gallery: [12, 15, 18] — media ids, ordered.
CREATE TABLE `case_studies` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(200) NOT NULL,
  `slug`             VARCHAR(200) NOT NULL,
  `client_name`      VARCHAR(150) NULL DEFAULT NULL,
  `industry`         VARCHAR(120) NULL DEFAULT NULL,
  `challenge`        TEXT NULL,
  `solution`         TEXT NULL,
  `results`          TEXT NULL,
  `metrics`          JSON NULL DEFAULT NULL,
  `cover_media_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `gallery`          JSON NULL DEFAULT NULL,
  `service_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `status`           ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at`     DATETIME NULL DEFAULT NULL,
  `is_featured`      TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`       INT NOT NULL DEFAULT 0,
  `meta_title`       VARCHAR(180) NULL DEFAULT NULL,
  `meta_description` VARCHAR(300) NULL DEFAULT NULL,
  `og_media_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
  `noindex`          TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_case_studies_slug` (`slug`),
  KEY `idx_case_studies_status` (`status`, `published_at`),
  KEY `idx_case_studies_service` (`service_id`),
  KEY `idx_case_studies_deleted` (`deleted_at`),
  CONSTRAINT `fk_case_studies_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_case_studies_cover` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_case_studies_og_media` FOREIGN KEY (`og_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `year` is a string so ranges like "2019–21" and labels like "Today" both work.
CREATE TABLE `timeline_entries` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year`        VARCHAR(9) NOT NULL,
  `title`       VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timeline_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RESTRICT on the media FK: a logo row without an image is a broken marquee cell,
-- so deleting an in-use logo asset has to be refused rather than silently nulled.
CREATE TABLE `client_logos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `media_id`   BIGINT UNSIGNED NOT NULL,
  `link_url`   VARCHAR(255) NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_logos_active_order` (`is_active`, `sort_order`),
  KEY `idx_client_logos_media` (`media_id`),
  CONSTRAINT `fk_client_logos_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every editable string on Home/About/Contact. The admin screen renders its form
-- from these rows, so adding a new editable headline is an INSERT, not a code change.
CREATE TABLE `page_blocks` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key`   VARCHAR(40) NOT NULL,
  `block_key`  VARCHAR(60) NOT NULL,
  `label`      VARCHAR(150) NOT NULL,
  `type`       ENUM('text','textarea','html','number','image','url') NOT NULL DEFAULT 'text',
  `value`      TEXT NULL,
  `media_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `group_name` VARCHAR(80) NOT NULL DEFAULT 'General',
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page_blocks` (`page_key`, `block_key`),
  KEY `idx_page_blocks_page` (`page_key`, `sort_order`),
  CONSTRAINT `fk_page_blocks_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_page_blocks_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
