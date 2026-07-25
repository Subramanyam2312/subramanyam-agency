-- Focus keyword and stored SEO score for the RankMath-style analyser.
--
-- The score is computed on save by App\Core\SeoAnalyzer and cached here so the
-- posts list and dashboard can show it without re-running the analysis per row.
-- The live editor panel calls the analyser directly and does not read this column.

ALTER TABLE `posts`
  ADD COLUMN `focus_keyword` VARCHAR(191) NULL DEFAULT NULL AFTER `meta_description`,
  ADD COLUMN `seo_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `focus_keyword`;
