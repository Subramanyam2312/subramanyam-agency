<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class ContactSubmission extends Model
{
    protected static string $table = 'contact_submissions';

    protected static bool $softDeletes = true;

    protected static bool $timestamps = false;

    /**
     * @param array<string,mixed> $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    public static function inbox(array $filters, int $page, int $perPage = 25): array
    {
        $where  = ['cs.deleted_at IS NULL'];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $where[]           = '(cs.name LIKE :search OR cs.email LIKE :search OR cs.company LIKE :search OR cs.message LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $state = $filters['state'] ?? '';

        if ($state === 'unread') {
            $where[] = 'cs.is_read = 0 AND cs.is_spam = 0';
        } elseif ($state === 'read') {
            $where[] = 'cs.is_read = 1 AND cs.is_spam = 0';
        } elseif ($state === 'spam') {
            $where[] = 'cs.is_spam = 1';
        } else {
            // Spam is excluded from the default view; it has its own filter.
            $where[] = 'cs.is_spam = 0';
        }

        $clause = 'WHERE ' . implode(' AND ', $where);

        $total  = (int) Database::scalar("SELECT COUNT(*) FROM `contact_submissions` cs {$clause}", $params);
        $last   = max(1, (int) ceil($total / $perPage));
        $page   = max(1, min($page, $last));
        $offset = ($page - 1) * $perPage;

        return [
            'data' => Database::select(
                "SELECT cs.*, s.title AS service_title
                 FROM `contact_submissions` cs
                 LEFT JOIN `services` s ON s.id = cs.service_id
                 {$clause}
                 ORDER BY cs.created_at DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                $params
            ),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $last,
        ];
    }

    public static function unreadCount(): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM `contact_submissions` WHERE `is_read` = 0 AND `is_spam` = 0 AND `deleted_at` IS NULL'
        );
    }

    /**
     * Every non-deleted submission, for CSV export.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forExport(): array
    {
        return Database::select(
            'SELECT cs.id, cs.name, cs.email, cs.phone, cs.company, s.title AS service,
                    cs.budget_range, cs.message, cs.is_read, cs.is_spam, cs.created_at
             FROM `contact_submissions` cs
             LEFT JOIN `services` s ON s.id = cs.service_id
             WHERE cs.deleted_at IS NULL
             ORDER BY cs.created_at DESC'
        );
    }
}
