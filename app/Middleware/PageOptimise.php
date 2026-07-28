<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\PageCache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Traffic;

/**
 * Public-site optimisation: serve pages from the full-page cache when possible,
 * store fresh renders, and count traffic.
 *
 * Placed after SecurityHeaders in the pipeline, so a cache hit that short-circuits
 * here still comes back out through SecurityHeaders and gets the full header set.
 * It no-ops for the admin, the API and non-HTML responses by construction.
 */
final class PageOptimise implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        // 1. Serve from the built-in cache if a fresh copy exists. Traffic is still
        //    counted here because PHP is running — only a LiteSpeed-served hit
        //    bypasses PHP entirely.
        $cached = PageCache::get($request);

        if ($cached !== null) {
            Traffic::record($request);

            return Response::html($cached)->header('X-Page-Cache', 'HIT');
        }

        $response = $next($request);

        // 2. Only act on a successful public HTML page.
        if ($this->isPublicHtml($request, $response)) {
            Traffic::record($request);

            $body = $response->getBody();

            if (trim($body) !== '') {
                PageCache::put($request, $body);

                $ls = PageCache::liteSpeedHeader($request);

                if ($ls !== null) {
                    // LiteSpeed on the host reads this and serves future hits itself.
                    $response->header('X-LiteSpeed-Cache-Control', $ls);
                    $response->header('X-Page-Cache', 'MISS');
                }
            }
        }

        return $response;
    }

    private function isPublicHtml(Request $request, Response $response): bool
    {
        if ($response->getStatus() !== 200) {
            return false;
        }

        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $path = $request->path();

        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api/')) {
            return false;
        }

        $type = (string) $response->getHeader('Content-Type');

        return str_contains($type, 'text/html');
    }
}
