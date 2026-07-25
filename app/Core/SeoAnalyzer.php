<?php

declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMXPath;

/**
 * RankMath-style on-page SEO analysis.
 *
 * One analyser, server-side, used two ways: the post editor calls it live over a
 * JSON endpoint as the author types, and the controller calls it on save to cache
 * a score. Keeping a single implementation means the live panel and the stored
 * badge can never disagree — the thing a two-analyser (JS + PHP) setup gets wrong.
 *
 * It grades a page against a chosen focus keyword and returns a 0-100 score plus a
 * checklist, each item marked good / ok / bad with a plain-English message. The
 * weights sum to 100 and are documented inline so the scoring is auditable rather
 * than a black box.
 */
final class SeoAnalyzer
{
    /** Ideal bounds, matching common RankMath defaults. */
    private const TITLE_MAX      = 60;
    private const META_MIN       = 120;
    private const META_MAX       = 160;
    private const CONTENT_GOOD   = 600;   // words
    private const CONTENT_OK     = 300;
    private const DENSITY_MIN    = 0.5;   // percent
    private const DENSITY_MAX    = 2.5;

    /**
     * Words that make a headline pull — a small, defensible subset of the lists
     * RankMath and CoSchedule use. Presence of one (or a number) earns the
     * "title sentiment" point.
     */
    private const POWER_WORDS = [
        'best', 'guide', 'how', 'why', 'what', 'proven', 'simple', 'ultimate',
        'essential', 'complete', 'free', 'fast', 'stop', 'avoid', 'mistake',
        'mistakes', 'secret', 'secrets', 'now', 'new', 'results', 'boost',
        'grow', 'increase', 'wrong', 'truth', 'never', 'always', 'lying',
    ];

    /**
     * @param array<string,mixed> $data
     *   Expects: focus_keyword, title, meta_title, excerpt, meta_description,
     *   slug, content (HTML). Missing keys are treated as empty.
     *
     * @return array{score:int, rating:string, keyword:string, checks:array<int,array{id:string,label:string,status:string,message:string}>}
     */
    public static function analyze(array $data): array
    {
        $keyword = self::normalise((string) ($data['focus_keyword'] ?? ''));

        if ($keyword === '') {
            return [
                'score'   => 0,
                'rating'  => 'none',
                'keyword' => '',
                'checks'  => [[
                    'id'      => 'keyword',
                    'label'   => 'Focus keyword',
                    'status'  => 'bad',
                    'message' => 'Set a focus keyword to see the SEO analysis.',
                ]],
            ];
        }

        $title       = trim((string) ($data['meta_title'] ?? '')) ?: trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['meta_description'] ?? '')) ?: trim((string) ($data['excerpt'] ?? ''));
        $slug        = self::normalise((string) ($data['slug'] ?? ''));
        $html        = (string) ($data['content'] ?? '');

        $titleLc = self::normalise($title);
        $descLc  = self::normalise($description);

        $parsed    = self::parseContent($html);
        $plain     = $parsed['text'];
        $plainLc   = self::normalise($plain);
        $wordCount = self::countWords($plain);

        $occurrences = self::countOccurrences($plainLc, $keyword);
        $keywordWords = max(1, self::countWords($keyword));
        $density = $wordCount > 0 ? ($occurrences * $keywordWords / $wordCount) * 100 : 0.0;

        // First 10% of the content (min 1 sentence), for the "keyword in intro" test.
        $introLength = max(120, (int) floor(mb_strlen($plainLc) * 0.1));
        $intro = mb_substr($plainLc, 0, $introLength);

        $checks = [];

        // Each check earns up to `max` points. Points sum to 100.

        // 1. Keyword in the SEO title — 10
        $checks[] = self::check(
            'title_keyword', 'Focus keyword in the SEO title', 10,
            self::contains($titleLc, $keyword)
                ? (self::startsWith($titleLc, $keyword) ? 10 : 8)
                : 0,
            self::contains($titleLc, $keyword)
                ? (self::startsWith($titleLc, $keyword)
                    ? 'The title opens with the focus keyword.'
                    : 'The keyword is in the title. Moving it nearer the start helps.')
                : 'Add the focus keyword to the SEO title.'
        );

        // 2. Keyword in the meta description — 8
        $checks[] = self::check(
            'meta_keyword', 'Focus keyword in the meta description', 8,
            $description !== '' && self::contains($descLc, $keyword) ? 8 : 0,
            $description === ''
                ? 'Write a meta description that includes the focus keyword.'
                : (self::contains($descLc, $keyword)
                    ? 'The meta description uses the focus keyword.'
                    : 'Add the focus keyword to the meta description.')
        );

        // 3. Keyword in the URL slug — 7
        $checks[] = self::check(
            'slug_keyword', 'Focus keyword in the URL', 7,
            $slug !== '' && self::slugContains($slug, $keyword) ? 7 : 0,
            $slug !== '' && self::slugContains($slug, $keyword)
                ? 'The URL contains the focus keyword.'
                : 'Include the focus keyword in the URL slug.'
        );

        // 4. Keyword near the start of the content — 8
        $checks[] = self::check(
            'intro_keyword', 'Focus keyword early in the content', 8,
            self::contains($intro, $keyword) ? 8 : 0,
            self::contains($intro, $keyword)
                ? 'The keyword appears in the opening of the content.'
                : 'Use the focus keyword within the first paragraph.'
        );

        // 5. Keyword in a subheading — 7
        $inHeading = false;
        foreach ($parsed['headings'] as $heading) {
            if (self::contains(self::normalise($heading), $keyword)) {
                $inHeading = true;
                break;
            }
        }
        $checks[] = self::check(
            'heading_keyword', 'Focus keyword in a subheading', 7,
            $inHeading ? 7 : 0,
            $parsed['headings'] === []
                ? 'Break the content up with H2/H3 subheadings, and use the keyword in one.'
                : ($inHeading ? 'A subheading includes the focus keyword.' : 'Use the focus keyword in at least one subheading.')
        );

        // 6. Keyword in image alt text — 6
        $imagesWithAlt = array_filter($parsed['imageAlts'], static fn (string $alt): bool => trim($alt) !== '');
        $altHasKeyword = false;
        foreach ($imagesWithAlt as $alt) {
            if (self::contains(self::normalise($alt), $keyword)) {
                $altHasKeyword = true;
                break;
            }
        }
        $checks[] = self::check(
            'alt_keyword', 'Focus keyword in image alt text', 6,
            $altHasKeyword ? 6 : 0,
            $parsed['imageCount'] === 0
                ? 'Add an image, and describe it in alt text using the focus keyword.'
                : ($altHasKeyword ? 'An image alt text uses the focus keyword.' : 'Add the focus keyword to an image\'s alt text.')
        );

        // 7. Keyword density in the ideal band — 10
        $densityGood = $density >= self::DENSITY_MIN && $density <= self::DENSITY_MAX;
        $densityClose = !$densityGood && $density > 0 && $density < self::DENSITY_MIN * 2;
        $checks[] = self::check(
            'density', 'Keyword density', 10,
            $densityGood ? 10 : ($densityClose ? 5 : 0),
            sprintf(
                'Density is %.1f%%. %s',
                $density,
                $densityGood
                    ? 'That is in the ideal 0.5–2.5% range.'
                    : ($density > self::DENSITY_MAX
                        ? 'That is high — ease off the keyword to avoid stuffing.'
                        : 'Aim for 0.5–2.5% by using the keyword a little more.')
            )
        );

        // 8. Content length — 12
        $lengthEarned = $wordCount >= self::CONTENT_GOOD ? 12 : ($wordCount >= self::CONTENT_OK ? 6 : 0);
        $checks[] = self::check(
            'length', 'Content length', 12, $lengthEarned,
            sprintf(
                '%d words. %s',
                $wordCount,
                $wordCount >= self::CONTENT_GOOD
                    ? 'Comfortably long enough to rank.'
                    : ($wordCount >= self::CONTENT_OK
                        ? 'Reasonable — 600+ words tends to perform better.'
                        : 'Thin. Aim for at least 600 words.')
            )
        );

        // 9. SEO title length — 7
        $titleLen = mb_strlen($title);
        $checks[] = self::check(
            'title_length', 'SEO title length', 7,
            $titleLen > 0 && $titleLen <= self::TITLE_MAX ? 7 : ($titleLen > 0 && $titleLen <= 70 ? 4 : 0),
            $titleLen === 0
                ? 'Set an SEO title.'
                : sprintf('%d characters. %s', $titleLen, $titleLen <= self::TITLE_MAX
                    ? 'Fits without being cut off in results.'
                    : 'Over 60 characters may be truncated in search results.')
        );

        // 10. Meta description length — 7
        $descLen = mb_strlen($description);
        $checks[] = self::check(
            'meta_length', 'Meta description length', 7,
            $descLen >= self::META_MIN && $descLen <= self::META_MAX ? 7 : ($descLen > 0 ? 3 : 0),
            $descLen === 0
                ? 'Write a meta description of 120–160 characters.'
                : sprintf('%d characters. %s', $descLen, ($descLen >= self::META_MIN && $descLen <= self::META_MAX)
                    ? 'A good length for search snippets.'
                    : ($descLen < self::META_MIN ? 'A little short — 120–160 reads best.' : 'A little long — it may be truncated.'))
        );

        // 11. Title has a number or a power word — 6
        $hasNumber = preg_match('/\d/', $title) === 1;
        $hasPower  = self::hasPowerWord($titleLc);
        $checks[] = self::check(
            'title_sentiment', 'Title has a number or power word', 6,
            ($hasNumber || $hasPower) ? 6 : 0,
            ($hasNumber || $hasPower)
                ? 'The title has a number or a compelling word.'
                : 'Titles with a number or a power word (how, why, best, proven…) get more clicks.'
        );

        // 12. Content contains at least one image — 6
        $checks[] = self::check(
            'has_image', 'Content contains an image', 6,
            $parsed['imageCount'] > 0 ? 6 : 0,
            $parsed['imageCount'] > 0
                ? 'The content includes at least one image.'
                : 'Add an image or two — content with media holds attention.'
        );

        // 13. Content contains a link — 6
        $checks[] = self::check(
            'has_link', 'Content contains a link', 6,
            $parsed['linkCount'] > 0 ? 6 : 0,
            $parsed['linkCount'] > 0
                ? 'The content links out or across the site.'
                : 'Add a relevant internal or external link.'
        );

        $score = 0;
        foreach ($checks as $check) {
            $score += $check['earned'];
        }
        $score = (int) min(100, $score);

        // Strip the internal `earned`/`max` keys from the public payload.
        $public = array_map(static fn (array $c): array => [
            'id'      => $c['id'],
            'label'   => $c['label'],
            'status'  => $c['status'],
            'message' => $c['message'],
        ], $checks);

        return [
            'score'   => $score,
            'rating'  => self::rating($score),
            'keyword' => $keyword,
            'checks'  => $public,
        ];
    }

    /**
     * Convenience for the controller: just the number to cache.
     *
     * @param array<string,mixed> $data
     */
    public static function score(array $data): int
    {
        return self::analyze($data)['score'];
    }

    /**
     * 0–50 poor, 51–80 ok, 81–100 great — the RankMath traffic-light bands.
     */
    public static function rating(int $score): string
    {
        if ($score >= 81) {
            return 'great';
        }

        return $score >= 51 ? 'ok' : 'poor';
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{max:int,earned:int,id:string,label:string,status:string,message:string}
     */
    private static function check(string $id, string $label, int $max, int $earned, string $message): array
    {
        $earned = max(0, min($max, $earned));

        $status = $earned >= $max ? 'good' : ($earned > 0 ? 'ok' : 'bad');

        return compact('id', 'label', 'max', 'earned', 'status', 'message');
    }

    private static function normalise(string $value): string
    {
        // Lowercase and collapse whitespace so comparisons are case- and
        // spacing-insensitive, which is what an author intuitively expects.
        $value = mb_strtolower(trim($value));

        return (string) preg_replace('/\s+/u', ' ', $value);
    }

    private static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }

    private static function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) === 0;
    }

    /**
     * Slug comparison: the keyword's spaces become hyphens, matching how slugs
     * are generated, so "paid media" matches "…-paid-media-…".
     */
    private static function slugContains(string $slug, string $keyword): bool
    {
        $needle = str_replace(' ', '-', $keyword);

        return $needle !== '' && str_contains($slug, $needle);
    }

    private static function countWords(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        // Unicode-aware word count; str_word_count misses accented and non-Latin text.
        return count(preg_split('/\s+/u', $text) ?: []);
    }

    private static function countOccurrences(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }

        return mb_substr_count($haystack, $needle);
    }

    private static function hasPowerWord(string $titleLc): bool
    {
        foreach (self::POWER_WORDS as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $titleLc) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pulls plain text, subheadings, image alts and link count out of the content
     * HTML in one parse.
     *
     * @return array{text:string,headings:array<int,string>,imageAlts:array<int,string>,imageCount:int,linkCount:int}
     */
    private static function parseContent(string $html): array
    {
        $empty = ['text' => '', 'headings' => [], 'imageAlts' => [], 'imageCount' => 0, 'linkCount' => 0];

        if (trim($html) === '') {
            return $empty;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();

        // Wrap so a fragment parses, and force UTF-8 so multibyte text survives.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // Fall back to a tag strip so a malformed fragment still yields text.
            return array_merge($empty, ['text' => trim(strip_tags($html))]);
        }

        $xpath = new DOMXPath($document);

        $headings = [];
        foreach ($xpath->query('//h1|//h2|//h3|//h4') ?: [] as $node) {
            $headings[] = $node->textContent;
        }

        $imageAlts = [];
        $imageNodes = $xpath->query('//img') ?: [];
        foreach ($imageNodes as $node) {
            $imageAlts[] = $node->attributes?->getNamedItem('alt')?->nodeValue ?? '';
        }

        $linkNodes = $xpath->query('//a[@href]') ?: [];

        return [
            'text'       => trim((string) $document->textContent),
            'headings'   => $headings,
            'imageAlts'  => $imageAlts,
            'imageCount' => $imageNodes->length,
            'linkCount'  => $linkNodes->length,
        ];
    }
}
