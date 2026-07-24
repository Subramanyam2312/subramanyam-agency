<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Restricts a route to the `admin` role.
 *
 * Applied to settings, users, API tokens and SEO configuration. The `editor` role
 * reaches every content module but none of these.
 *
 * Always stacked after RequireAuth, which guarantees a user is present.
 */
final class RequireAdmin implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, 'That area is restricted to administrators.');
        }

        return $next($request);
    }
}
