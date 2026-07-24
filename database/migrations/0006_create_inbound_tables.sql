-- Inbound: contact form submissions and newsletter signups.
-- Neither table stores a raw IP address; ip_hash is an HMAC keyed with APP_KEY.

CREATE TABLE `contact_submissions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150) NOT NULL,
  `email`        VARCHAR(191) NOT NULL,
  `phone`        VARCHAR(40) NULL DEFAULT NULL,
  `company`      VARCHAR(150) NULL DEFAULT NULL,
  `service_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `budget_range` VARCHAR(60) NULL DEFAULT NULL,
  `message`      TEXT NOT NULL,
  `ip_hash`      CHAR(64) NULL DEFAULT NULL,
  `user_agent`   VARCHAR(255) NULL DEFAULT NULL,
  `referrer`     VARCHAR(255) NULL DEFAULT NULL,
  `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
  `is_spam`      TINYINT(1) NOT NULL DEFAULT 0,
  `replied_at`   DATETIME NULL DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`   DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_submissions_unread` (`is_read`, `created_at`),
  KEY `idx_submissions_service` (`service_id`),
  KEY `idx_submissions_deleted` (`deleted_at`),
  CONSTRAINT `fk_submissions_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `newsletter_subscribers` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`              VARCHAR(191) NOT NULL,
  `source`             VARCHAR(40) NOT NULL DEFAULT 'footer',
  `confirm_token_hash` CHAR(64) NULL DEFAULT NULL,
  `confirmed_at`       DATETIME NULL DEFAULT NULL,
  `unsubscribed_at`    DATETIME NULL DEFAULT NULL,
  `ip_hash`            CHAR(64) NULL DEFAULT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_subscribers_email` (`email`),
  KEY `idx_subscribers_confirmed` (`confirmed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
