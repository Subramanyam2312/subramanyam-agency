-- Application firewall: IP blocklist and an event log.
--
-- These two tables store RAW IP addresses, unlike the rest of the app which keeps
-- only HMAC hashes for privacy. A blocklist you cannot read is unmanageable, and a
-- security log the owner cannot act on is pointless — so a WAF is the one place a
-- real address earns its keep. The trade is deliberate and documented in the
-- Tools -> Security panel.

CREATE TABLE `firewall_blocks` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         VARCHAR(45) NOT NULL,               -- IPv4 or IPv6
  `reason`     VARCHAR(255) NULL DEFAULT NULL,
  `source`     ENUM('manual','auto') NOT NULL DEFAULT 'auto',
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,  -- null = an automatic ban
  `expires_at` DATETIME NULL DEFAULT NULL,         -- null = permanent
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_firewall_ip` (`ip`),
  KEY `idx_firewall_expires` (`expires_at`),
  CONSTRAINT `fk_firewall_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `firewall_events` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         VARCHAR(45) NOT NULL,
  `method`     VARCHAR(10) NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `rule`       VARCHAR(40) NOT NULL,               -- sqli, xss, traversal, bad_agent, ip_block, flood
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `action`     ENUM('blocked','banned') NOT NULL DEFAULT 'blocked',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_firewall_events_created` (`created_at`),
  KEY `idx_firewall_events_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Firewall toggles live in settings so the owner can turn a misbehaving rule off
-- without a deploy. Master switch defaults ON; individual rules default ON too.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `group_name`) VALUES
('firewall_enabled',    '1', 'boolean', 'security'),
('firewall_signatures', '1', 'boolean', 'security'),
('firewall_agents',     '1', 'boolean', 'security'),
('firewall_flood',      '1', 'boolean', 'security');
