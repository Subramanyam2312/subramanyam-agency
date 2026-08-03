<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Fonts;
use App\Core\Nonce;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use Throwable;

/**
 * Applies the security header set to every response.
 *
 * Runs as global middleware, and decorates the response on the way back out so the
 * headers are present on error pages too.
 */
final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $config = (array) config('security.headers');

        $response->header('Content-Security-Policy', $this->buildCsp($request));
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', (string) $config['frame_options']);
        $response->header('Referrer-Policy', (string) $config['referrer_policy']);
        $response->header('Permissions-Policy', (string) $config['permissions_policy']);
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');

        // Only ever sent over an already-secure connection; sending it over plain
        // HTTP is meaningless and sending it before SSL works is unrecoverable.
        if ($config['hsts'] && $request->isSecure()) {
            $response->header(
                'Strict-Transport-Security',
                'max-age=' . (int) $config['hsts_max_age'] . '; includeSubDomains'
            );
        }

        return $response;
    }

    private function buildCsp(Request $request): string
    {
        $nonce      = Nonce::value();
        $directives = (array) config('security.csp');

        // The nonce is what allows inlined critical CSS and JSON-LD without
        // opening the policy up with 'unsafe-inline'.
        $directives['script-src'] = "'self' 'nonce-{$nonce}'";
        $directives['style-src']  = "'self' 'nonce-{$nonce}'";

        /*
         * The rich-text editor writes inline style attributes, which style-src
         * blocks unless 'unsafe-inline' is present — and 'unsafe-inline' is ignored
         * whenever a nonce is also present, so the nonce has to go for this to work.
         *
         * Scoped to /admin only. The trade is deliberate and narrow: the public
         * site keeps the strict nonce policy, script-src stays locked everywhere
         * (which is where XSS actually lands), and the relaxed style rule applies
         * only to pages that already require an authenticated session.
         */
        if (str_starts_with($request->path(), '/admin')) {
            $directives['style-src'] = "'self' 'unsafe-inline'";
        }

        /*
         * Analytics widens the policy only when an ID is actually configured.
         *
         * A fresh install ships a strict policy and zero third-party requests;
         * turning on GA is what opts you into Google's domains, and nothing else.
         * Deliberately narrow: the tag script host and the collection endpoints,
         * not a wildcard.
         */
        if (!str_starts_with($request->path(), '/admin') && $this->analyticsEnabled()) {
            $directives['script-src'] .= ' https://www.googletagmanager.com';
            $directives['img-src']    .= ' https://www.googletagmanager.com https://www.google-analytics.com';
            $directives['connect-src'] .= ' https://www.google-analytics.com https://analytics.google.com'
                . ' https://region1.google-analytics.com';
        }

        // Meta Pixel widens the policy to Facebook's hosts, again only when a valid
        // pixel id is configured.
        if (!str_starts_with($request->path(), '/admin') && $this->metaPixelEnabled()) {
            $directives['script-src']  .= ' https://connect.facebook.net';
            $directives['img-src']     .= ' https://www.facebook.com https://connect.facebook.net';
            $directives['connect-src'] .= ' https://www.facebook.com';
        }

        /*
         * Google Fonts, only while the Appearance setting is switched to it. The
         * curated pairings are self-hosted, so the default install never reaches
         * fonts.googleapis.com and font-src stays 'self'.
         *
         * The stylesheet Google serves is a real stylesheet rather than a nonce'd
         * inline block, so style-src has to name the host explicitly.
         */
        if (Fonts::usesGoogle()) {
            $directives['style-src'] .= ' https://fonts.googleapis.com';
            $directives['font-src']  .= ' https://fonts.gstatic.com';
        }

        $parts = [];

        foreach ($directives as $directive => $value) {
            $parts[] = $directive . ' ' . $value;
        }

        return implode('; ', $parts);
    }

    /**
     * Reads the same settings the analytics partial does, and applies the same
     * format check — so a mistyped ID cannot widen the policy for a tag that will
     * never load.
     */
    private function analyticsEnabled(): bool
    {
        try {
            $ga4 = trim((string) Setting::get('ga_measurement_id', ''));
            $gtm = trim((string) Setting::get('gtm_id', ''));
        } catch (Throwable) {
            // Headers must still be sent if the database is unreachable.
            return false;
        }

        if (!Setting::bool('plugin_analytics_enabled', true)) {
            return false;
        }

        return preg_match('/^G-[A-Z0-9]{6,}$/i', $ga4) === 1
            || preg_match('/^GTM-[A-Z0-9]{4,}$/i', $gtm) === 1;
    }

    private function metaPixelEnabled(): bool
    {
        try {
            if (!Setting::bool('plugin_analytics_enabled', true) || !Setting::bool('meta_pixel_enabled', false)) {
                return false;
            }

            return preg_match('/^\d{6,20}$/', trim((string) Setting::get('meta_pixel_id', ''))) === 1;
        } catch (Throwable) {
            return false;
        }
    }
}
