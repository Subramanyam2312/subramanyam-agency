<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Editable page copy.
 *
 * The admin screen renders its form from these rows, so adding a new editable
 * headline is an INSERT rather than a template change.
 */
final class PageBlock extends Model
{
    protected static string $table = 'page_blocks';

    /** @var array<string,array<string,string>>|null Cached page_key => block_key => value */
    private static ?array $cache = null;

    /**
     * Reads a block's value. Templates call this constantly, so the whole table is
     * loaded once per request rather than queried per block.
     */
    public static function value(string $pageKey, string $blockKey, string $default = ''): string
    {
        if (self::$cache === null) {
            self::$cache = [];

            foreach (Database::select('SELECT `page_key`, `block_key`, `value` FROM `page_blocks`') as $row) {
                self::$cache[(string) $row['page_key']][(string) $row['block_key']] = (string) ($row['value'] ?? '');
            }
        }

        $value = self::$cache[$pageKey][$blockKey] ?? '';

        return $value === '' ? $default : $value;
    }

    /**
     * Blocks for one page, bucketed by their display group.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function forPageGrouped(string $pageKey): array
    {
        $grouped = [];

        foreach (self::all(['page_key' => $pageKey], 'sort_order ASC, id ASC') as $block) {
            $grouped[(string) $block['group_name']][] = $block;
        }

        return $grouped;
    }

    /**
     * @return array<int,string>
     */
    public static function pageKeys(): array
    {
        return array_column(
            Database::select('SELECT DISTINCT `page_key` FROM `page_blocks` ORDER BY `page_key`'),
            'page_key'
        );
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
