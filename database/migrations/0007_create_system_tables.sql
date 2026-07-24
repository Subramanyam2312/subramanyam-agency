-- Settings and the audit trail.
--
-- The settings columns are `setting_key`/`setting_value` rather than `key`/`value`
-- because KEY is a reserved word in MySQL. Key/value rather than one wide row means
-- adding a setting is an INSERT instead of a migration.

CREATE TABLE `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `type`          VARCHAR(20) NOT NULL DEFAULT 'text',
  `group_name`    VARCHAR(40) NOT NULL DEFAULT 'general',
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `idx_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- api_token_id is recorded alongside user_id so a post created by the publishing
-- agent is attributable to the specific token that made it, not just its owner.
CREATE TABLE `activity_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
  `api_token_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `action`       VARCHAR(80) NOT NULL,
  `entity_type`  VARCHAR(60) NULL DEFAULT NULL,
  `entity_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `meta`         JSON NULL DEFAULT NULL,
  `ip_hash`      CHAR(64) NULL DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_entity` (`entity_type`, `entity_id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_activity_token` FOREIGN KEY (`api_token_id`) REFERENCES `api_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
