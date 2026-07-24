<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Key/value settings store.
 *
 * Not a Model subclass: the primary key is a string, and every read in a request
 * should come from one cached fetch rather than a query per key.
 *
 * The columns are `setting_key`/`setting_value` rather than `key`/`value` because
 * KEY is a reserved word in MySQL, and a table whose every query needs back-quoting
 * to parse is a table someone will eventually break.
 */
final class Setting
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = Database::select('SELECT `setting_key`, `setting_value` FROM `settings`');

        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return self::$cache = $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string,mixed>
     */
    public static function json(string $key): array
    {
        return json_column(self::get($key, '[]'));
    }

    public static function set(string $key, mixed $value, string $type = 'text', string $group = 'general'): void
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        // Portable upsert: no VALUES() and no row alias, so this runs unchanged on
        // both MySQL 8 and the MariaDB builds Hostinger may provide.
        $updated = Database::update(
            'settings',
            ['setting_value' => (string) $value, 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => $key]
        );

        if ($updated === 0 && !self::exists($key)) {
            Database::insert('settings', [
                'setting_key'   => $key,
                'setting_value' => (string) $value,
                'type'          => $type,
                'group_name'    => $group,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        self::$cache = null;
    }

    /**
     * @param array<string,mixed> $values
     */
    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value, 'text', $group);
        }
    }

    public static function exists(string $key): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM `settings` WHERE `setting_key` = :key',
            [':key' => $key]
        ) > 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function group(string $group): array
    {
        return Database::select(
            'SELECT * FROM `settings` WHERE `group_name` = :group ORDER BY `setting_key`',
            [':group' => $group]
        );
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
