<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Append-only audit trail for the dashboard and for answering "who changed this?".
 *
 * Every write is wrapped in a try/catch: an audit-log failure must never take down
 * the action being audited.
 */
final class ActivityLogger
{
    /**
     * @param array<string,mixed> $meta
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $meta = [],
        ?int $userId = null,
        ?int $apiTokenId = null
    ): void {
        try {
            Database::insert('activity_log', [
                'user_id'      => $userId ?? Auth::id(),
                'api_token_id' => $apiTokenId ?? Auth::apiTokenId(),
                'action'       => $action,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId !== null ? (int) $entityId : null,
                'meta'         => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip_hash'      => self::currentIpHash(),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('Activity log write failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 10): array
    {
        return Database::select(
            'SELECT a.*, u.name AS user_name
             FROM `activity_log` a
             LEFT JOIN `users` u ON u.id = a.user_id
             ORDER BY a.created_at DESC
             LIMIT ' . max(1, min(100, $limit))
        );
    }

    private static function currentIpHash(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($ip === null) {
            return null;
        }

        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }
}
