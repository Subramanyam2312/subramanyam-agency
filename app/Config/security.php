<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'login' => [
        // Per IP+email, enforced by RateLimiter.
        'max_attempts'    => 5,
        'decay_seconds'   => 900,

        // Per account, enforced on the user row so rotating IPs does not help.
        'lockout_after'   => 8,
        'lockout_minutes' => 15,
    ],

    'remember' => [
        'cookie' => 'agency_remember',
        'days'   => 30,
    ],

    'reset' => [
        'expires_minutes' => 60,

        // Per IP, so the reset form cannot be used to mail-bomb an address.
        'max_requests'    => 5,
        'decay_seconds'   => 3600,
    ],

    'contact' => [
        'max_submissions' => 5,
        'decay_seconds'   => 3600,
    ],

    'api' => [
        'max_requests'  => 120,
        'decay_seconds' => 60,
    ],

    'uploads' => [
        'max_bytes'     => 8 * 1024 * 1024,
        // Validated by finfo MIME sniffing, never by the filename extension.
        'allowed_mimes' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/svg+xml' => 'svg',
        ],
        // Widths generated as WebP for every raster upload.
        'widths'        => [320, 640, 1024, 1600],
        'webp_quality'  => 82,
    ],

    'headers' => [
        /*
         * HSTS stays off until SSL is confirmed working on the live domain, then goes
         * on at a low max-age first. Enabling it prematurely makes the domain
         * unreachable over HTTP for the full max-age with no way to take it back.
         */
        'hsts'            => (bool) Env::get('SECURITY_HSTS', false),
        'hsts_max_age'    => (int) Env::get('SECURITY_HSTS_MAX_AGE', 300),

        'frame_options'   => 'DENY',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=(), interest-cohort=()',
    ],

    /*
     * Nonce-based CSP. No 'unsafe-inline' anywhere, which is the whole reason the
     * project self-hosts every script and stylesheet instead of using a CDN.
     */
    'csp' => [
        'default-src' => "'self'",
        'base-uri'    => "'self'",
        'object-src'  => "'none'",
        'frame-ancestors' => "'none'",
        'form-action' => "'self'",
        'img-src'     => "'self' data:",
        'font-src'    => "'self'",
        'media-src'   => "'self'",
        'connect-src' => "'self'",
    ],
];
