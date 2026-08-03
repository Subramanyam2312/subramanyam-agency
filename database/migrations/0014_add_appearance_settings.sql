-- Typography settings for Settings -> Appearance.
--
-- font_pairing selects one of the curated, self-hosted pairings in
-- config/fonts.php. fonts_source stays 'self' unless someone deliberately opts
-- into Google Fonts, which is the only setting here that causes the site to
-- request anything from a third party.

INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `group_name`, `updated_at`) VALUES
('font_pairing',        'instrument', 'text', 'appearance', NOW()),
('fonts_source',        'self',       'text', 'appearance', NOW()),
('font_google_display', '',           'text', 'appearance', NOW()),
('font_google_body',    '',           'text', 'appearance', NOW())
ON DUPLICATE KEY UPDATE `group_name` = 'appearance';
