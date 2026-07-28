<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Full-page cache for anonymous visitors, with LiteSpeed integration.
 *
 * Two mechanisms, both driven by the same toggle:
 *
 *  1. LiteSpeed headers. Hostinger runs LiteSpeed, which reads X-LiteSpeed-Cache-
 *     Control and serves subsequent hits from its own cache WITHOUT starting PHP —
 *     the fastest possible path. Purge is a tagged header the app emits on publish.
 *
 *  2. A built-in file cache as a fallback for any host that is not LiteSpeed (and
 *     for local dev). The middleware serves a stored copy when present, so even
 *     without LiteSpeed the second visitor to a page skips rendering.
 *
 * Only cacheable: GET, HTTP 200, HTML, a guest (no session), no query string.
 * Logged-in staff, POSTs, the admin, the API and anything dynamic are never cached.
 */
final class PageCache
{
    public static function enabled(): bool
    {
        try {
            return Setting::bool('plugin_cache_enabled', false);
        } catch (Throwable) {
            return false;
        }
    }

    public static function ttl(): int
    {
        try {
            return max(60, (int) Setting::get('cache_ttl', 3600));
        } catch (Throwable) {
            return 3600;
        }
    }

    /**
     * Whether this request is eligible to be served from / stored in the cache.
     */
    public static function isCacheable(Request $request): bool
    {
        if (!self::enabled()) {
            return false;
        }

        if ($request->method() !== 'GET') {
            return false;
        }

        $path = $request->path();

        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api/')) {
            return false;
        }

        // A query string usually means search or filtering — page-specific, skip it.
        if ($request->rawQueryString() !== '') {
            return false;
        }

        // Never serve a cached page to a signed-in user (they may see edit affordances).
        if (Auth::check()) {
            return false;
        }

        return true;
    }

    /**
     * Returns a fresh cached body for this request, or null.
     */
    public static function get(Request $request): ?string
    {
        if (!self::isCacheable($request)) {
            return null;
        }

        $file = self::pathFor($request);

        if (!is_file($file)) {
            return null;
        }

        if (filemtime($file) + self::ttl() < time()) {
            @unlink($file);

            return null;
        }

        $body = @file_get_contents($file);

        return $body === false ? null : $body;
    }

    /**
     * Stores a rendered HTML body for this request.
     */
    public static function put(Request $request, string $body): void
    {
        if (!self::isCacheable($request) || trim($body) === '') {
            return;
        }

        try {
            $dir = self::dir();

            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                return;
            }

            file_put_contents(self::pathFor($request), $body, LOCK_EX);
        } catch (Throwable $e) {
            error_log('PageCache put failed: ' . $e->getMessage());
        }
    }

    /**
     * LiteSpeed cache-control header value for a cacheable response, or null.
     */
    public static function liteSpeedHeader(Request $request): ?string
    {
        if (!self::isCacheable($request)) {
            return null;
        }

        return 'public,max-age=' . self::ttl();
    }

    /**
     * Empties the whole cache. Called on any publish/content change, and from the
     * plugin screen's "Purge now" button.
     *
     * @return int files removed
     */
    public static function purge(): int
    {
        $removed = 0;

        try {
            foreach (glob(self::dir() . '/*.html') ?: [] as $file) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        } catch (Throwable) {
        }

        return $removed;
    }

    public static function size(): int
    {
        return count(glob(self::dir() . '/*.html') ?: []);
    }

    /**
     * The LiteSpeed purge-all header, emitted alongside a normal response after a
     * publish so LiteSpeed drops its copies too.
     */
    public static function liteSpeedPurgeHeader(): string
    {
        return '*';
    }

    private static function dir(): string
    {
        return STORAGE_PATH . '/cache/pages';
    }

    private static function pathFor(Request $request): string
    {
        // Just a filename-safe key for the path; sha256 keeps the security audit
        // happy (no sha1/md5 anywhere) even though this is not security-bearing.
        return self::dir() . '/' . hash('sha256', $request->path()) . '.html';
    }
}
