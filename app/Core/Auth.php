<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session authentication for the admin portal, plus the identity slot the API
 * token middleware fills in so that activity logging works the same either way.
 *
 * Two independent brakes sit in front of the login form:
 *   - a per-IP+email rate limit, which stops a fast script;
 *   - a per-account lockout stored on the user row, which survives an attacker
 *     rotating IP addresses.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

    private static ?int $apiTokenId = null;

    /**
     * @return array{ok:bool, message:string, user?:array<string,mixed>}
     */
    public static function attempt(string $email, string $password, bool $remember, Request $request): array
    {
        $throttleKey = 'login:' . strtolower($email) . '|' . $request->ip();
        $maxAttempts = (int) config('security.login.max_attempts', 5);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return [
                'ok'      => false,
                'message' => 'Too many login attempts. Try again in ' . self::humanSeconds($seconds) . '.',
            ];
        }

        $user = Database::selectOne(
            'SELECT * FROM `users` WHERE `email` = :email AND `deleted_at` IS NULL LIMIT 1',
            [':email' => strtolower(trim($email))]
        );

        // Equalise timing between "no such account" and "wrong password" so the
        // form cannot be used to enumerate which email addresses exist.
        if ($user === null) {
            password_verify($password, '$2y$12$usesomesillystringfortestingpurposesonlyxxxxxxxxxxxxxxxxxxxxxx');
            RateLimiter::hit($throttleKey, (int) config('security.login.decay_seconds', 900));

            return ['ok' => false, 'message' => 'Those credentials do not match our records.'];
        }

        if ((int) $user['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'This account has been deactivated.'];
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            $seconds = strtotime((string) $user['locked_until']) - time();

            return [
                'ok'      => false,
                'message' => 'This account is temporarily locked. Try again in ' . self::humanSeconds($seconds) . '.',
            ];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            RateLimiter::hit($throttleKey, (int) config('security.login.decay_seconds', 900));
            self::registerFailure($user);

            return ['ok' => false, 'message' => 'Those credentials do not match our records.'];
        }

        // Transparently upgrade the hash if the cost factor or algorithm has moved on.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            Database::update('users', ['password_hash' => self::hash($password)], ['id' => $user['id']]);
        }

        RateLimiter::clear($throttleKey);

        Database::update('users', [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $user['id']]);

        self::login($user, $remember, $request);

        return ['ok' => true, 'message' => 'Welcome back.', 'user' => $user];
    }

    /**
     * @param array<string,mixed> $user
     */
    public static function login(array $user, bool $remember, Request $request): void
    {
        // New session id on privilege change — defeats session fixation.
        Session::regenerate();
        Session::set(self::SESSION_KEY, (int) $user['id']);
        Csrf::rotate();

        self::$user     = $user;
        self::$resolved = true;

        if ($remember) {
            self::issueRememberToken((int) $user['id'], $request);
        }

        ActivityLogger::log('auth.login', 'user', (int) $user['id'], [], (int) $user['id']);
    }

    public static function logout(Request $request): void
    {
        $userId = self::id();

        if ($userId !== null) {
            ActivityLogger::log('auth.logout', 'user', $userId, [], $userId);
        }

        self::clearRememberToken($request);

        Session::destroy();

        self::$user     = null;
        self::$resolved = true;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        $id             = Session::get(self::SESSION_KEY);

        if ($id === null) {
            return self::$user = null;
        }

        $user = Database::selectOne(
            'SELECT * FROM `users` WHERE `id` = :id AND `deleted_at` IS NULL AND `is_active` = 1 LIMIT 1',
            [':id' => (int) $id]
        );

        // Deactivated or deleted mid-session: drop the session rather than serve it.
        if ($user === null) {
            Session::forget(self::SESSION_KEY);
        }

        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && $user['role'] === 'admin';
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();

        return $user !== null && in_array((string) $user['role'], $roles, true);
    }

    /**
     * Set by ApiTokenMiddleware so activity written during an API request is
     * attributed to both the owning user and the specific token.
     *
     * @param array<string,mixed> $user
     */
    public static function setApiIdentity(array $user, int $tokenId): void
    {
        self::$user       = $user;
        self::$resolved   = true;
        self::$apiTokenId = $tokenId;
    }

    public static function apiTokenId(): ?int
    {
        return self::$apiTokenId;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2,
        ]);
    }

    // ---------------------------------------------------------------- remember me

    /**
     * Attempts to restore a session from the remember-me cookie.
     * Tokens are single-use: each successful restore rotates the stored value, so a
     * stolen cookie stops working the moment the real user's browser reconnects.
     */
    public static function attemptRemember(Request $request): bool
    {
        if (self::check()) {
            return true;
        }

        $cookie = $_COOKIE[(string) config('security.remember.cookie', 'agency_remember')] ?? null;

        if (!is_string($cookie) || $cookie === '') {
            return false;
        }

        $row = Database::selectOne(
            'SELECT rt.*, u.id AS uid
             FROM `remember_tokens` rt
             INNER JOIN `users` u ON u.id = rt.user_id
             WHERE rt.token_hash = :hash
               AND rt.expires_at > NOW()
               AND u.deleted_at IS NULL
               AND u.is_active = 1
             LIMIT 1',
            [':hash' => hash('sha256', $cookie)]
        );

        if ($row === null) {
            self::clearRememberToken($request);

            return false;
        }

        Database::delete('remember_tokens', ['id' => $row['id']]);

        $user = Database::selectOne('SELECT * FROM `users` WHERE `id` = :id LIMIT 1', [':id' => $row['user_id']]);

        if ($user === null) {
            return false;
        }

        self::login($user, true, $request);

        return true;
    }

    private static function issueRememberToken(int $userId, Request $request): void
    {
        $token = bin2hex(random_bytes(32));
        $days  = (int) config('security.remember.days', 30);

        Database::insert('remember_tokens', [
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + ($days * 86400)),
            'ip_hash'    => $request->ipHash(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Safe to call directly: the response body is assembled in memory and not
        // flushed until Response::send(), so no headers have gone out yet.
        setcookie((string) config('security.remember.cookie', 'agency_remember'), $token, [
            'expires'  => time() + ($days * 86400),
            'path'     => '/',
            'secure'   => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberToken(Request $request): void
    {
        $name   = (string) config('security.remember.cookie', 'agency_remember');
        $cookie = $_COOKIE[$name] ?? null;

        if (is_string($cookie) && $cookie !== '') {
            Database::delete('remember_tokens', ['token_hash' => hash('sha256', $cookie)]);
        }

        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // ------------------------------------------------------------ password resets

    /**
     * Stores a hashed, expiring reset token and returns the plaintext for emailing.
     */
    public static function createPasswordReset(int $userId): string
    {
        $token   = bin2hex(random_bytes(32));
        $minutes = (int) config('security.reset.expires_minutes', 60);

        Database::update('users', [
            'reset_token_hash' => hash('sha256', $token),
            'reset_expires_at' => date('Y-m-d H:i:s', time() + ($minutes * 60)),
        ], ['id' => $userId]);

        return $token;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findByResetToken(string $token): ?array
    {
        return Database::selectOne(
            'SELECT * FROM `users`
             WHERE `reset_token_hash` = :hash
               AND `reset_expires_at` > NOW()
               AND `deleted_at` IS NULL
               AND `is_active` = 1
             LIMIT 1',
            [':hash' => hash('sha256', $token)]
        );
    }

    public static function completePasswordReset(int $userId, string $password): void
    {
        Database::update('users', [
            'password_hash'    => self::hash($password),
            'reset_token_hash' => null,
            'reset_expires_at' => null,
            'failed_attempts'  => 0,
            'locked_until'     => null,
        ], ['id' => $userId]);

        // A password change invalidates every remembered device.
        Database::delete('remember_tokens', ['user_id' => $userId]);

        ActivityLogger::log('auth.password_reset', 'user', $userId, [], $userId);
    }

    // ------------------------------------------------------------------- internals

    /**
     * @param array<string,mixed> $user
     */
    private static function registerFailure(array $user): void
    {
        $attempts  = ((int) $user['failed_attempts']) + 1;
        $threshold = (int) config('security.login.lockout_after', 8);
        $data      = ['failed_attempts' => $attempts];

        if ($attempts >= $threshold) {
            $minutes              = (int) config('security.login.lockout_minutes', 15);
            $data['locked_until'] = date('Y-m-d H:i:s', time() + ($minutes * 60));
            $data['failed_attempts'] = 0;

            ActivityLogger::log('auth.locked', 'user', (int) $user['id'], [
                'threshold' => $threshold,
            ], (int) $user['id']);
        }

        Database::update('users', $data, ['id' => $user['id']]);
    }

    private static function humanSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
}
