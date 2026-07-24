<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Per-request CSP nonce.
 *
 * Generated once at boot and reused by both the Content-Security-Policy header and
 * every inline <style>/<script> block in the templates. This is what lets the policy
 * stay free of 'unsafe-inline' while still inlining critical CSS and JSON-LD.
 */
final class Nonce
{
    private static ?string $value = null;

    public static function value(): string
    {
        if (self::$value === null) {
            self::$value = base64_encode(random_bytes(16));
        }

        return self::$value;
    }
}
