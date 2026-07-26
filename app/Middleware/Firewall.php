<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Firewall as Engine;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * The first middleware in the pipeline. A request the firewall rejects never
 * reaches CSRF, auth, routing or a controller.
 *
 * Rejections throw an HttpException, which the front controller renders through the
 * normal error path — so a blocked request still comes back with the full security
 * header set, not a bare string.
 *
 * Authenticated users are exempt from the heuristic rules (signatures, scanner
 * agents, flood). Combined with the "can't add your own IP" guard in the admin
 * panel and the env allowlist, that is what makes self-lockout effectively
 * impossible: a signed-in admin cannot trip a rule that would ban them, and an
 * explicit manual block can never target their current address.
 */
final class Firewall implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Engine::enabled()) {
            return $next($request);
        }

        $ip = $request->ip();

        // The escape hatch always wins, checked before anything else.
        if (Engine::allowlisted($ip)) {
            return $next($request);
        }

        // 1. Standing blocklist — applies to everyone, authenticated or not. The
        //    self-lockout guards live at the point a block is created, not here.
        if (Engine::activeBlock($ip) !== null) {
            Engine::log($request, 'ip_block', 'blocked');

            throw new HttpException(403, 'Your access to this site has been blocked.');
        }

        // 2. Trusted sessions skip the heuristics, so staff can never auto-ban
        //    themselves by, say, pasting a snippet into a URL.
        if (Auth::check()) {
            return $next($request);
        }

        // 3. Attack signatures and scanner agents on the request line.
        $hit = Engine::inspect($request);

        if ($hit !== null) {
            $banned = Engine::recordStrike($ip);
            Engine::log($request, $hit['rule'], $banned ? 'banned' : 'blocked');

            throw new HttpException($hit['status'], $hit['message']);
        }

        // 4. Per-IP flood cap. Only unauthenticated dynamic requests reach here.
        if (Engine::registerFloodHit($ip)) {
            Engine::log($request, 'flood', 'banned');

            throw new HttpException(429, 'Too many requests. Please slow down.', [
                'Retry-After' => (string) ((int) config('security.firewall.ban_minutes', 60) * 60),
            ]);
        }

        return $next($request);
    }
}
