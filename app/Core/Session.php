<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session wrapper.
 *
 * Two things here matter on shared hosting:
 *  - the save path is moved into storage/sessions, because the default /tmp is
 *    world-readable and shared with every other account on the machine;
 *  - flash data and old input are lifted out of $_SESSION at boot, so they survive
 *    exactly one request without any manual clean-up in controllers.
 */
final class Session
{
    private static bool $started = false;

    /** @var array<string,mixed> */
    private static array $flash = [];

    /** @var array<string,mixed> */
    private static array $old = [];

    /** @var array<string,string> */
    private static array $errors = [];

    public static function start(bool $secure = false): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $path = STORAGE_PATH . '/sessions';

        if (!is_dir($path)) {
            mkdir($path, 0770, true);
        }

        session_name(config('session.name', 'agency_session'));
        session_save_path($path);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        self::$started = true;

        // Consume one-request data now so the next request starts clean.
        self::$flash  = $_SESSION['_flash']  ?? [];
        self::$old    = $_SESSION['_old']    ?? [];
        self::$errors = $_SESSION['_errors'] ?? [];

        unset($_SESSION['_flash'], $_SESSION['_old'], $_SESSION['_errors']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Queue a message for the next request. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /** Read a message queued by the previous request. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::$flash[$key] ?? $default;
    }

    public static function hasFlash(string $key): bool
    {
        return isset(self::$flash[$key]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function flashInput(array $input): void
    {
        // Never bounce a password back into a re-rendered form.
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['current_password']);

        $_SESSION['_old'] = $input;
    }

    public static function oldInput(string $key, mixed $default = ''): mixed
    {
        return self::$old[$key] ?? $default;
    }

    /**
     * @param array<string,string> $errors
     */
    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    /**
     * @return array<string,string>
     */
    public static function errors(): array
    {
        return self::$errors;
    }

    /**
     * Called on privilege changes (login, logout, password change) to defeat
     * session fixation.
     */
    public static function regenerate(): void
    {
        if (self::$started) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (!self::$started) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }
}
