<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Editable page copy, with a draft state.
 *
 * The admin screen renders its form from these rows, so adding a new editable
 * headline is an INSERT rather than a template change.
 *
 * `value` is what the public site shows. `draft_value` is work in progress and is
 * NULL once there is nothing pending. Saving in the CMS writes the draft only, so
 * the live site is never changed by typing — publishing is a separate, deliberate
 * step. Staff previewing a page from the portal see drafts; everyone else sees the
 * published copy, which is the safe default if the preview flag is never set.
 */
final class PageBlock extends Model
{
    protected static string $table = 'page_blocks';

    /** @var array<string,array<string,string>>|null Cached page_key => block_key => value */
    private static ?array $cache = null;

    /** Whether this request should render pending drafts instead of published copy. */
    private static bool $preview = false;

    /**
     * Reads a block's value. Templates call this constantly, so the whole table is
     * loaded once per request rather than queried per block.
     */
    public static function value(string $pageKey, string $blockKey, string $default = ''): string
    {
        if (self::$cache === null) {
            self::$cache = [];

            foreach (Database::select('SELECT `page_key`, `block_key`, `value`, `draft_value` FROM `page_blocks`') as $row) {
                // A draft only wins while previewing; otherwise the published value
                // is used, so a visitor can never be served unpublished copy.
                $value = self::$preview && $row['draft_value'] !== null
                    ? (string) $row['draft_value']
                    : (string) ($row['value'] ?? '');

                self::$cache[(string) $row['page_key']][(string) $row['block_key']] = $value;
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
            /*
             * The editor works on the draft. `value` is kept alongside as
             * `published_value` so the form can show what is currently live and
             * mark which fields have an unpublished edit.
             */
            $block['published_value'] = (string) ($block['value'] ?? '');
            $block['has_draft']       = $block['draft_value'] !== null;
            $block['value']           = $block['draft_value'] ?? $block['value'];

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

    /**
     * Renders pending drafts for the rest of this request.
     *
     * Only ever called for a signed-in staff preview. It clears the cache because
     * blocks may already have been read as published values.
     */
    public static function previewDrafts(bool $on = true): void
    {
        self::$preview = $on;
        self::$cache   = null;
    }

    public static function previewing(): bool
    {
        return self::$preview;
    }

    /** How many blocks have an edit waiting to be published. */
    public static function draftCount(): int
    {
        $row = Database::selectOne('SELECT COUNT(*) AS n FROM `page_blocks` WHERE `draft_value` IS NOT NULL');

        return (int) ($row['n'] ?? 0);
    }

    /** The pages that have pending edits, for showing where the work is. */
    public static function draftPages(): array
    {
        return array_column(
            Database::select(
                'SELECT DISTINCT `page_key` FROM `page_blocks` WHERE `draft_value` IS NOT NULL ORDER BY `page_key`'
            ),
            'page_key'
        );
    }

    /**
     * Moves every pending draft into the published value.
     *
     * @return int blocks published
     */
    public static function publishDrafts(): int
    {
        $count = self::draftCount();

        Database::query(
            'UPDATE `page_blocks` SET `value` = `draft_value`, `draft_value` = NULL, `updated_at` = NOW()
              WHERE `draft_value` IS NOT NULL'
        );

        self::flushCache();

        return $count;
    }

    /**
     * Throws pending drafts away, leaving the published copy untouched.
     *
     * @return int blocks discarded
     */
    public static function discardDrafts(): int
    {
        $count = self::draftCount();

        Database::query('UPDATE `page_blocks` SET `draft_value` = NULL WHERE `draft_value` IS NOT NULL');

        self::flushCache();

        return $count;
    }
}
