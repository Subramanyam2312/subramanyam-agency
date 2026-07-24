<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Nonce;
use App\Core\Request;
use App\Core\Response;

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

        $response->header('Content-Security-Policy', $this->buildCsp());
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

    private function buildCsp(): string
    {
        $nonce      = Nonce::value();
        $directives = (array) config('security.csp');

        // The nonce is what allows inlined critical CSS and JSON-LD without
        // opening the policy up with 'unsafe-inline'.
        $directives['script-src'] = "'self' 'nonce-{$nonce}'";
        $directives['style-src']  = "'self' 'nonce-{$nonce}'";

        $parts = [];

        foreach ($directives as $directive => $value) {
            $parts[] = $directive . ' ' . $value;
        }

        return implode('; ', $parts);
    }
}
