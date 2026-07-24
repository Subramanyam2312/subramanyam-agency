<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Faq extends Model
{
    protected static string $table = 'faqs';

    protected static bool $softDeletes = true;

    /**
     * Active FAQs bucketed by group, ready for the accordion and FAQPage JSON-LD.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all(['is_active' => 1], 'sort_order ASC, id ASC') as $faq) {
            $grouped[(string) $faq['group_name']][] = $faq;
        }

        return $grouped;
    }

    /**
     * Existing group names, so the editor can reuse one instead of inventing a
     * near-duplicate ("General" vs "general") that splits the accordion in two.
     *
     * @return array<int,string>
     */
    public static function groupNames(): array
    {
        return array_column(
            Database::select('SELECT DISTINCT `group_name` FROM `faqs` WHERE `deleted_at` IS NULL ORDER BY `group_name`'),
            'group_name'
        );
    }
}
