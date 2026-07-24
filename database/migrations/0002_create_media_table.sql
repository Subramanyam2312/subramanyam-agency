-- Media library. `variants` holds the generated WebP widths as
-- {"320":"filename-320.webp", "640":"..."} so a template can pick a srcset without
-- touching the filesystem.

CREATE TABLE `media` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`      VARCHAR(191) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `path`          VARCHAR(255) NOT NULL,
  `mime`          VARCHAR(100) NOT NULL,
  `size`          INT UNSIGNED NOT NULL DEFAULT 0,
  `width`         SMALLINT UNSIGNED NULL DEFAULT NULL,
  `height`        SMALLINT UNSIGNED NULL DEFAULT NULL,
  `alt_text`      VARCHAR(255) NULL DEFAULT NULL,
  `caption`       VARCHAR(255) NULL DEFAULT NULL,
  `variants`      JSON NULL DEFAULT NULL,
  `uploaded_by`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_media_filename` (`filename`),
  KEY `idx_media_mime` (`mime`),
  KEY `idx_media_created` (`created_at`),
  KEY `idx_media_deleted` (`deleted_at`),
  CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Closes the users <-> media loop now that both tables exist.
ALTER TABLE `users`
  ADD KEY `idx_users_avatar` (`avatar_media_id`),
  ADD CONSTRAINT `fk_users_avatar` FOREIGN KEY (`avatar_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;
