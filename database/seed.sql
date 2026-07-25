-- Seed data: realistic placeholder content so the site looks finished on first load.
--
-- Assumes empty tables (run against a fresh database, or after --fresh). IDs are
-- explicit so the pivot rows and foreign keys below are deterministic.
--
-- Deliberately NOT seeded: media and client_logos. A media row has to point at a
-- file that actually exists on disk, and client_logos.media_id is NOT NULL with a
-- RESTRICT foreign key — seeding either would produce broken images. Both are
-- populated through the media library in Phase 3.

-- ---------------------------------------------------------------- settings

INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `group_name`) VALUES
('site_name',            'SUBRAMANYAM', 'text', 'general'),
('tagline',              'Performance marketing for brands that are done guessing', 'text', 'general'),
('footer_copy',          'A independent digital marketing studio building measurable growth for founder-led brands.', 'textarea', 'general'),
('maintenance_mode',     '0', 'boolean', 'general'),

('contact_email',        'you@example.com', 'text', 'contact'),
('contact_phone',        '', 'text', 'contact'),
('whatsapp_number',      '', 'text', 'contact'),
('address',              'Chennai, Tamil Nadu, India', 'textarea', 'contact'),
('business_hours',       'Monday to Friday, 10:00 to 18:00 IST', 'text', 'contact'),

('social_instagram',     '', 'url', 'social'),
('social_linkedin',      '', 'url', 'social'),
('social_x',             '', 'url', 'social'),
('social_youtube',       '', 'url', 'social'),

('seo_default_title',    'SUBRAMANYAM — Performance Marketing Studio', 'text', 'seo'),
('seo_default_description', 'SEO, paid media and conversion work for brands that need results they can measure. Based in Chennai, working with clients everywhere.', 'textarea', 'seo'),
('ga_measurement_id',    '', 'text', 'seo'),
('gtm_id',               '', 'text', 'seo'),
('search_console_token', '', 'text', 'seo'),
('robots_extra',         '', 'textarea', 'seo');

-- ---------------------------------------------------------------- page blocks

INSERT INTO `page_blocks` (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`) VALUES
('home', 'hero_headline',        'Hero headline',            'text',     'Marketing that earns its line on the P&L', 'Hero', 1),
('home', 'hero_subheadline',     'Hero subheadline',         'textarea', 'We build search, paid and conversion systems for founder-led brands — then show you exactly what each rupee returned.', 'Hero', 2),
('home', 'hero_cta_primary',     'Primary button label',     'text',     'Start a project', 'Hero', 3),
('home', 'hero_cta_primary_href','Primary button link',      'url',      '/contact', 'Hero', 4),
('home', 'hero_cta_secondary',   'Secondary button label',   'text',     'See the work', 'Hero', 5),
('home', 'hero_cta_secondary_href','Secondary button link',  'url',      '/work', 'Hero', 6),

('home', 'trust_bar_label',      'Client logo strip caption','text',     'Trusted by teams who measure everything', 'Trust', 10),

('home', 'process_heading',      'How we work heading',      'text',     'How we work', 'Process', 20),
('home', 'process_intro',        'How we work intro',        'textarea', 'No retainers that drift. Every engagement runs on the same four steps, and you see the numbers at each one.', 'Process', 21),

('home', 'stat_projects_value',  'Stat 1 value',             'number',   '48',  'Stats', 30),
('home', 'stat_projects_label',  'Stat 1 label',             'text',     'Projects delivered', 'Stats', 31),
('home', 'stat_clients_value',   'Stat 2 value',             'number',   '23',  'Stats', 32),
('home', 'stat_clients_label',   'Stat 2 label',             'text',     'Brands worked with', 'Stats', 33),
('home', 'stat_years_value',     'Stat 3 value',             'number',   '5',   'Stats', 34),
('home', 'stat_years_label',     'Stat 3 label',             'text',     'Years in practice', 'Stats', 35),
('home', 'stat_retention_value', 'Stat 4 value',             'number',   '92',  'Stats', 36),
('home', 'stat_retention_label', 'Stat 4 label',             'text',     'Percent client retention', 'Stats', 37),

('home', 'cta_heading',          'Closing CTA heading',      'text',     'Tell us what is not working', 'CTA', 40),
('home', 'cta_text',             'Closing CTA text',         'textarea', 'One call, no deck. We will tell you whether we can help and what it would take.', 'CTA', 41),
('home', 'cta_button',           'Closing CTA button label', 'text',     'Book a call', 'CTA', 42),

('about', 'story_heading',       'Story heading',            'text',     'Built the long way round', 'Story', 1),
('about', 'story_body',          'Story body',               'html',     '<p>SUBRAMANYAM started as one person doing SEO audits at night and grew into a small studio that still runs the same way: senior people on the work, no account-manager layer between you and the person changing things.</p><p>We take on a limited number of engagements at a time because performance work needs attention, not volume.</p>', 'Story', 2),
('about', 'values_heading',      'Values heading',           'text',     'How we operate', 'Values', 10),
('about', 'tools_heading',       'Tools marquee heading',    'text',     'Platforms we work in daily', 'Tools', 20),

('contact', 'heading',           'Contact heading',          'text',     'Start a conversation', 'Header', 1),
('contact', 'intro',             'Contact intro',            'textarea', 'Tell us what you are working on and what is in the way. We reply to everything within one working day.', 'Header', 2),
('contact', 'response_note',     'Response time note',       'text',     'Typical reply time: under 24 hours on working days', 'Header', 3);

-- ---------------------------------------------------------------- services

INSERT INTO `services` (`id`, `title`, `slug`, `icon`, `short_description`, `hero_headline`, `hero_subheadline`, `problem_statement`, `includes`, `process`, `deliverables`, `content`, `sort_order`, `is_featured`, `is_active`, `meta_title`, `meta_description`) VALUES
(1, 'Search Engine Optimisation', 'seo', 'search',
 'Technical fixes, content that ranks, and links that hold up — measured in pipeline, not positions.',
 'SEO that shows up in revenue, not just rankings',
 'Technical foundations, content built around real search demand, and the reporting to prove which pages earn money.',
 'Most SEO reports celebrate rankings for terms nobody buys from. Traffic climbs, the pipeline does not move, and nobody can explain the gap.',
 '["Full technical audit and remediation plan","Keyword and search-intent mapping to your offer","On-page optimisation across priority templates","Content briefs written for writers, not robots","Internal linking architecture","Digital PR and link acquisition","Monthly reporting tied to leads, not impressions"]',
 '[{"title":"Audit","description":"We crawl everything, pull Search Console and analytics history, and find what is actually holding the site back."},{"title":"Prioritise","description":"Every finding is scored by effort against revenue impact. You approve the order before anything is touched."},{"title":"Execute","description":"Fixes ship in batches. Content goes out on a schedule you can see."},{"title":"Compound","description":"Monthly review against pipeline. We keep what works and cut what does not."}]',
 '["Technical audit document","90-day prioritised roadmap","Keyword to URL mapping sheet","Monthly performance report","Quarterly strategy review"]',
 '<p>Search is the only channel where the asset keeps paying after you stop spending. That is also why it is slow, and why most agencies sell activity instead of outcomes.</p><p>We work the other way round: find the small number of pages that can realistically earn qualified traffic, make those genuinely the best answer available, and prove the result against your pipeline rather than a rank tracker.</p>',
 1, 1, 1,
 'SEO Services — Technical, Content and Digital PR',
 'Technical SEO, content strategy and link acquisition measured against pipeline rather than rankings. Chennai-based, working with brands everywhere.'),

(2, 'Paid Media', 'paid-media', 'target',
 'Google, Meta and LinkedIn campaigns built around unit economics, not vanity reach.',
 'Paid campaigns that respect your unit economics',
 'Structured accounts, tight creative testing, and spend that stops the moment it stops paying.',
 'Paid budgets quietly leak through broad match, untested creative and campaigns nobody has restructured in a year.',
 '["Account audit and restructure","Campaign architecture and naming conventions","Audience and keyword research","Creative testing framework","Landing page recommendations","Conversion tracking and offline import","Weekly optimisation and pacing"]',
 '[{"title":"Audit","description":"We map current spend against actual return and find where the waste is."},{"title":"Rebuild","description":"Accounts get restructured around how you actually make money."},{"title":"Test","description":"Creative and audiences run against a documented testing plan, not hunches."},{"title":"Scale","description":"Budget follows proven winners, with pacing reviewed weekly."}]',
 '["Account audit and restructure plan","Campaign build","Creative testing roadmap","Conversion tracking setup","Weekly pacing report"]',
 '<p>Paid media is the fastest way to learn what your market responds to and the fastest way to burn money doing it. The difference is almost always structure and measurement.</p><p>We rebuild accounts so that spend maps to margin, then run a documented testing cadence so every rupee of learning is kept.</p>',
 2, 1, 1,
 'Paid Media Management — Google, Meta and LinkedIn Ads',
 'Paid search and social campaigns structured around unit economics, with weekly optimisation and conversion tracking that survives audit.'),

(3, 'Social Media Management', 'social-media', 'share',
 'Consistent, on-brand publishing with a content engine that does not depend on inspiration.',
 'Social that compounds instead of resetting every month',
 'A publishing system, a real content calendar, and creative your audience actually stops for.',
 'Most brands post reactively, run out of ideas by week three, and end up with a feed that looks like three different companies.',
 '["Channel strategy and positioning","Monthly content calendar","Creative production and copywriting","Community management","Paid amplification of top performers","Monthly performance review"]',
 '[{"title":"Position","description":"We define what the brand sounds like and what it is allowed to talk about."},{"title":"Plan","description":"A month of content mapped before anything is designed."},{"title":"Produce","description":"Creative and copy delivered on schedule, approved in one pass."},{"title":"Review","description":"What performed, what did not, and what changes next month."}]',
 '["Channel strategy document","Monthly content calendar","Designed creative assets","Monthly analytics report"]',
 '<p>Consistency beats brilliance on social. The brands that win are the ones that show up in the same voice every week for a year.</p><p>We build the system that makes that possible, then run it.</p>',
 3, 0, 1,
 'Social Media Management Services',
 'Strategy, content calendars, creative production and community management for brands that need consistent publishing.'),

(4, 'Content and Creative', 'content-creative', 'pen',
 'Copy, design and video built for a specific channel and a specific job.',
 'Content made to do a job, not fill a calendar',
 'Editorial, design and video produced against a brief with a defined outcome.',
 'Content gets commissioned by volume and judged by feel, so nobody can say which piece did anything.',
 '["Editorial strategy and content pillars","Long-form articles and landing page copy","Design systems and campaign creative","Short-form video and motion","Photography art direction","Asset library and brand guidelines"]',
 '[{"title":"Brief","description":"Every asset starts with the job it has to do and how we will know it worked."},{"title":"Draft","description":"First version in front of you fast, before polish is wasted on the wrong direction."},{"title":"Refine","description":"One structured round of feedback, consolidated."},{"title":"Ship","description":"Final files, correctly formatted for every placement."}]',
 '["Content strategy document","Production calendar","Finished assets in all required formats","Brand and usage guidelines"]',
 '<p>Good content is expensive and bad content is worse than nothing, because it teaches your audience to scroll past you.</p><p>We produce less, brief it properly, and measure it.</p>',
 4, 0, 1,
 'Content Marketing and Creative Production',
 'Editorial strategy, copywriting, design and video production briefed against measurable outcomes.'),

(5, 'Web Design and Development', 'web-design-development', 'layout',
 'Fast, accessible sites that convert — built to be edited without calling a developer.',
 'Sites that load fast, convert, and stay editable',
 'Custom design and build with a CMS your team can actually run.',
 'Agency sites arrive slow, locked to a page builder, and impossible to change without a support ticket.',
 '["UX and information architecture","Custom design, no templates","Hand-built front end, Core Web Vitals first","Custom CMS with real editing workflows","Analytics and conversion tracking","Accessibility to WCAG 2.1 AA","Training and handover documentation"]',
 '[{"title":"Discover","description":"What the site has to do commercially, and for whom."},{"title":"Design","description":"Structure first, then visual direction, approved before build."},{"title":"Build","description":"Hand-coded, performance-budgeted, tested on real devices."},{"title":"Hand over","description":"You get the CMS, the documentation, and the training to run it."}]',
 '["Wireframes and design files","Production website","CMS with editor training","Performance and accessibility report"]',
 '<p>A marketing site is infrastructure. If it is slow, inaccessible or locked behind a developer, every other channel pays for it.</p><p>We build sites the team can actually maintain, and we hand over the keys properly.</p>',
 5, 1, 1,
 'Web Design and Development — Fast, Accessible, Editable',
 'Custom websites built for Core Web Vitals, accessibility and conversion, with a CMS your team can run without a developer.'),

(6, 'Analytics and CRO', 'analytics-cro', 'chart',
 'Tracking you can trust, and a testing programme that turns existing traffic into more revenue.',
 'Fix the measurement, then fix the funnel',
 'Trustworthy tracking, honest reporting, and structured experiments on the traffic you already have.',
 'Most teams optimise against numbers that are quietly wrong, which is worse than having no numbers at all.',
 '["Analytics audit and tracking plan","GA4 and server-side tagging","Dashboard and reporting build","Funnel and drop-off analysis","A/B testing programme","Session and heatmap review"]',
 '[{"title":"Verify","description":"We check every number you currently rely on and document what is broken."},{"title":"Instrument","description":"Tracking rebuilt to a written plan so it survives site changes."},{"title":"Diagnose","description":"Find where qualified traffic falls out of the funnel."},{"title":"Test","description":"Structured experiments, one variable at a time, called on significance."}]',
 '["Tracking plan document","Configured analytics and tag manager","Reporting dashboard","Test roadmap and result write-ups"]',
 '<p>Doubling conversion rate is usually cheaper than doubling traffic, and it makes every other channel more profitable at the same time.</p><p>It only works if the measurement is right, so that is where we always start.</p>',
 6, 0, 1,
 'Analytics Setup and Conversion Rate Optimisation',
 'GA4 and server-side tracking, dashboard reporting, funnel analysis and structured A/B testing programmes.');

INSERT INTO `service_faqs` (`service_id`, `question`, `answer`, `sort_order`) VALUES
(1, 'How long before SEO shows results?',
    'Technical fixes can move things within weeks. Content and authority work realistically takes three to six months to compound. Anyone promising faster is either buying links or counting the wrong numbers.', 1),
(1, 'Do you guarantee first-page rankings?',
    'No, and neither can anyone else honestly. We commit to the work, the reporting and the review cadence. Ranking guarantees are usually made against terms that were already ranking.', 2),
(1, 'Will you work with our existing content team?',
    'Yes. We often supply briefs and strategy while an in-house team writes. That is usually the cheapest way to run it.', 3),
(2, 'What is the minimum ad budget you work with?',
    'Below roughly a lakh a month in media spend, management fees eat too much of the budget to make sense. We will tell you if that is the case rather than take the retainer.', 1),
(2, 'Do we own the ad accounts?',
    'Always. Accounts are created under your business manager, and access stays with you if we stop working together.', 2),
(5, 'Can we edit the site ourselves afterwards?',
    'That is the point. Every site ships with a CMS covering all editable copy, imagery and page content, plus training and written documentation.', 1),
(5, 'Do you work with WordPress?',
    'We maintain existing WordPress sites, but new builds are custom. It gives better performance, a smaller attack surface and no plugin dependency.', 2),
(6, 'Is our current analytics setup probably wrong?',
    'In most audits we run, at least one primary conversion is either double-counted or missing entirely. It is worth checking before you optimise against it.', 1);

-- ---------------------------------------------------------------- FAQ

INSERT INTO `faqs` (`question`, `answer`, `group_name`, `sort_order`, `is_active`) VALUES
('How do engagements usually start?', 'With a paid discovery block. We audit what exists, agree the priorities, and produce a roadmap. If you take the roadmap elsewhere, that is fine — you own it.', 'Working together', 1, 1),
('Do you work on retainer or per project?', 'Both. Search, paid and social run better as retainers because they compound. Websites, audits and tracking builds are fixed-scope projects.', 'Working together', 2, 1),
('Who actually does the work?', 'The people you meet in the first call. We stay deliberately small so there is no handover to a junior team after signing.', 'Working together', 3, 1),
('What is the minimum commitment?', 'Three months on retainers, because anything shorter cannot show a real result. Projects run to their scope.', 'Working together', 4, 1),
('How do you report?', 'A monthly report tied to pipeline and revenue, plus a live dashboard you can open any time. No vanity metrics unless you ask for them.', 'Reporting', 10, 1),
('Will we get access to all accounts?', 'Yes. Every ad account, analytics property and tool is created under your ownership with us added as users.', 'Reporting', 11, 1),
('What happens if it is not working?', 'We say so in the monthly review, with the numbers. If we cannot fix it, we would rather end the engagement than keep billing.', 'Reporting', 12, 1),
('How is pricing structured?', 'Retainers are a flat monthly fee based on scope, not a percentage of ad spend — that incentive is backwards. Projects are quoted fixed.', 'Commercials', 20, 1),
('Do you charge a percentage of ad spend?', 'No. It rewards us for spending more of your money, which is not the job.', 'Commercials', 21, 1),
('Do you work with clients outside India?', 'Yes, regularly. Most communication is asynchronous with a weekly call scheduled to suit your timezone.', 'Commercials', 22, 1);

-- ---------------------------------------------------------------- testimonials

INSERT INTO `testimonials` (`quote`, `author_name`, `author_role`, `company`, `rating`, `is_featured`, `is_active`, `sort_order`) VALUES
('They found four tracking errors in the first week that had been quietly misreporting our best channel for over a year. Everything after that was built on numbers we could finally trust.', 'Priya Raghavan', 'Head of Growth', 'Meridian Labs', 5, 1, 1, 1),
('The first report actually told us which pages made money. Nobody had done that before — we had been optimising the wrong half of the site.', 'Arun Karthik', 'Founder', 'Northbound Supply', 5, 1, 1, 2),
('We came in expecting a rebrand pitch and got a spreadsheet showing exactly where the budget was leaking. Refreshing, if slightly uncomfortable.', 'Deepa Menon', 'Marketing Director', 'Kestrel Interiors', 5, 1, 1, 3),
('Site went from four seconds to under one, and our team can update it without raising a ticket. That second part changed how we work.', 'Vikram Shah', 'Operations Lead', 'Anchor Logistics', 5, 0, 1, 4),
('Straight answers, including the ones we did not want. They told us to pause a channel that was costing us money, which cut their own fee.', 'Sneha Iyer', 'Co-founder', 'Verity Wellness', 5, 0, 1, 5);

-- ---------------------------------------------------------------- case studies
--
-- Intentionally empty. The placeholder work was removed at the owner's request;
-- real case studies are added through the CMS (Admin → Case studies). The /work
-- page, the home "Selected work" section and the per-service "proof" block all
-- hide themselves when there are none, so an empty table renders cleanly.

-- ---------------------------------------------------------------- timeline

INSERT INTO `timeline_entries` (`year`, `title`, `description`, `sort_order`, `is_active`) VALUES
('2021', 'Started freelancing', 'First SEO audits taken on at night alongside a full-time job. Three clients by the end of the year, all by referral.', 1, 1),
('2022', 'Went full time', 'Left employment to work on the studio properly. Added paid media after too many clients asked for it.', 2, 1),
('2023', 'First retained clients', 'Moved from project work to monthly retainers, which made real compounding results possible for the first time.', 3, 1),
('2024', 'Added build capability', 'Started building the websites rather than handing recommendations to someone else and hoping.', 4, 1),
('2025', 'Analytics practice', 'Formalised tracking and CRO as a standalone service after finding broken measurement in nearly every audit.', 5, 1),
('Today', 'Deliberately small', 'A limited number of engagements at a time, senior people on every account, no plans to change that.', 6, 1);

-- ---------------------------------------------------------------- blog

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `sort_order`) VALUES
(1, 'SEO', 'seo', 'Search strategy, technical fixes and content that earns its place.', 1),
(2, 'Paid Media', 'paid-media', 'Campaign structure, creative testing and spend discipline.', 2),
(3, 'Analytics', 'analytics', 'Measurement, tracking and reporting you can defend.', 3),
(4, 'Studio Notes', 'studio-notes', 'How we work, and what we are learning.', 4);

INSERT INTO `tags` (`id`, `name`, `slug`) VALUES
(1, 'Technical SEO', 'technical-seo'),
(2, 'Content Strategy', 'content-strategy'),
(3, 'Google Ads', 'google-ads'),
(4, 'Meta Ads', 'meta-ads'),
(5, 'GA4', 'ga4'),
(6, 'Conversion Rate', 'conversion-rate'),
(7, 'Core Web Vitals', 'core-web-vitals'),
(8, 'Reporting', 'reporting');

INSERT INTO `posts` (`id`, `title`, `slug`, `excerpt`, `content`, `content_text`, `category_id`, `author_id`, `status`, `published_at`, `reading_time`, `is_featured`, `meta_title`, `meta_description`) VALUES
(1, 'Your conversion tracking is probably lying to you', 'conversion-tracking-is-lying-to-you',
 'In almost every analytics audit we run, at least one primary conversion is double-counted or missing. Here is how to check yours in an afternoon.',
 '<p>Before you optimise anything, the number you are optimising towards has to be right. In practice it usually is not.</p><h2>The three failures we find most</h2><p>Duplicate firing is the most common: a thank-you page that reloads, or a tag installed both in Tag Manager and hard-coded in the template. Both fire, both count, and reported conversions run roughly double reality.</p><p>The second is attribution drift, where a channel gets credit because it was the last touch on a journey it had nothing to do with starting.</p><p>The third is silent breakage. A developer renames a CSS class, a click trigger stops matching, and a conversion quietly reports zero for five weeks before anyone notices.</p><h2>How to check</h2><p>Fire a real conversion yourself and watch it arrive. Compare the analytics count against your actual sales records for the same period. If those two numbers disagree by more than a few percent, stop optimising until you know why.</p>',
 'Before you optimise anything, the number you are optimising towards has to be right. In practice it usually is not. The three failures we find most. Duplicate firing is the most common: a thank-you page that reloads, or a tag installed both in Tag Manager and hard-coded in the template. Both fire, both count, and reported conversions run roughly double reality. The second is attribution drift, where a channel gets credit because it was the last touch on a journey it had nothing to do with starting. The third is silent breakage. A developer renames a CSS class, a click trigger stops matching, and a conversion quietly reports zero for five weeks before anyone notices. How to check. Fire a real conversion yourself and watch it arrive. Compare the analytics count against your actual sales records for the same period. If those two numbers disagree by more than a few percent, stop optimising until you know why.',
 3, NULL, 'published', '2026-06-12 09:30:00', 4, 1,
 'Your Conversion Tracking Is Probably Lying To You',
 'The three tracking failures we find in nearly every analytics audit, and how to check your own setup in an afternoon.'),

(2, 'Why we deleted 60 blog posts and traffic went up', 'why-we-deleted-60-blog-posts',
 'Thin content does not just fail to rank. It actively drags down the pages that could.',
 '<p>A client came to us with 74 blog posts and flat organic growth over two years. We deleted or consolidated 60 of them. Six months later qualified enquiries had tripled.</p><h2>Thin content is not neutral</h2><p>Every low-value page competes with your good pages for crawl budget, dilutes internal link equity, and gives search engines a weaker overall impression of the site.</p><h2>What we kept</h2><p>Twelve substantial guides, each built from the strongest parts of four or five thin posts, each mapped to something the business actually sells. Everything else was redirected to its closest surviving relative.</p><p>Total sessions fell by roughly a fifth. Enquiries went up 214 percent. Sessions were never the goal.</p>',
 'A client came to us with 74 blog posts and flat organic growth over two years. We deleted or consolidated 60 of them. Six months later qualified enquiries had tripled. Thin content is not neutral. Every low-value page competes with your good pages for crawl budget, dilutes internal link equity, and gives search engines a weaker overall impression of the site. What we kept. Twelve substantial guides, each built from the strongest parts of four or five thin posts, each mapped to something the business actually sells. Everything else was redirected to its closest surviving relative. Total sessions fell by roughly a fifth. Enquiries went up 214 percent. Sessions were never the goal.',
 1, NULL, 'published', '2026-06-28 09:30:00', 5, 1,
 'Why We Deleted 60 Blog Posts And Traffic Went Up',
 'Consolidating thin content into fewer substantial guides tripled qualified enquiries while total sessions fell.'),

(3, 'Percentage-of-spend pricing is a broken incentive', 'percentage-of-spend-pricing-is-broken',
 'If your agency earns more when you spend more, you have hired someone whose interests diverge from yours the moment scaling stops working.',
 '<p>The standard agency model takes a percentage of media spend. It is easy to explain and easy to bill. It is also structurally backwards.</p><h2>Where it breaks</h2><p>The moment the right recommendation is to reduce spend, making that recommendation costs the agency money. Most will not make it. Not through dishonesty, usually — just the quiet weight of an incentive pulling one way for long enough.</p><h2>What we do instead</h2><p>A flat monthly fee based on scope. If the correct call is to pause a channel, that conversation costs us nothing, so we can have it honestly.</p>',
 'The standard agency model takes a percentage of media spend. It is easy to explain and easy to bill. It is also structurally backwards. Where it breaks. The moment the right recommendation is to reduce spend, making that recommendation costs the agency money. Most will not make it. Not through dishonesty, usually - just the quiet weight of an incentive pulling one way for long enough. What we do instead. A flat monthly fee based on scope. If the correct call is to pause a channel, that conversation costs us nothing, so we can have it honestly.',
 4, NULL, 'published', '2026-07-09 09:30:00', 3, 0,
 'Percentage-Of-Spend Agency Pricing Is A Broken Incentive',
 'Why we charge a flat monthly fee instead of a percentage of media spend, and what that changes about the advice you get.'),

(4, 'Core Web Vitals: what actually moves the needle', 'core-web-vitals-what-actually-matters',
 'Most Core Web Vitals advice is a checklist of micro-optimisations. In practice, three things account for nearly all of the score.',
 '<p>Teams spend weeks shaving kilobytes off JavaScript while a single unoptimised hero image quietly owns their Largest Contentful Paint.</p><h2>The three that matter</h2><p>First, whatever renders largest above the fold. Usually a hero image or heading. Serve it in a modern format, at the right dimensions, and never lazy-load it.</p><p>Second, layout shift from images and embeds without reserved dimensions. Width and height attributes cost nothing and fix most of it.</p><p>Third, third-party scripts. Every tag manager container, chat widget and analytics script competes with your content for the main thread.</p><p>Fix those three before touching anything else.</p>',
 'Teams spend weeks shaving kilobytes off JavaScript while a single unoptimised hero image quietly owns their Largest Contentful Paint. The three that matter. First, whatever renders largest above the fold. Usually a hero image or heading. Serve it in a modern format, at the right dimensions, and never lazy-load it. Second, layout shift from images and embeds without reserved dimensions. Width and height attributes cost nothing and fix most of it. Third, third-party scripts. Every tag manager container, chat widget and analytics script competes with your content for the main thread. Fix those three before touching anything else.',
 1, NULL, 'published', '2026-07-18 09:30:00', 4, 0,
 'Core Web Vitals — What Actually Moves The Needle',
 'Three fixes account for nearly all of a Core Web Vitals score. Do these before any micro-optimisation.'),

(5, 'A testing framework that survives contact with reality', 'ab-testing-framework-that-survives',
 'Most A/B testing programmes die because they test too many things at once and call results too early.',
 '<p>Draft in progress.</p>',
 'Draft in progress.',
 3, NULL, 'draft', NULL, 1, 0, NULL, NULL),

(6, 'What we learned running paid social for six months', 'paid-social-six-month-review',
 'A candid review of what worked, what did not, and what we would do differently.',
 '<p>Scheduled for publication.</p>',
 'Scheduled for publication.',
 2, NULL, 'scheduled', '2026-08-05 09:30:00', 6, 0, NULL, NULL);

INSERT INTO `post_tags` (`post_id`, `tag_id`) VALUES
(1, 5), (1, 8), (1, 6),
(2, 1), (2, 2),
(3, 8),
(4, 7), (4, 1),
(5, 6),
(6, 4);

-- Seed posts are inserted with no author, because the admin account is created
-- separately by scripts/create-admin.php and may not exist yet — hardcoding an id
-- here makes the seed fail outright on a genuinely fresh database.
--
-- This statement attributes them when the account already exists, and is a no-op
-- when it does not. For the usual order (migrate --seed first, admin second),
-- create-admin.php performs the same attribution when it creates the first admin.
UPDATE `posts`
SET `author_id` = (
    SELECT `id` FROM `users`
    WHERE `role` = 'admin' AND `deleted_at` IS NULL
    ORDER BY `id` LIMIT 1
)
WHERE `author_id` IS NULL;
