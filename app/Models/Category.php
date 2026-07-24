<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Category extends Model
{
    protected static string $table = 'categories';

    protected static bool $softDeletes = true;

    /**
     * Categories with their post counts, for the admin list and the public filter.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function withCounts(): array
    {
        return Database::select(
            "SELECT c.*, COUNT(p.id) AS post_count
             FROM `categories` c
             LEFT JOIN `posts` p
                    ON p.category_id = c.id
                   AND p.deleted_at IS NULL
                   AND p.status = 'published'
             WHERE c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.sort_order, c.name"
        );
    }

    /**
     * @return array<int,string> id => name, for select inputs
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all([], 'sort_order ASC, name ASC') as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }
}
