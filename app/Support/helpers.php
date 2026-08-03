<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;

/**
 * Global helpers. Loaded by Composer's `files` autoloader on every request.
 * Kept deliberately small — anything with real logic belongs in a Core class.
 */

if (!function_exists('e')) {
    /**
     * Escape for HTML context. Every dynamic value printed in a template goes
     * through this; there are no exceptions outside of already-sanitized rich text.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('url')) {
    /**
     * Absolute URL. Use for canonicals, OG tags, sitemap entries and email links —
     * anywhere the value leaves the page. In-page hrefs should stay root-relative.
     */
    function url(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Root-relative asset path with a cache-busting fingerprint taken from mtime,
     * so a redeploy invalidates the browser cache without renaming files.
     */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = PUBLIC_PATH . $path;

        if (is_file($file)) {
            return $path . '?v=' . filemtime($file);
        }

        return $path;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Browsers only send GET and POST. PATCH/DELETE routes are reached by POSTing
     * this hidden field, which Request translates back into the real verb.
     */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return App\Core\Nonce::value();
    }
}

if (!function_exists('old')) {
    /**
     * Re-populates a form field after a failed validation round-trip.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::oldInput($key, $default);
    }
}

if (!function_exists('errors')) {
    /**
     * @return array<string,string>
     */
    function errors(): array
    {
        return Session::errors();
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): ?string
    {
        return errors()[$field] ?? null;
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $value, int $limit = 160, string $end = '…'): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $value, string $format = 'j M Y'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return '';
        }

        return date($format, strtotime($value));
    }
}

if (!function_exists('array_get')) {
    function array_get(?array $array, string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }
}

if (!function_exists('json_column')) {
    /**
     * Decodes a JSON column into an array, tolerating NULL and malformed values
     * rather than fataling on a public page.
     *
     * @return array<mixed>
     */
    function json_column(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('editable')) {
    /**
     * Marks an element as inline-editable for signed-in staff.
     *
     * Prints the data attributes inline-edit.js looks for, and nothing at all for
     * a guest — so the public HTML is byte-identical to what it was before, and
     * there is no hint that an editing surface exists.
     *
     * The value still has to survive the same sanitiser as the CMS form: the save
     * endpoint reads the block's declared type from the database rather than
     * trusting anything sent from the page.
     */
    function editable(string $page, string $block, string $type = 'text'): string
    {
        if (!App\Core\Auth::check()) {
            return '';
        }

        return sprintf(
            ' data-edit="%s" data-edit-block="%s" data-edit-type="%s"',
            e($page),
            e($block),
            $type === 'html' ? 'html' : 'text'
        );
    }
}
