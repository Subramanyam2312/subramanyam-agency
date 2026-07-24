<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Minimal .env reader.
 *
 * Deliberately not vlucas/phpdotenv: this is ~60 lines, has no dependencies, and
 * critically it keeps values in a private static array instead of calling putenv().
 * putenv() leaks secrets into the environment of any process PHP spawns, which on
 * shared hosting is a real concern.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_readable($path)) {
            throw new RuntimeException(
                'Missing .env file at ' . $path . '. Copy .env.example to .env and fill it in.'
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key   = trim(preg_replace('/^export\s+/', '', $key));
            $value = trim($value);

            // Strip one layer of matching quotes, then unescape \n inside double quotes.
            if (strlen($value) > 1) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);

                    if ($first === '"') {
                        $value = str_replace(['\\n', '\\r', '\\"'], ["\n", "\r", '"'], $value);
                    }
                }
            }

            self::$vars[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$vars)) {
            return $default;
        }

        $value = self::$vars[$key];

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    /**
     * Fetch a value that the application cannot run without.
     */
    public static function require(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Required environment variable {$key} is not set.");
        }

        return (string) $value;
    }
}
