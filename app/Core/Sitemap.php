<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * sitemap.xml generation.
 *
 * Written to disk rather than served from a route so the web server can hand it
 * over without touching PHP, and so a crawler hitting it never contends with a
 * visitor for a database connection. A route serves it as a fallback when the
 * file has not been generated yet — a missing sitemap should be a slow sitemap,
 * not a 404.
 *
 * Regenerated whenever content that appears in it is published or removed, and
 * again nightly by cron in case a write ever failed silently.
 */
final class Sitemap
{
    /**
     * @return array{ok:bool, count:int, message?:string}
     */
    public static function generate(): array
    {
        try {
            $xml   = self::build();
            $path  = PUBLIC_PATH . '/sitemap.xml';
            $bytes = file_put_contents($path, $xml, LOCK_EX);

            if ($bytes === false) {
                return ['ok' => false, 'count' => 0, 'message' => 'Could not write sitemap.xml.'];
            }

            @chmod($path, 0644);

            return ['ok' => true, 'count' => substr_count($xml, '<url>')];
        } catch (Throwable $e) {
            // A sitemap failure must never take down the save that triggered it.
            error_log('Sitemap generation failed: ' . $e->getMessage());

            return ['ok' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
    }

    public static function build(): string
    {
        $urls = [];

        // Static pages. Priorities are relative hints only — search engines have
        // largely stopped using them, but they cost nothing and remain valid.
        $urls[] = self::url('/', '1.0', 'weekly');
        $urls[] = self::url('/services', '0.9', 'monthly');
        $urls[] = self::url('/work', '0.9', 'monthly');
        $urls[] = self::url('/about', '0.7', 'monthly');
        $urls[] = self::url('/faq', '0.6', 'monthly');
        $urls[] = self::url('/blog', '0.8', 'daily');
        $urls[] = self::url('/contact', '0.7', 'yearly');
        $urls[] = self::url('/privacy', '0.2', 'yearly');
        $urls[] = self::url('/terms', '0.2', 'yearly');

        // Services.
        foreach (Database::select(
            'SELECT `slug`, `updated_at` FROM `services`
             WHERE `is_active` = 1 AND `noindex` = 0 AND `deleted_at` IS NULL
             ORDER BY `sort_order`'
        ) as $row) {
            $urls[] = self::url('/services/' . $row['slug'], '0.8', 'monthly', $row['updated_at']);
        }

        // Case studies.
        foreach (Database::select(
            "SELECT `slug`, `updated_at` FROM `case_studies`
             WHERE `status` = 'published' AND `noindex` = 0 AND `deleted_at` IS NULL
             ORDER BY `sort_order`"
        ) as $row) {
            $urls[] = self::url('/work/' . $row['slug'], '0.7', 'monthly', $row['updated_at']);
        }

        // Published posts. Scheduled ones are deliberately excluded — listing a URL
        // that 404s until Tuesday is how a site loses crawl trust.
        foreach (Database::select(
            "SELECT `slug`, `updated_at` FROM `posts`
             WHERE `status` = 'published' AND `published_at` <= NOW()
               AND `noindex` = 0 AND `deleted_at` IS NULL
             ORDER BY `published_at` DESC"
        ) as $row) {
            $urls[] = self::url('/blog/' . $row['slug'], '0.6', 'monthly', $row['updated_at']);
        }

        // Category archives that actually have something in them.
        foreach (Database::select(
            "SELECT c.slug, MAX(p.updated_at) AS updated_at
             FROM `categories` c
             INNER JOIN `posts` p
                     ON p.category_id = c.id
                    AND p.status = 'published'
                    AND p.published_at <= NOW()
                    AND p.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
             GROUP BY c.id, c.slug"
        ) as $row) {
            $urls[] = self::url('/blog/category/' . $row['slug'], '0.5', 'weekly', $row['updated_at']);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode("\n", $urls) . "\n"
            . '</urlset>' . "\n";
    }

    /**
     * robots.txt, assembled from settings so the owner can add rules without a deploy.
     */
    public static function robots(): string
    {
        $lines = ['User-agent: *'];

        // Maintenance mode must not invite crawlers in to index a holding page.
        if (Setting::bool('maintenance_mode')) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Disallow: /admin';
            $lines[] = 'Disallow: /api/';
            // Query-string variants of the blog are duplicates of the canonical page.
            $lines[] = 'Disallow: /*?q=';
            $lines[] = 'Allow: /';
        }

        $extra = trim((string) Setting::get('robots_extra', ''));

        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('/sitemap.xml');

        return implode("\n", $lines) . "\n";
    }

    private static function url(string $path, string $priority, string $frequency, ?string $lastModified = null): string
    {
        $entry = "    <url>\n"
            . '        <loc>' . htmlspecialchars(url($path), ENT_XML1, 'UTF-8') . "</loc>\n";

        if ($lastModified !== null && $lastModified !== '') {
            $entry .= '        <lastmod>' . date('Y-m-d', (int) strtotime($lastModified)) . "</lastmod>\n";
        }

        $entry .= "        <changefreq>{$frequency}</changefreq>\n"
            . "        <priority>{$priority}</priority>\n"
            . '    </url>';

        return $entry;
    }
}
