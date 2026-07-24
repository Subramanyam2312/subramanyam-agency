<?php

declare(strict_types=1);

namespace App\Core;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Rich-text sanitising, applied on SAVE rather than on render.
 *
 * Sanitising at render time means the database holds hostile markup and every
 * future consumer — the REST API, an RSS feed, a CSV export, a migration script —
 * has to remember to clean it. Cleaning once on the way in means what is stored is
 * already safe for every consumer.
 *
 * HTMLPurifier works from a whitelist: anything not explicitly permitted is dropped,
 * so <script>, on* handlers and javascript: URLs are impossible by construction
 * rather than by pattern-matching against a blacklist that always has gaps.
 */
final class Sanitizer
{
    private static ?HTMLPurifier $rich = null;

    /**
     * Full rich text: the blog editor and long-form CMS fields.
     */
    public static function rich(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        self::$rich ??= new HTMLPurifier(self::config());

        return self::$rich->purify($html);
    }

    /**
     * Strips markup entirely. Used for excerpts, meta descriptions and the
     * content_text mirror column that backs FULLTEXT search.
     */
    public static function plain(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', ' ', $html) ?? $html;
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Reading time from the plain-text mirror. 200 wpm is the usual reading rate
     * for this kind of prose; always at least one minute.
     */
    public static function readingTime(string $plainText): int
    {
        $words = str_word_count($plainText);

        return max(1, (int) ceil($words / 200));
    }

    private static function config(): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();

        $cache = STORAGE_PATH . '/purifier';

        if (!is_dir($cache)) {
            mkdir($cache, 0770, true);
        }

        $config->set('Cache.SerializerPath', $cache);
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'span',
            'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
            'h2', 'h3', 'h4',
            'ul', 'ol', 'li',
            'blockquote', 'pre', 'code',
            'hr',
            'a[href|title|target|rel]',
            'img[src|alt|title|width|height|loading]',
            'figure', 'figcaption',
            'table', 'thead', 'tbody', 'tr', 'th[scope|colspan|rowspan]', 'td[colspan|rowspan]',
            'iframe[src|width|height|title|allow|allowfullscreen|frameborder]',
        ]));

        // Quill writes alignment and indentation as classes rather than inline styles.
        $config->set('Attr.AllowedClasses', [
            'ql-align-center', 'ql-align-right', 'ql-align-justify',
            'ql-indent-1', 'ql-indent-2', 'ql-indent-3',
            'ql-syntax',
        ]);

        // Any link opening a new tab gets rel="noopener noreferrer" added, so the
        // destination cannot reach back through window.opener.
        $config->set('HTML.TargetBlank', false);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);

        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.AutoParagraph', false);

        // Only http/https/mailto/tel survive. This is what kills javascript: and data: URLs.
        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
            'tel'    => true,
        ]);

        /*
         * Embeds are limited to a host whitelist. Allowing arbitrary iframes would
         * let anyone with editor access frame a phishing page onto the site's own
         * domain, which is worth more to an attacker than stored XSS.
         */
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed)%');

        return $config;
    }
}
