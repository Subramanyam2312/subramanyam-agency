<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Media;
use App\Models\Setting;

/**
 * The site's logo and browser icon.
 *
 * Both are stored as media library IDs and resolved here rather than in each view.
 * Three templates need them (public site, admin, sign-in), and every one of those
 * has to handle the same two awkward states: the setting is empty, or it points at
 * an image that has since been deleted. Resolving in one place means an unset or
 * missing logo degrades to the text wordmark everywhere instead of rendering a
 * broken image box in the two templates somebody forgot to guard.
 */
final class Branding
{
    /** Resolved rows, keyed by setting. Null is a real cached answer, not a miss. */
    private static array $cache = [];

    /**
     * @return array<string,mixed>|null
     */
    public static function logo(): ?array
    {
        return self::resolve('site_logo_media_id');
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function icon(): ?array
    {
        return self::resolve('site_icon_media_id');
    }

    /**
     * Public URL for a media row, cache-busted by the file's own mtime.
     *
     * Browsers cache favicons far more aggressively than ordinary images — often
     * past a hard refresh — so without the mtime a replaced icon can appear not to
     * have changed at all, which reads as the CMS being broken.
     *
     * @param array<string,mixed> $media
     */
    public static function url(array $media): string
    {
        $path = '/' . ltrim((string) $media['path'], '/');
        $file = PUBLIC_PATH . $path;

        return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
    }

    /**
     * The icon's MIME type, for the `type` attribute on the icon link.
     */
    public static function iconMime(): string
    {
        $icon = self::icon();

        return $icon === null ? '' : (string) $icon['mime'];
    }

    /**
     * Clears the resolved rows. Called after the settings are saved, because a
     * long-running request that both writes and reads would otherwise serve the
     * previous image.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function resolve(string $key): ?array
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $id = (int) Setting::get($key, 0);

        // Media::find already excludes soft-deleted rows.
        $media = $id > 0 ? Media::find($id) : null;

        /*
         * The row can outlive the file — a restored database against an uploads
         * directory that was not restored with it. Checking the file exists here
         * is what turns that into "no logo" rather than a broken image on every
         * page of the site.
         */
        if ($media !== null && !is_file(PUBLIC_PATH . '/' . ltrim((string) $media['path'], '/'))) {
            $media = null;
        }

        return self::$cache[$key] = $media;
    }
}
