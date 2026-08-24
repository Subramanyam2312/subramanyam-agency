-- The /about and /contact meta tags stop being hardcoded in their controllers.
--
-- Both pages had their title and description written as PHP string literals. That
-- made the copy deploy-only: changing a description meant editing a controller and
-- shipping, which is the wrong shape for text whose whole job is to be tuned against
-- Search Console. Every other page already reads its meta from a block; these two
-- were the exception.
--
-- It also leaked. The About description named real clients, and this repository is
-- public, so the client list was readable by anyone who cloned it. Content that
-- names customers belongs in the database, which is not published — not in source,
-- which is. Moving the field removes the whole class of mistake rather than fixing
-- one instance of it.
--
-- The controller defaults stay as a safety net and are deliberately generic: an
-- install with no value set gets a sensible description with no customer named.
--
-- home.meta_description is registered here too. HomeController already reads that
-- block, but nothing ever inserted it, so the field never appeared in Content ->
-- Page copy and the value it reads could not be edited. It ships empty, which
-- falls through to the controller's existing fallback — so behaviour is unchanged
-- and the field simply becomes editable.

INSERT INTO `page_blocks` (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`) VALUES
('about',   'meta_title',       'Search title (<title> tag)', 'text',     'About Subramanyam M N',   'SEO', 1),
('about',   'meta_description', 'Search description',         'textarea', 'Who I am and how I work: one person doing the strategy and the making, in Chennai and across Tamil-speaking markets.', 'SEO', 2),
('contact', 'meta_title',       'Search title (<title> tag)', 'text',     'Contact Subramanyam M N', 'SEO', 1),
('contact', 'meta_description', 'Search description',         'textarea', 'Get in touch with Subramanyam M N in Chennai. Tell me what you''re working on and I''ll reply within one business day — by email, phone or WhatsApp.', 'SEO', 2),
('home',    'meta_description', 'Search description',         'textarea', '', 'SEO', 2);
