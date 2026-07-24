-- Privacy and terms as editable page copy.
--
-- Stored as page_blocks rather than hardcoded templates because legal text
-- changes for reasons that have nothing to do with deployments — and the person
-- who needs to change it should not need a developer.
--
-- The starting text is a plain-language skeleton that reflects what this
-- application actually does (hashes IPs, stores enquiries, sets one session
-- cookie). It is a starting point for review, NOT legal advice, and the copy
-- says so where the site owner will see it.

INSERT IGNORE INTO `page_blocks` (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`) VALUES
('privacy', 'heading', 'Page heading', 'text', 'Privacy policy', 'Content', 1),
('privacy', 'updated', 'Last updated', 'text', 'July 2026', 'Content', 2),
('privacy', 'body', 'Policy text', 'html',
'<p>This page explains what this website collects and why. Have it reviewed by someone qualified before you rely on it.</p><h2>What we collect</h2><p>If you send us an enquiry we store the name, email address, phone number, company, service and budget you chose, and your message. If you subscribe to the newsletter we store your email address and the page you subscribed from.</p><p>We store a one-way hash of your IP address rather than the address itself. It is used only to rate limit forms and cannot be reversed back into an address.</p><h2>Cookies</h2><p>This site sets one cookie, and only for people signing in to the content portal. There are no advertising or tracking cookies. If analytics is enabled it is configured through the settings screen and disclosed here before it goes live.</p><h2>How long we keep it</h2><p>Enquiries are kept while the conversation is live and for as long as we need them for our records. Newsletter subscriptions are kept until you unsubscribe, at which point the record is deleted outright rather than flagged.</p><h2>Sharing</h2><p>We do not sell your data. Enquiries are delivered to our own inbox through an email provider, and that is the only third party involved in handling them.</p><h2>Your rights</h2><p>Ask us for a copy of what we hold about you, or ask us to delete it, and we will do it. Use the contact details on the contact page.</p>',
'Content', 3),

('terms', 'heading', 'Page heading', 'text', 'Terms of use', 'Content', 1),
('terms', 'updated', 'Last updated', 'text', 'July 2026', 'Content', 2),
('terms', 'body', 'Terms text', 'html',
'<p>These terms cover use of this website. They do not cover client engagements, which are governed by the agreement signed for that work. Have them reviewed before you rely on them.</p><h2>Using this site</h2><p>You may read, share and link to anything published here. You may not copy the content wholesale and publish it as your own, or attempt to disrupt or gain unauthorised access to the site.</p><h2>Accuracy</h2><p>Articles reflect our view at the time of writing. Search engines, ad platforms and analytics tools change constantly, so treat anything technical here as a starting point rather than current advice.</p><h2>Case studies</h2><p>Results described in case studies were achieved in a specific context with specific constraints. They are illustrative of the work, not a projection of what any other engagement will produce.</p><h2>Links</h2><p>Where we link to other sites we do not control what is on them and are not responsible for their content.</p><h2>Getting in touch</h2><p>Questions about these terms go to the address on the contact page.</p>',
'Content', 3);
