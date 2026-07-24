<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface Middleware
{
    /**
     * Either return a Response of your own (short-circuiting the pipeline),
     * or call $next($request) and optionally decorate what comes back.
     *
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response;
}
