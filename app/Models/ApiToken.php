<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Personal access tokens for the REST API.
 *
 * Only the SHA-256 of a token is ever stored. The plaintext is shown once, at
 * creation, and cannot be recovered afterwards — if it is lost the token is
 * revoked and a new one issued.
 */
final class ApiToken extends Model
{
    protected static string $table = 'api_tokens';

    protected static array $jsonColumns = ['abilities'];

    /** Deliberately coarse. Fine-grained scopes nobody understands get granted wholesale. */
    public const ABILITY_READ  = 'read';
    public const ABILITY_WRITE = 'write';

    /**
     * @return array<string,string>
     */
    public static function abilities(): array
    {
        return [
            self::ABILITY_READ  => 'Read — list posts, categories and tags',
            self::ABILITY_WRITE => 'Write — create and update posts, upload media',
        ];
    }

    /**
     * Issues a token and returns the plaintext alongside the stored row id.
     *
     * The `sub_` prefix makes the string recognisable in logs and to secret
     * scanners, so a leaked token is more likely to be spotted.
     *
     * @param array<int,string> $abilities
     * @return array{id:int, token:string}
     */
    public static function issue(int $userId, string $name, array $abilities, ?string $expiresAt = null): array
    {
        $secret = bin2hex(random_bytes(32));
        $token  = 'sub_' . $secret;

        $id = Database::insert('api_tokens', [
            'user_id'    => $userId,
            'name'       => $name,
            'prefix'     => substr($secret, 0, 8),
            'token_hash' => hash('sha256', $token),
            'abilities'  => json_encode(array_values($abilities)),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $id, 'token' => $token];
    }

    /**
     * Resolves a bearer token to its row plus the owning user, in one query.
     *
     * Revoked, expired and deactivated-owner tokens are filtered in SQL rather
     * than in PHP, so there is no path where a caller is authenticated first and
     * checked second.
     *
     * @return array<string,mixed>|null
     */
    public static function resolve(string $plaintext): ?array
    {
        return Database::selectOne(
            'SELECT t.*, u.id AS user_id, u.name AS user_name, u.email AS user_email, u.role AS user_role
             FROM `api_tokens` t
             INNER JOIN `users` u ON u.id = t.user_id
             WHERE t.token_hash = :hash
               AND t.revoked_at IS NULL
               AND (t.expires_at IS NULL OR t.expires_at > NOW())
               AND u.deleted_at IS NULL
               AND u.is_active = 1
             LIMIT 1',
            [':hash' => hash('sha256', $plaintext)]
        );
    }

    /**
     * Written on every authenticated request so an unused or compromised token is
     * visible in the admin list.
     */
    public static function touch(int $id): void
    {
        Database::update('api_tokens', ['last_used_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public static function revoke(int $id): bool
    {
        return Database::update('api_tokens', ['revoked_at' => date('Y-m-d H:i:s')], ['id' => $id]) > 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function withOwners(): array
    {
        return Database::select(
            'SELECT t.*, u.name AS owner_name
             FROM `api_tokens` t
             INNER JOIN `users` u ON u.id = t.user_id
             ORDER BY t.revoked_at IS NOT NULL, t.created_at DESC'
        );
    }

    /**
     * @param array<string,mixed> $token
     */
    public static function can(array $token, string $ability): bool
    {
        $abilities = is_array($token['abilities'] ?? null)
            ? $token['abilities']
            : json_column($token['abilities'] ?? null);

        return in_array($ability, $abilities, true);
    }
}
