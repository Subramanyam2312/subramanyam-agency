<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class NewsletterSubscriber extends Model
{
    protected static string $table = 'newsletter_subscribers';

    protected static bool $timestamps = false;

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    public static function list(string $search, int $page, int $perPage = 50): array
    {
        $conditions = [];

        if ($search !== '') {
            $conditions['email LIKE'] = '%' . $search . '%';
        }

        return self::paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    public static function activeCount(): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM `newsletter_subscribers` WHERE `unsubscribed_at` IS NULL'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function forExport(): array
    {
        return Database::select(
            'SELECT `email`, `source`, `confirmed_at`, `unsubscribed_at`, `created_at`
             FROM `newsletter_subscribers`
             ORDER BY `created_at` DESC'
        );
    }
}
