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
        if (!self::enabled()) {
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
