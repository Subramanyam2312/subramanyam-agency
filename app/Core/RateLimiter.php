<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;

/**
 * Fixed-window rate limiter backed by the database.
 *
 * Shared hosting has no Redis and no APCu worth relying on, so the counter lives in
 * a table. The SQL deliberately avoids both the VALUES() function and the newer row
 * alias syntax for ON DUPLICATE KEY UPDATE, because one is deprecated in MySQL 8.4
 * and the other is unsupported on older MariaDB — and Hostinger may hand us either.
 */
final class RateLimiter
{
    private const TABLE = 'rate_limits';

    /**
     * Records one attempt and returns the running count for the current window.
     */
    public static function hit(string $key, int $decaySeconds): int
    {
        $bucket = self::bucket($key);

        // Drop the row if its window has already closed, so the counter restarts.
        Database::query(
            'DELETE FROM `' . self::TABLE . '` WHERE `bucket` = :bucket AND `expires_at` <= NOW()',
            [':bucket' => $bucket]
        );

        try {
            Database::query(
                'INSERT INTO `' . self::TABLE . '` (`bucket`, `hits`, `expires_at`, `created_at`)
                 VALUES (:bucket, 1, DATE_ADD(NOW(), INTERVAL ' . max(1, $decaySeconds) . ' SECOND), NOW())
                 ON DUPLICATE KEY UPDATE `hits` = `hits` + 1',
                [':bucket' => $bucket]
            );
        } catch (PDOException $e) {
            // Two requests raced on the same fresh bucket; the row exists now.
            Database::query(
                'UPDATE `' . self::TABLE . '` SET `hits` = `hits` + 1 WHERE `bucket` = :bucket',
                [':bucket' => $bucket]
            );
        }

        // Cheap opportunistic sweep; the cron does the thorough one.
        if (random_int(1, 50) === 1) {
            self::sweep();
        }

        return self::attempts($key);
    }

    public static function attempts(string $key): int
    {
        return (int) Database::scalar(
            'SELECT `hits` FROM `' . self::TABLE . '` WHERE `bucket` = :bucket AND `expires_at` > NOW()',
            [':bucket' => self::bucket($key)]
        );
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) >= $maxAttempts;
    }

    /**
     * Seconds until the current window closes. Feeds the Retry-After header.
     */
    public static function availableIn(string $key): int
    {
        $seconds = Database::scalar(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), `expires_at`) FROM `' . self::TABLE . '`
             WHERE `bucket` = :bucket AND `expires_at` > NOW()',
            [':bucket' => self::bucket($key)]
        );

        return max(0, (int) $seconds);
    }

    /**
     * Called after a successful login so a user who fumbled their password twice
     * is not still one attempt from a lockout.
     */
    public static function clear(string $key): void
    {
        Database::query(
            'DELETE FROM `' . self::TABLE . '` WHERE `bucket` = :bucket',
            [':bucket' => self::bucket($key)]
        );
    }

    public static function sweep(): int
    {
        return Database::query('DELETE FROM `' . self::TABLE . '` WHERE `expires_at` <= NOW()')->rowCount();
    }

    /**
     * Hashed so the table never stores a raw IP address or email, and so the
     * column stays inside its index length regardless of key content.
     */
    private static function bucket(string $key): string
    {
        return hash('sha256', $key);
    }
}
