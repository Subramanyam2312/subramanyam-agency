<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';

    protected static bool $softDeletes = true;

    public const ROLE_ADMIN  = 'admin';
    public const ROLE_EDITOR = 'editor';

    /**
     * @return array<string,string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN  => 'Administrator — full access including settings, users and API tokens',
            self::ROLE_EDITOR => 'Editor — content only, no settings, users or API tokens',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        return self::first(['email' => strtolower(trim($email))]);
    }

    /**
     * Refuses to remove the last remaining active administrator, which would lock
     * everyone out of the settings screens with no way back in through the UI.
     */
    public static function isLastAdmin(int $userId): bool
    {
        $count = (int) Database::scalar(
            "SELECT COUNT(*) FROM `users`
             WHERE `role` = 'admin' AND `is_active` = 1 AND `deleted_at` IS NULL AND `id` != :id",
            [':id' => $userId]
        );

        return $count === 0;
    }
}
