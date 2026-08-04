-- Site logo and browser icon, for Settings -> Appearance.
--
-- Both store a media library ID, not a path. The media library owns the file: it
-- generates the WebP renditions, it knows the dimensions, and it can report what
-- still references an image before you delete it. Storing a path here would fork
-- that ownership and leave the branding pointing at a file nothing else knows about.
--
-- Empty means "not set", which is a supported state rather than a broken one:
-- with no logo the header renders the site name as a wordmark, and with no icon
-- the page emits no icon link at all and the browser falls back to its default.

INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `group_name`, `updated_at`) VALUES
('site_logo_media_id', '', 'text', 'appearance', NOW()),
('site_icon_media_id', '', 'text', 'appearance', NOW())
ON DUPLICATE KEY UPDATE `group_name` = 'appearance';
