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
    [
        'label' => 'Content',
        'items' => [
            ['label' => 'Posts',        'href' => '/admin/posts',        'icon' => 'file'],
            ['label' => 'Categories',   'href' => '/admin/categories',   'icon' => 'folder'],
            ['label' => 'Services',     'href' => '/admin/services',     'icon' => 'layers'],
            ['label' => 'Case studies', 'href' => '/admin/case-studies', 'icon' => 'award'],
            ['label' => 'Testimonials', 'href' => '/admin/testimonials', 'icon' => 'quote'],
            ['label' => 'FAQs',         'href' => '/admin/faqs',         'icon' => 'help'],
            ['label' => 'Timeline',     'href' => '/admin/timeline',     'icon' => 'clock'],
            ['label' => 'Client logos', 'href' => '/admin/client-logos', 'icon' => 'star'],
            ['label' => 'Page copy',    'href' => '/admin/page-content', 'icon' => 'type'],
            ['label' => 'Media',        'href' => '/admin/media',        'icon' => 'image'],
        ],
    ],
    [
        'label' => 'Inbox',
        'items' => [
            ['label' => 'Enquiries',   'href' => '/admin/submissions', 'icon' => 'inbox', 'badge' => 'unread'],
            ['label' => 'Subscribers', 'href' => '/admin/subscribers', 'icon' => 'mail'],
        ],
    ],
    [
        'label' => 'Configuration',
        'items' => [
            ['label' => 'Plugins',    'href' => '/admin/plugins',    'icon' => 'plug',   'admin_only' => true],
            ['label' => 'Traffic',    'href' => '/admin/traffic',    'icon' => 'chart',  'admin_only' => true],
            ['label' => 'Appearance', 'href' => '/admin/appearance', 'icon' => 'type',   'admin_only' => true],
            ['label' => 'Settings',   'href' => '/admin/settings',   'icon' => 'cog',    'admin_only' => true],
            ['label' => 'Users',      'href' => '/admin/users',      'icon' => 'users',  'admin_only' => true],
            ['label' => 'API tokens', 'href' => '/admin/api-tokens', 'icon' => 'key',    'admin_only' => true],
            ['label' => 'Security',   'href' => '/admin/security',   'icon' => 'shield', 'admin_only' => true],
        ],
    ],
];

$icons = [
    'grid'   => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
    'file'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M9 13h6M9 17h6"/>',
    'folder' => '<path d="M4 4h5l2 3h9a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/>',
    'layers' => '<path d="M12 2 2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>',
    'award'  => '<circle cx="12" cy="8" r="6"/><path d="m8.2 13.9-1.2 7.1 5-3 5 3-1.2-7.1"/>',
    'quote'  => '<path d="M7 7h4v4a4 4 0 0 1-4 4M15 7h4v4a4 4 0 0 1-4 4"/>',
    'help'   => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
    'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'star'   => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2-5.5-2.9-5.5 2.9 1-6.2L3 9.6l6.2-.9z"/>',
    'type'   => '<path d="M4 6V4h16v2M9 20h6M12 4v16"/>',
    'image'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
    'inbox'  => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.4 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/>',
    'mail'   => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>',
    'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
    'users'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/>',
    'key'    => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 8.3-8.3M17 6l3 3M14 9l3 3"/>',
    'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'plug'   => '<path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0zM12 17v5"/>',
    'chart'  => '<path d="M3 3v18h18M8 15v4M13 11v8M18 7v12"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
];

// Unread enquiry count, shown as a badge so it is visible without opening the inbox.
$unreadCount = \App\Models\ContactSubmission::unreadCount();

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

                                <?php if (($item['badge'] ?? '') === 'unread' && $unreadCount > 0): ?>
                                    <span class="ml-auto rounded-full bg-accent px-1.5 py-0.5 text-xs font-medium text-ink">
                                        <?= e((string) $unreadCount) ?>
                                        <span class="sr-only">unread enquiries</span>
                                    </span>
                                <?php endif; ?>
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
