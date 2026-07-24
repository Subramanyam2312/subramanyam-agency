<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Rejects any state-changing request that does not carry the session's CSRF token.
 *
 * The API is exempt because it authenticates with a bearer token rather than a
 * cookie, so there is no ambient authority for a cross-site request to borrow.
 */
final class VerifyCsrf implements Middleware
{
    private const READ_ONLY = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), self::READ_ONLY, true)) {
            return $next($request);
        }

        if (str_starts_with($request->path(), '/api/')) {
            return $next($request);
        }

        if (!Csrf::verify($request)) {
            throw new HttpException(419);
        }

        return $next($request);
    }
}
