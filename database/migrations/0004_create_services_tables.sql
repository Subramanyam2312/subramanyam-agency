-- Services and their per-service FAQs.
--
-- includes / process / deliverables are JSON because they are repeatable lists
-- edited as a single unit in one admin form and never queried across rows.
-- service_faqs is a real table because each entry is individually orderable and
-- feeds FAQPage JSON-LD on the service detail page.

CREATE TABLE `services` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`             VARCHAR(150) NOT NULL,
  `slug`              VARCHAR(180) NOT NULL,
  `icon`              VARCHAR(60) NULL DEFAULT NULL,
  `short_description` VARCHAR(400) NULL DEFAULT NULL,
  `hero_headline`     VARCHAR(200) NULL DEFAULT NULL,
  `hero_subheadline`  VARCHAR(400) NULL DEFAULT NULL,
  `problem_statement` TEXT NULL,
  `includes`          JSON NULL DEFAULT NULL,
  `process`           JSON NULL DEFAULT NULL,
  `deliverables`      JSON NULL DEFAULT NULL,
  `content`           LONGTEXT NULL,
  `image_media_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `sort_order`        INT NOT NULL DEFAULT 0,
  `is_featured`       TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `meta_title`        VARCHAR(180) NULL DEFAULT NULL,
  `meta_description`  VARCHAR(300) NULL DEFAULT NULL,
  `og_media_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `canonical_url`     VARCHAR(255) NULL DEFAULT NULL,
  `noindex`           TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_services_slug` (`slug`),
  KEY `idx_services_active_order` (`is_active`, `sort_order`),
  KEY `idx_services_deleted` (`deleted_at`),
  CONSTRAINT `fk_services_image` FOREIGN KEY (`image_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_services_og_media` FOREIGN KEY (`og_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_faqs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `question`   VARCHAR(300) NOT NULL,
  `answer`     TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_faqs_service` (`service_id`, `sort_order`),
  CONSTRAINT `fk_service_faqs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
