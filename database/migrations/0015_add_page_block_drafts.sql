-- Draft/publish for page copy.
--
-- Posts, case studies, services, FAQs and testimonials already carry their own
-- publish state. Page blocks did not: editing the About page changed the live
-- site the moment it was saved, with no way to work on wording first.
--
-- `value` stays the published copy — that is what the public site reads, so this
-- migration changes nothing about what visitors see. `draft_value` holds unsaved
-- work: NULL means the block has no pending edit. Publishing copies draft over
-- value and clears it; discarding just clears it.

ALTER TABLE `page_blocks`
    ADD COLUMN `draft_value` LONGTEXT NULL DEFAULT NULL AFTER `value`;

-- No index on draft_value. An expression index would need MySQL 8, and Hostinger
-- runs MariaDB, which cannot parse one — the dump would fail to import. The table
-- holds a couple of hundred rows, so counting pending drafts is a trivial scan.
