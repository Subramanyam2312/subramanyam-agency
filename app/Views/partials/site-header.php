<?php

use App\Models\Setting;

$siteName = Setting::get('site_name', config('app.name'));

$navigation = [
    ['label' => 'Services', 'href' => '/services'],
    ['label' => 'Work',     'href' => '/work'],
    ['label' => 'About',    'href' => '/about'],
    ['label' => 'Journal',  'href' => '/blog'],
];

$currentPath = $currentPath ?? '/';
?>
<header id="site-header"
        class="fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,backdrop-filter] duration-300
               border-b border-transparent">
    <div class="container-site flex h-[4.5rem] items-center justify-between gap-6">
        <a href="/" class="group relative z-10 flex items-baseline gap-2">
            <span class="display text-xl tracking-tight"><?= e($siteName) ?></span>
        </a>

        <nav class="hidden items-center gap-8 md:flex" aria-label="Main">
            <?php foreach ($navigation as $item): ?>
                <a href="<?= e($item['href']) ?>"
                   class="text-sm text-muted transition-colors hover:text-body <?= str_starts_with($currentPath, $item['href']) ? 'text-body' : '' ?>"
                   <?= str_starts_with($currentPath, $item['href']) ? 'aria-current="page"' : '' ?>>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-3">
            <a href="/contact" class="btn-bone hidden h-10 px-5 text-sm md:inline-flex">Start a project</a>

            <button type="button" id="site-nav-toggle"
                    class="relative z-10 -mr-2 inline-flex h-10 w-10 items-center justify-center rounded-full
                           border border-line text-body md:hidden"
                    aria-controls="site-nav-drawer" aria-expanded="false" aria-label="Open menu">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 8h16M4 16h16"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<!-- Mobile drawer. Kept in the DOM and toggled with [hidden] so it needs no
     framework and stays keyboard reachable when open. -->
<div id="site-nav-drawer" hidden class="fixed inset-0 z-40 md:hidden">
    <div class="absolute inset-0 bg-ink/95 backdrop-blur-xl" data-close-nav></div>

    <div class="relative flex h-full flex-col justify-between px-5 pb-10 pt-24"
         role="dialog" aria-modal="true" aria-label="Menu">
        <nav class="flex flex-col gap-2" aria-label="Mobile">
            <?php foreach ($navigation as $item): ?>
                <a href="<?= e($item['href']) ?>" class="display-md py-2 text-body">
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
            <a href="/contact" class="display-md py-2 text-body">Contact</a>
        </nav>

        <div>
            <a href="/contact" class="btn-bone w-full">Start a project</a>
            <?php if ($email = Setting::get('contact_email')): ?>
                <p class="mt-5 text-sm text-muted">
                    <a href="mailto:<?= e($email) ?>" class="link-underline"><?= e($email) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
