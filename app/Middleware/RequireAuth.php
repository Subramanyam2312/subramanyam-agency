<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Gate for every /admin route except the login and password-reset screens.
 */
final class RequireAuth implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check() && !Auth::attemptRemember($request)) {
            // Remember where they were headed so login can bounce them back.
            Session::set('intended_url', $request->path());

            if ($request->wantsJson()) {
                return Response::json(['error' => ['code' => 'unauthenticated', 'message' => 'Login required.']], 401);
            }

            Session::flash('warning', 'Please sign in to continue.');

            return Response::redirect('/admin/login');
        }

        return $next($request);
    }
}
