<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Media extends Model
{
    protected static string $table = 'media';

    protected static bool $softDeletes = true;

    /** width => path, generated WebP renditions. */
    protected static array $jsonColumns = ['variants'];

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    public static function browse(string $search, int $page, int $perPage = 24): array
    {
        $conditions = [];

        if ($search !== '') {
            $conditions['@raw'] = [
                '(`original_name` LIKE ' . Database::connection()->quote('%' . $search . '%')
                . ' OR `alt_text` LIKE ' . Database::connection()->quote('%' . $search . '%') . ')',
            ];
        }

        return self::paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    /**
     * Builds a srcset from the generated variants.
     *
     * Returns an empty string when there are none (SVG, or an upload smaller than
     * every configured width), which is the correct thing to put in the attribute.
     *
     * @param array<string,mixed> $media
     */
    public static function srcset(array $media): string
    {
        $variants = is_array($media['variants'] ?? null)
            ? $media['variants']
            : json_column($media['variants'] ?? null);

        if ($variants === []) {
            return '';
        }

        $parts = [];

        /*
         * Each candidate goes through asset() for the same ?v=<mtime> the src gets.
         *
         * Without it these URLs are immutable to the browser, and .htaccess caches
         * image/webp for a year — so anyone who loaded a page while a variant was
         * still the 0-byte file GD used to write (fixed in 29e5278) keeps being served
         * that empty response from their own cache for up to twelve months. The file
         * on disk is correct, a fresh fetch decodes fine, and only the <img> is broken:
         * naturalWidth reads 0x0 while createImageBitmap() on the same URL succeeds.
         * Regenerating variants does not help either, because the URL never changes.
         * Tying the candidate to mtime means a rewritten variant is simply a new URL.
         */
        foreach ($variants as $width => $path) {
            $parts[] = asset((string) $path) . ' ' . (int) $width . 'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Everything currently pointing at a media row, so the delete confirmation can
     * say what will break rather than discovering it afterwards.
     *
     * @return array<int,string>
     */
    public static function usage(int $mediaId): array
    {
        $checks = [
            ['posts',        'featured_media_id', 'title',       'blog post'],
            ['posts',        'og_media_id',       'title',       'blog post (social image)'],
            ['services',     'image_media_id',    'title',       'service'],
            ['case_studies', 'cover_media_id',    'title',       'case study'],
            ['testimonials', 'media_id',          'author_name', 'testimonial'],
            ['client_logos', 'media_id',          'name',        'client logo'],
            ['page_blocks',  'media_id',          'label',       'page block'],
            ['users',        'avatar_media_id',   'name',        'user avatar'],
        ];

        $used = [];

        foreach ($checks as [$table, $column, $labelColumn, $description]) {
            $rows = Database::select(
                sprintf(
                    'SELECT `%s` AS label FROM `%s` WHERE `%s` = :id LIMIT 5',
                    Database::identifier($labelColumn),
                    Database::identifier($table),
                    Database::identifier($column)
                ),
                [':id' => $mediaId]
            );

            foreach ($rows as $row) {
                $used[] = $description . ': ' . $row['label'];
            }
        }

        return $used;
    }
}
