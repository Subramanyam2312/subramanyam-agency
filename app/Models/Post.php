<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Post extends Model
{
    protected static string $table = 'posts';

    protected static bool $softDeletes = true;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';

    /**
     * @return array<string,string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_PUBLISHED => 'Published',
        ];
    }

    /**
     * Flips scheduled posts whose time has come.
     *
     * Called by the cron script AND lazily on public blog reads. Hostinger's cron
     * floor is five minutes, and a cron job that silently stops is a common shared
     * hosting failure — the lazy path means the site is never wrong even then.
     */
    public static function publishDue(): int
    {
        return Database::query(
            "UPDATE `posts`
             SET `status` = 'published'
             WHERE `status` = 'scheduled'
               AND `published_at` IS NOT NULL
               AND `published_at` <= NOW()
               AND `deleted_at` IS NULL"
        )->rowCount();
    }

    /**
     * Admin list query: joins the display columns and supports search plus filters
     * in one statement rather than N+1 lookups per row.
     *
     * @param array<string,mixed> $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    public static function adminList(array $filters, int $page, int $perPage): array
    {
        $where  = ['p.deleted_at IS NULL'];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            // LIKE rather than the FULLTEXT index: editors search for partial words
            // and half-typed slugs, which MATCH() does not handle.
            $where[]           = '(p.title LIKE :search OR p.slug LIKE :search OR p.excerpt LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['status'] ?? '') !== '') {
            $where[]           = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (($filters['category_id'] ?? '') !== '') {
            $where[]             = 'p.category_id = :category';
            $params[':category'] = (int) $filters['category_id'];
        }

        $clause = 'WHERE ' . implode(' AND ', $where);

        $total   = (int) Database::scalar("SELECT COUNT(*) FROM `posts` p {$clause}", $params);
        $last    = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min($page, $last));
        $offset  = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT p.*, c.name AS category_name, u.name AS author_name
             FROM `posts` p
             LEFT JOIN `categories` c ON c.id = p.category_id
             LEFT JOIN `users` u ON u.id = p.author_id
             {$clause}
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $last,
        ];
    }

    /**
     * Replaces a post's tags, creating any that do not exist yet.
     *
     * @param array<int,string> $names
     */
    public static function syncTags(int $postId, array $names): void
    {
        Database::delete('post_tags', ['post_id' => $postId]);

        $seen = [];

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $slug = \App\Core\Slugger::make($name);

            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;

            $tag = Tag::findBy('slug', $slug);

            $tagId = $tag !== null
                ? (int) $tag['id']
                : Database::insert('tags', ['name' => $name, 'slug' => $slug, 'created_at' => date('Y-m-d H:i:s')]);

            Database::insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function tagsFor(int $postId): array
    {
        return Database::select(
            'SELECT t.* FROM `tags` t
             INNER JOIN `post_tags` pt ON pt.tag_id = t.id
             WHERE pt.post_id = :id
             ORDER BY t.name',
            [':id' => $postId]
        );
    }

    public static function tagNamesFor(int $postId): string
    {
        return implode(', ', array_column(self::tagsFor($postId), 'name'));
    }
}
