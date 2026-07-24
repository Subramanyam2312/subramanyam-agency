<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class ClientLogo extends Model
{
    protected static string $table = 'client_logos';

    /**
     * Logos joined to their image. media_id is NOT NULL with a RESTRICT foreign key,
     * so every row is guaranteed to have one — no null handling needed downstream.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function withMedia(bool $activeOnly = false): array
    {
        $clause = $activeOnly ? 'WHERE cl.is_active = 1 AND m.deleted_at IS NULL' : '';

        return Database::select(
            "SELECT cl.*, m.path AS media_path, m.alt_text AS media_alt, m.variants AS media_variants
             FROM `client_logos` cl
             INNER JOIN `media` m ON m.id = cl.media_id
             {$clause}
             ORDER BY cl.sort_order, cl.name"
        );
    }
}
