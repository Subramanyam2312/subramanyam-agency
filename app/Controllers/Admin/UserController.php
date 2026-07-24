<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Models\User;

/**
 * User management. The whole module sits behind RequireAdmin — editors cannot see
 * or reach it.
 */
final class UserController extends ResourceController
{
    private const MIN_PASSWORD_LENGTH = 12;

    protected string $model = User::class;

    protected string $route = '/admin/users';

    protected string $views = 'admin/users';

    protected string $singular = 'User';

    protected string $plural = 'Users';

    protected string $order = 'name ASC';

    protected array $searchable = ['name', 'email'];

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'type' => 'primary', 'sub' => 'email'],
            [
                'key'    => 'role',
                'label'  => 'Role',
                'type'   => 'badge',
                'labels' => ['admin' => 'Administrator', 'editor' => 'Editor'],
                'tones'  => ['admin' => 'warning', 'editor' => 'muted'],
            ],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'bool'],
            ['key' => 'last_login_at', 'label' => 'Last signed in', 'type' => 'date'],
        ];
    }

    protected function filters(): array
    {
        return ['role' => ['label' => 'Role', 'options' => ['admin' => 'Administrator', 'editor' => 'Editor']]];
    }

    protected function rules(?int $id): array
    {
        return [
            'name'  => 'required|max:120',
            'email' => 'required|email|max:191|unique:users,email' . ($id !== null ? ',' . $id : ''),
            'role'  => 'required|in:admin,editor',
            // Required when creating; on edit, blank means "leave the password alone".
            'password' => ($id === null ? 'required|' : 'nullable|') . 'min:' . self::MIN_PASSWORD_LENGTH . '|confirmed',
        ];
    }

    protected function fields(?array $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'value' => $record['name'] ?? '', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $record['email'] ?? '',
             'required' => true],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true,
             'options' => ['admin' => 'Administrator — everything', 'editor' => 'Editor — content only'],
             'value' => $record['role'] ?? 'editor',
             'hint' => 'Editors cannot reach settings, users or API tokens.'],
            ['name' => 'is_active', 'label' => 'Account is active', 'type' => 'checkbox',
             'value' => (string) ($record['is_active'] ?? 1),
             'hint' => 'Deactivating signs them out immediately and blocks sign-in.'],

            ['type' => 'section', 'label' => $record ? 'Change password' : 'Password'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password',
             'required' => $record === null,
             'hint' => $record
                 ? 'Leave blank to keep the current password. Minimum ' . self::MIN_PASSWORD_LENGTH . ' characters.'
                 : 'Minimum ' . self::MIN_PASSWORD_LENGTH . ' characters. A passphrase beats a short complex password.'],
            ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password',
             'required' => $record === null],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        $data = [
            'name'      => trim((string) $request->input('name')),
            'email'     => strtolower(trim((string) $request->input('email'))),
            'role'      => (string) $request->input('role', 'editor'),
            'is_active' => (int) $request->input('is_active', 0),
        ];

        $password = (string) $request->input('password', '');

        if ($password !== '') {
            $data['password_hash'] = Auth::hash($password);
        }

        // Never let the last administrator demote or deactivate themselves out of
        // the only account that can reach settings.
        if ($id !== null && User::isLastAdmin($id)) {
            $data['role']      = 'admin';
            $data['is_active'] = 1;
        }

        return $data;
    }

    protected function afterSave(int $id, Request $request, bool $isNew): void
    {
        // A password change invalidates every remembered device for that user.
        if (!$isNew && (string) $request->input('password', '') !== '') {
            Database::delete('remember_tokens', ['user_id' => $id]);
        }
    }

    protected function beforeDelete(array $record): ?string
    {
        if ((int) $record['id'] === Auth::id()) {
            return 'You cannot delete the account you are signed in with.';
        }

        if ($record['role'] === 'admin' && User::isLastAdmin((int) $record['id'])) {
            return 'That is the only administrator account. Promote someone else first.';
        }

        return null;
    }
}
