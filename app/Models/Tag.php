<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Tag extends Model
{
    protected static string $table = 'tags';

    protected static bool $timestamps = false;

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function withCounts(): array
    {
        return Database::select(
            "SELECT t.*, COUNT(pt.post_id) AS post_count
             FROM `tags` t
             LEFT JOIN `post_tags` pt ON pt.tag_id = t.id
             LEFT JOIN `posts` p ON p.id = pt.post_id AND p.deleted_at IS NULL
             GROUP BY t.id
             ORDER BY t.name"
        );
    }

    /**
     * Tags attached to nothing. Surfaced in the admin so the list does not silently
     * accumulate leftovers every time a post is retagged.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function orphans(): array
    {
        return Database::select(
            'SELECT t.* FROM `tags` t
             LEFT JOIN `post_tags` pt ON pt.tag_id = t.id
             WHERE pt.tag_id IS NULL
             ORDER BY t.name'
        );
    }
}
