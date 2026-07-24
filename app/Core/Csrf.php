<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection.
 *
 * One token per session rather than one per form: per-form tokens break the back
 * button and multi-tab editing, which matters in a CMS where an editor routinely
 * has three tabs open. The token is rotated on login and logout.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (!Session::has(self::KEY)) {
            self::rotate();
        }

        return (string) Session::get(self::KEY);
    }

    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::KEY, $token);

        return $token;
    }

    /**
     * Accepts the token from either the form field or the X-CSRF-Token header,
     * the latter for fetch() calls from the admin UI.
     */
    public static function verify(Request $request): bool
    {
        $expected = Session::get(self::KEY);

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $provided = $request->post('_token')
            ?? $request->header('X-CSRF-Token')
            ?? '';

        if (!is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
