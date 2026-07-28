-- Plugins hub: settings for each plugin, plus the tables the Traffic Manager needs.
--
-- Unlike a static-site CMS, this app has a real server — so caching, spam filtering
-- and traffic counting are implemented for real rather than delegated to the host.

-- Per-day traffic aggregates. One row per day keeps the dashboard query O(days).
CREATE TABLE `traffic_daily` (
  `day`      DATE NOT NULL,
  `views`    INT UNSIGNED NOT NULL DEFAULT 0,
  `visitors` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-path per-day counts, for the "top pages" table.
CREATE TABLE `traffic_paths` (
  `id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `day`   DATE NOT NULL,
  `path`  VARCHAR(191) NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_path_day` (`day`, `path`),
  KEY `idx_traffic_paths_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-referrer-host per-day counts, for "where visitors come from".
CREATE TABLE `traffic_referrers` (
  `id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `day`   DATE NOT NULL,
  `host`  VARCHAR(191) NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ref_day` (`day`, `host`),
  KEY `idx_traffic_ref_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per (day, hashed visitor) so unique visitors can be counted without
-- ever storing a raw IP. Pruned to 90 days by the cron sweep.
CREATE TABLE `traffic_visitors` (
  `day`     DATE NOT NULL,
  `visitor` CHAR(64) NOT NULL,
  PRIMARY KEY (`day`, `visitor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plugin configuration, all under the 'plugins' settings group so the hub reads
-- and writes them uniformly. Booleans are '0'/'1'.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `group_name`) VALUES
-- SEO (RankMath-style analyser — already built for posts; toggle surfaces it)
('plugin_seo_enabled',       '1', 'boolean', 'plugins'),
-- Analytics
('plugin_analytics_enabled', '1', 'boolean', 'plugins'),
('meta_pixel_id',            '',  'text',    'plugins'),
('meta_pixel_enabled',       '0', 'boolean', 'plugins'),
('custom_head_enabled',      '0', 'boolean', 'plugins'),
('custom_head_code',         '',  'textarea','plugins'),
('custom_body_enabled',      '0', 'boolean', 'plugins'),
('custom_body_code',         '',  'textarea','plugins'),
-- Traffic Manager
('plugin_traffic_enabled',   '1', 'boolean', 'plugins'),
-- Spam protection
('plugin_spam_enabled',      '1', 'boolean', 'plugins'),
('akismet_key',              '',  'text',    'plugins'),
('spam_max_links',           '4', 'number',  'plugins'),
-- LiteSpeed / page cache (off by default — the owner turns it on when ready)
('plugin_cache_enabled',     '0', 'boolean', 'plugins'),
('cache_ttl',                '3600', 'number','plugins');
