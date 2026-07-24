<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Api;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;

/**
 * Bearer token authentication and rate limiting for /api/v1.
 *
 * The resolved token is handed to Auth so that anything written during an API
 * request is attributed to both the owning user and the specific token — a post
 * created by an agent is traceable to the credential that made it, not just to
 * whoever happens to own that credential.
 */
final class AuthenticateApiToken implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $plaintext = $request->bearerToken();

        if ($plaintext === null) {
            return Api::error(
                'unauthenticated',
                'Provide a token as: Authorization: Bearer <token>',
                401
            )->header('WWW-Authenticate', 'Bearer');
        }

        $token = ApiToken::resolve($plaintext);

        if ($token === null) {
            // One message for missing, wrong, revoked and expired alike — telling a
            // caller that a token "exists but expired" confirms the string was real.
            return Api::error('invalid_token', 'That token is not valid.', 401)
                ->header('WWW-Authenticate', 'Bearer');
        }

        // Keyed on the token, not the IP: an agent behind a shared address should
        // not be throttled by someone else's traffic, and rotating IPs must not
        // reset the limit.
        $throttleKey = 'api:' . $token['id'];
        $maxRequests = (int) config('security.api.max_requests', 120);
        $window      = (int) config('security.api.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxRequests)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            return Api::error('rate_limited', 'Too many requests. Slow down.', 429)
                ->header('Retry-After', (string) $retryAfter)
                ->header('X-RateLimit-Limit', (string) $maxRequests)
                ->header('X-RateLimit-Remaining', '0');
        }

        $used = RateLimiter::hit($throttleKey, $window);

        ApiToken::touch((int) $token['id']);

        $request->setApiToken($token);

        Auth::setApiIdentity([
            'id'    => (int) $token['user_id'],
            'name'  => $token['user_name'],
            'email' => $token['user_email'],
            'role'  => $token['user_role'],
        ], (int) $token['id']);

        $response = $next($request);

        return $response
            ->header('X-RateLimit-Limit', (string) $maxRequests)
            ->header('X-RateLimit-Remaining', (string) max(0, $maxRequests - $used));
    }
}
