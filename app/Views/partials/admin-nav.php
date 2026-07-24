<?php

use App\Core\Auth;

/**
 * Admin sidebar navigation.
 *
 * Single source of truth for the menu: Phase 3 adds its content modules by
 * appending to $groups, and nothing else in the layout changes.
 *
 * 'admin_only' entries are hidden from editors — the routes are independently
 * protected by RequireAdmin, so this only removes a dead-end link, it is not the
 * access control itself.
 */
$groups = [
    [
        'label' => null,
        'items' => [
            ['label' => 'Dashboard', 'href' => '/admin', 'icon' => 'grid', 'exact' => true],
        ],
    ],
];

$icons = [
    'grid'   => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
];

$currentPath = $currentPath ?? '/admin';

$isActive = static function (array $item) use ($currentPath): bool {
    if ($item['exact'] ?? false) {
        return $currentPath === $item['href'];
    }

    return str_starts_with($currentPath, $item['href']);
};

$user = Auth::user();
?>
<nav class="flex h-full flex-col" aria-label="Admin">
    <div class="px-4 py-5">
        <a href="/admin" class="block">
            <span class="text-sm font-semibold tracking-tight"><?= e(config('app.name')) ?></span>
            <span class="mt-0.5 block text-xs text-muted">Content portal</span>
        </a>
    </div>

    <div class="flex-1 space-y-6 overflow-y-auto px-3 pb-4">
        <?php foreach ($groups as $group): ?>
            <div>
                <?php if ($group['label'] !== null): ?>
                    <p class="px-3 pb-2 text-xs font-medium uppercase tracking-wider text-muted/70">
                        <?= e($group['label']) ?>
                    </p>
                <?php endif; ?>

                <ul class="space-y-0.5">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php if (($item['admin_only'] ?? false) && !Auth::isAdmin()) { continue; } ?>
                        <li>
                            <a href="<?= e($item['href']) ?>"
                               class="nav-link <?= $isActive($item) ? 'nav-link-active' : '' ?>"
                               <?= $isActive($item) ? 'aria-current="page"' : '' ?>>
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <?= $icons[$item['icon']] ?? '' ?>
                                </svg>
                                <span><?= e($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="border-t border-line/70 p-3">
        <?php if ($user !== null): ?>
            <div class="px-3 py-2">
                <p class="truncate text-sm font-medium"><?= e($user['name']) ?></p>
                <p class="truncate text-xs text-muted">
                    <?= e($user['email']) ?> · <?= e(ucfirst((string) $user['role'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/logout">
            <?= csrf_field() ?>
            <button type="submit" class="nav-link w-full text-left">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <?= $icons['logout'] ?>
                </svg>
                <span>Sign out</span>
            </button>
        </form>
    </div>
</nav>
