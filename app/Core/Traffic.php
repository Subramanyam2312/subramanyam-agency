<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Server-side traffic counter.
 *
 * A privacy-first alternative (or complement) to Google Analytics: it counts page
 * views and unique visitors in the site's own database, stores no raw IP address,
 * sets no cookie, and sends nothing to a third party. Uniques are counted from a
 * daily-salted HMAC of the IP, so the same visitor is one hash today and a
 * different hash tomorrow — countable, never trackable.
 *
 * Every method fails silently: analytics must never break a page render.
 *
 * Note the interaction with the page cache — when LiteSpeed or the built-in cache
 * serves a page, PHP does not run, so that hit is not counted here. Client-side
 * GA4 remains the source of truth for total traffic; this is the honest, cookieless
 * in-house view.
 */
final class Traffic
{
    /**
     * Clients whose request is not a person reading the page.
     *
     * Everything here is matched as a lowercase substring of the user agent.
     * SpamGuard keeps a similar list for the contact form, but a narrower one:
     * it only cares about automation pretending to be a person filling in a
     * form, and has no reason to exclude Googlebot. This list has to exclude
     * both, so the two are kept separate rather than shared.
     *
     * Self-declaring crawlers whose name simply ends in "bot" are caught by
     * BOT_PATTERN instead, so they are not repeated here.
     */
    private const NON_HUMAN_AGENTS = [
        // Crawlers and readers that do not end in "bot"
        'crawler', 'crawling', 'spider', 'slurp', 'yandex', 'baidu',
        'duckduckgo', 'ia_archiver', 'mediapartners-google',
        'google-inspectiontool', 'facebookexternalhit', 'embedly',
        'quora link preview', 'skypeuripreview', 'whatsapp', 'feedfetcher',
        // Auditing and monitoring
        'lighthouse', 'pagespeed', 'pingdom', 'statuscake', 'site24x7',
        // Scripted HTTP clients
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'python-requests', 'python-urllib', 'curl/', 'wget/', 'go-http-client',
        'okhttp', 'scrapy', 'axios/', 'node-fetch', 'libwww-perl', 'java/',
        'apache-httpclient', 'postmanruntime', 'insomnia/',
    ];

    /**
     * Device names containing "bot" that belong to real visitors. Checked
     * before BOT_PATTERN, which would otherwise drop them: CUBOT is an Android
     * phone brand sold in India, this site's main market.
     */
    private const AGENT_EXCEPTIONS = ['cubot'];

    /**
     * A self-declared crawler: "Googlebot/2.1", "AhrefsBot;", "bingbot)".
     *
     * "bot" must not be followed by another letter, so "Googlebot/2.1" matches
     * while a word that merely contains the letters does not.
     */
    private const BOT_PATTERN = '~bot(?![a-z])~';

    public static function enabled(): bool
    {
        try {
            return Setting::bool('plugin_traffic_enabled', true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Records one page view. Called from the traffic middleware for public,
     * non-asset HTML responses only.
     */
    public static function record(Request $request): void
    {
        if (!self::enabled() || !self::countable($request)) {
            return;
        }

        try {
            $day  = date('Y-m-d');
            $path = mb_substr($request->path(), 0, 191);

            // Daily-salted visitor hash — unique-countable, not trackable.
            $visitor = hash_hmac('sha256', $request->ip() . '|' . $day, (string) config('app.key'));

            self::upsert(
                'INSERT INTO `traffic_daily` (`day`, `views`, `visitors`) VALUES (:d, 1, 0)
                 ON DUPLICATE KEY UPDATE `views` = `views` + 1',
                [':d' => $day]
            );

            // First view from this visitor today bumps the unique count.
            $isNew = self::insertIgnore(
                'INSERT INTO `traffic_visitors` (`day`, `visitor`) VALUES (:d, :v)',
                [':d' => $day, ':v' => $visitor]
            );

            if ($isNew) {
                Database::query(
                    'UPDATE `traffic_daily` SET `visitors` = `visitors` + 1 WHERE `day` = :d',
                    [':d' => $day]
                );
            }

            self::upsert(
                'INSERT INTO `traffic_paths` (`day`, `path`, `views`) VALUES (:d, :p, 1)
                 ON DUPLICATE KEY UPDATE `views` = `views` + 1',
                [':d' => $day, ':p' => $path]
            );

            $host = self::refererHost($request->referer());

            if ($host !== null) {
                self::upsert(
                    'INSERT INTO `traffic_referrers` (`day`, `host`, `views`) VALUES (:d, :h, 1)
                     ON DUPLICATE KEY UPDATE `views` = `views` + 1',
                    [':d' => $day, ':h' => $host]
                );
            }
        } catch (Throwable $e) {
            error_log('Traffic record failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{today_views:int, today_visitors:int, total_views:int, series:array<int,array{day:string,views:int,visitors:int}>, top_paths:array<int,array<string,mixed>>, referrers:array<int,array<string,mixed>>}
     */
    public static function summary(int $days = 30): array
    {
        $blank = [
            'today_views' => 0, 'today_visitors' => 0, 'total_views' => 0,
            'series' => [], 'top_paths' => [], 'referrers' => [],
        ];

        try {
            $since = date('Y-m-d', time() - ($days - 1) * 86400);

            $rows = Database::select(
                'SELECT `day`, `views`, `visitors` FROM `traffic_daily`
                 WHERE `day` >= :since ORDER BY `day`',
                [':since' => $since]
            );

            // Fill missing days with zeros so the chart has an unbroken axis.
            $byDay = [];
            foreach ($rows as $r) {
                $byDay[$r['day']] = ['views' => (int) $r['views'], 'visitors' => (int) $r['visitors']];
            }

            $series = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('Y-m-d', time() - $i * 86400);
                $series[] = [
                    'day'      => $d,
                    'views'    => $byDay[$d]['views'] ?? 0,
                    'visitors' => $byDay[$d]['visitors'] ?? 0,
                ];
            }

            $today = $byDay[date('Y-m-d')] ?? ['views' => 0, 'visitors' => 0];

            return [
                'today_views'    => $today['views'],
                'today_visitors' => $today['visitors'],
                'total_views'    => (int) Database::scalar('SELECT COALESCE(SUM(`views`),0) FROM `traffic_daily`'),
                'series'         => $series,
                'top_paths'      => Database::select(
                    'SELECT `path`, SUM(`views`) AS views FROM `traffic_paths`
                     WHERE `day` >= :since GROUP BY `path` ORDER BY views DESC LIMIT 8',
                    [':since' => $since]
                ),
                'referrers'      => Database::select(
                    'SELECT `host`, SUM(`views`) AS views FROM `traffic_referrers`
                     WHERE `day` >= :since GROUP BY `host` ORDER BY views DESC LIMIT 8',
                    [':since' => $since]
                ),
            ];
        } catch (Throwable) {
            return $blank;
        }
    }

    public static function totalViews(): int
    {
        try {
            return (int) Database::scalar('SELECT COALESCE(SUM(`views`),0) FROM `traffic_daily`');
        } catch (Throwable) {
            return 0;
        }
    }

    /** Cron housekeeping: drop visitor hashes older than 90 days. */
    public static function sweep(): void
    {
        try {
            Database::query('DELETE FROM `traffic_visitors` WHERE `day` < DATE_SUB(CURDATE(), INTERVAL 90 DAY)');
        } catch (Throwable) {
        }
    }

    /**
     * Is this request worth counting as a human page view?
     *
     * Before this existed the counter recorded every request that reached PHP,
     * so crawlers, uptime pingers and scripted checks all showed up as visitors.
     * On 10 Sep 2026 the counter reported 44 views from 8 visitors in a day that
     * was almost entirely one automated sweep of the sitemap.
     *
     * Substring matching is crude and will never be complete — a crawler that
     * declares itself as an ordinary browser is still counted, and nothing here
     * can catch that. It removes the non-human traffic that identifies itself
     * honestly, which on this site is the bulk of it.
     */
    private static function countable(Request $request): bool
    {
        // A HEAD request never rendered a page for anybody.
        if ($request->method() === 'HEAD') {
            return false;
        }

        $agent = mb_strtolower(trim($request->userAgent()));

        // Every real browser sends one; an empty agent is a script.
        if ($agent === '') {
            return false;
        }

        foreach (self::NON_HUMAN_AGENTS as $marker) {
            if (str_contains($agent, $marker)) {
                return false;
            }
        }

        foreach (self::AGENT_EXCEPTIONS as $exception) {
            if (str_contains($agent, $exception)) {
                return true;
            }
        }

        return preg_match(self::BOT_PATTERN, $agent) !== 1;
    }

    private static function refererHost(string $referer): ?string
    {
        if ($referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        // Our own domain is not a referrer worth listing.
        $self = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($host === $self) {
            return null;
        }

        return mb_substr(preg_replace('/^www\./', '', $host), 0, 191);
    }

    /**
     * @param array<string,mixed> $params
     */
    private static function upsert(string $sql, array $params): void
    {
        Database::query($sql, $params);
    }

    /**
     * @param array<string,mixed> $params
     * @return bool true if a new row was inserted
     */
    private static function insertIgnore(string $sql, array $params): bool
    {
        $sql = preg_replace('/^INSERT INTO/', 'INSERT IGNORE INTO', $sql);

        return Database::query($sql, $params)->rowCount() > 0;
    }
}
