-- The homepage title tag stops borrowing the hero headline.
--
-- HomeController used to pass `home.hero_headline` straight through as the meta
-- title, so the <title> read "Marketing that earns its line on the P&L". That is
-- deliberate positioning copy and it stays as the H1 — but as a title tag it names
-- no service and no city, which left the strongest on-page relevance signal on the
-- site saying nothing Google could match a search against.
--
-- Splitting it into its own block keeps both editable and independent: the headline
-- can stay as bold as it likes without dragging the title with it.
--
-- Leaving the value empty falls back to the default in HomeController, so clearing
-- this field degrades to a sensible title rather than an empty one.

INSERT INTO `page_blocks` (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`) VALUES
('home', 'meta_title', 'Search title (<title> tag)', 'text', 'Digital Marketing & SEO Consultant in Chennai', 'SEO', 1);
