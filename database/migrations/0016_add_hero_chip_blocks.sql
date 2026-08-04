-- Hero chips become editable page copy.
--
-- The three chips floating beside the founder portrait were hardcoded with
-- placeholder figures (+180%, 3.1x, -42%) that read as real client results but
-- were never measured. They ship instead as plain capability labels, and move
-- into page_blocks so a real, verified number can replace one at any time from
-- Content -> Page copy without touching a template.
--
-- Emptying a chip's value removes it from the hero, so all three can be dropped.

INSERT INTO `page_blocks` (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`) VALUES
('home', 'hero_chip_1_value', 'Chip 1 headline', 'text', 'SEO',        'Hero chips', 20),
('home', 'hero_chip_1_label', 'Chip 1 caption',  'text', 'built to compound', 'Hero chips', 21),
('home', 'hero_chip_2_value', 'Chip 2 headline', 'text', 'Creative',   'Hero chips', 22),
('home', 'hero_chip_2_label', 'Chip 2 caption',  'text', 'that earns attention', 'Hero chips', 23),
('home', 'hero_chip_3_value', 'Chip 3 headline', 'text', 'Strategy',   'Hero chips', 24),
('home', 'hero_chip_3_label', 'Chip 3 caption',  'text', 'before tactics', 'Hero chips', 25);
