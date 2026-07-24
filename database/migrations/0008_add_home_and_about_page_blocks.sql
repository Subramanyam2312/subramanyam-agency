-- Page copy needed by the Home and About templates built in Phase 5.
--
-- These arrive as a migration rather than a seed edit so existing installs pick
-- them up too: seed.sql only runs on a fresh database, and the templates would
-- otherwise render section headings that the CMS has no field for.
--
-- INSERT IGNORE against the (page_key, block_key) unique index makes this safe to
-- re-run and safe on a database that already has some of these rows.

INSERT IGNORE INTO `page_blocks` (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`) VALUES
('home', 'hero_eyebrow', 'Hero eyebrow', 'text', 'Performance marketing studio', 'Hero', 0),

-- Optional self-hosted hero video. Left blank the hero uses its CSS motion layer,
-- which is what keeps the mobile LCP score intact.
('home', 'hero_video', 'Hero video URL', 'url', '', 'Hero', 7),
('home', 'hero_poster', 'Hero poster image', 'image', '', 'Hero', 8),

('home', 'process_step_1_title', 'Step 1 title', 'text', 'Audit', 'Process', 22),
('home', 'process_step_1_body',  'Step 1 description', 'textarea', 'We look at what you already have — traffic, spend, tracking, content — and find where the money is actually going.', 'Process', 23),
('home', 'process_step_2_title', 'Step 2 title', 'text', 'Prioritise', 'Process', 24),
('home', 'process_step_2_body',  'Step 2 description', 'textarea', 'Everything found gets scored by effort against revenue impact. You approve the order before anything is touched.', 'Process', 25),
('home', 'process_step_3_title', 'Step 3 title', 'text', 'Execute', 'Process', 26),
('home', 'process_step_3_body',  'Step 3 description', 'textarea', 'Work ships in batches on a schedule you can see, not in a black box that reports monthly.', 'Process', 27),
('home', 'process_step_4_title', 'Step 4 title', 'text', 'Compound', 'Process', 28),
('home', 'process_step_4_body',  'Step 4 description', 'textarea', 'Monthly review against pipeline. We keep what works, cut what does not, and tell you which is which.', 'Process', 29),

('home', 'services_heading', 'Services heading', 'text', 'What we do', 'Services', 14),
('home', 'services_intro',   'Services intro', 'textarea', 'Four disciplines that compound when run together, and stand up on their own when they are not.', 'Services', 15),

('home', 'work_heading', 'Selected work heading', 'text', 'Selected work', 'Work', 38),
('home', 'work_intro',   'Selected work intro', 'textarea', 'A few engagements where the numbers moved enough to be worth writing up.', 'Work', 39),

('home', 'testimonials_heading', 'Testimonials heading', 'text', 'What clients say', 'Testimonials', 43),

('home', 'blog_heading', 'Journal heading', 'text', 'From the journal', 'Journal', 44),
('home', 'blog_intro',   'Journal intro', 'textarea', 'Working notes on search, spend and measurement.', 'Journal', 45),

('about', 'value_1_title', 'Value 1 title', 'text', 'Say the uncomfortable thing', 'Values', 11),
('about', 'value_1_body',  'Value 1 description', 'textarea', 'If a channel is losing money we say so, even when the honest recommendation cuts our own fee.', 'Values', 12),
('about', 'value_2_title', 'Value 2 title', 'text', 'Measure before optimising', 'Values', 13),
('about', 'value_2_body',  'Value 2 description', 'textarea', 'Most teams optimise against numbers that are quietly wrong. We check the measurement first, every time.', 'Values', 14),
('about', 'value_3_title', 'Value 3 title', 'text', 'Senior people on the work', 'Values', 15),
('about', 'value_3_body',  'Value 3 description', 'textarea', 'The people in the first call are the people doing the work. There is no handover to a junior team after signing.', 'Values', 16),
('about', 'value_4_title', 'Value 4 title', 'text', 'Own everything you pay for', 'Values', 17),
('about', 'value_4_body',  'Value 4 description', 'textarea', 'Accounts, dashboards, documentation and roadmaps are created under your ownership and stay with you.', 'Values', 18);
