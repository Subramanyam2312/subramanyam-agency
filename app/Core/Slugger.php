<?php

declare(strict_types=1);

namespace App\Core;

final class Slugger
{
    public static function make(string $value): string
    {
        $value = trim($value);

        // Transliterate accented characters where the platform can; iconv returns
        // false on some builds, so the original string stays the fallback.
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            $value = 'item-' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        return substr($value, 0, 180);
    }

    /**
     * Appends -2, -3 … until the slug is free within the given table.
     * Soft-deleted rows are included in the check, because they still hold the
     * unique index and could be restored later.
     */
    public static function unique(string $value, string $table, int|string|null $ignoreId = null, string $column = 'slug'): string
    {
        $base = self::make($value);
        $slug = $base;
        $n    = 1;

        $table  = Database::identifier($table);
        $column = Database::identifier($column);

        while (true) {
            $sql    = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :slug', $table, $column);
            $params = [':slug' => $slug];

            if ($ignoreId !== null) {
                $sql .= ' AND `id` != :ignore';
                $params[':ignore'] = $ignoreId;
            }

            if ((int) Database::scalar($sql, $params) === 0) {
                return $slug;
            }

            $slug = $base . '-' . (++$n);
        }
    }
}
