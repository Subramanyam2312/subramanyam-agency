-- Users, sessions-adjacent auth tables and the rate-limit counter.
-- avatar_media_id is declared here but its foreign key is added in 0002, because
-- users and media reference each other and one of them has to exist first.

CREATE TABLE `users` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(120) NOT NULL,
  `email`            VARCHAR(191) NOT NULL,
  `password_hash`    VARCHAR(255) NOT NULL,
  `role`             ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  `avatar_media_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`    DATETIME NULL DEFAULT NULL,
  `failed_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`     DATETIME NULL DEFAULT NULL,
  `reset_token_hash` CHAR(64) NULL DEFAULT NULL,
  `reset_expires_at` DATETIME NULL DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_reset` (`reset_token_hash`),
  KEY `idx_users_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per remembered device, so "log out everywhere" is a single DELETE and a
-- stolen cookie can be revoked without forcing a password change.
CREATE TABLE `remember_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `ip_hash`    CHAR(64) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_remember_token` (`token_hash`),
  KEY `idx_remember_expires` (`expires_at`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only the SHA-256 of a token is stored. The plaintext is shown once, at creation.
CREATE TABLE `api_tokens` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `prefix`       CHAR(8) NOT NULL,
  `token_hash`   CHAR(64) NOT NULL,
  `abilities`    JSON NULL DEFAULT NULL,
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `expires_at`   DATETIME NULL DEFAULT NULL,
  `revoked_at`   DATETIME NULL DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_token_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window counters for login, password reset, contact form and API.
-- bucket is a SHA-256 of the real key, so no raw IP or email is stored.
CREATE TABLE `rate_limits` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`     CHAR(64) NOT NULL,
  `hits`       INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rate_bucket` (`bucket`),
  KEY `idx_rate_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
