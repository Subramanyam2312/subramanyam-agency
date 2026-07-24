<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($this->yieldSection('title', 'Admin')) ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="h-full">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50
                           focus:rounded-lg focus:bg-raised focus:px-4 focus:py-2 focus:text-body">
        Skip to content
    </a>

    <div class="flex h-full">
        <!-- Desktop sidebar -->
        <aside class="hidden w-64 shrink-0 border-r border-line/70 bg-surface lg:block">
            <?= $this->include('partials/admin-nav') ?>
        </aside>

        <!-- Mobile drawer. Kept in the DOM and toggled with [hidden] so it needs no
             framework, and stays reachable by keyboard when open. -->
        <div id="mobile-nav" hidden class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/60" data-close-nav aria-hidden="true"></div>
            <aside class="absolute inset-y-0 left-0 w-64 border-r border-line/70 bg-surface animate-fade-up"
                   role="dialog" aria-modal="true" aria-label="Navigation">
                <?= $this->include('partials/admin-nav') ?>
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line/70
                           bg-ink/80 px-4 backdrop-blur-xl">
                <button type="button" id="nav-toggle"
                        class="btn-ghost -ml-1 h-9 w-9 p-0 lg:hidden"
                        aria-controls="mobile-nav" aria-expanded="false" aria-label="Open navigation">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <h1 class="truncate text-sm font-medium"><?= e($this->yieldSection('title', 'Admin')) ?></h1>

                <div class="ml-auto flex items-center gap-2">
                    <?= $this->yieldSection('actions') ?>
                </div>
            </header>

            <main id="main" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-6xl">
                    <?= $this->include('partials/flash') ?>
                    <?= $this->yieldSection('content') ?>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= e(asset('/assets/js/admin.js')) ?>" defer></script>
    <script src="<?= e(asset('/assets/js/admin-forms.js')) ?>" defer></script>
</body>
</html>
