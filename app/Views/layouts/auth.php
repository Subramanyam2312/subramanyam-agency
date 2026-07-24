<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($this->yieldSection('title', 'Sign in')) ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="h-full">
    <main class="min-h-full flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <p class="text-sm uppercase tracking-[0.2em] text-muted"><?= e(config('app.name')) ?></p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                    <?= e($this->yieldSection('heading', 'Sign in')) ?>
                </h1>
                <?php if ($this->hasSection('subheading')): ?>
                    <p class="mt-2 text-sm text-muted"><?= $this->yieldSection('subheading') ?></p>
                <?php endif; ?>
            </div>

            <div class="card p-6 sm:p-8">
                <?= $this->include('partials/flash') ?>
                <?= $this->yieldSection('content') ?>
            </div>

            <p class="mt-6 text-center text-xs text-muted">
                Authorised access only. Attempts are logged.
            </p>
        </div>
    </main>
</body>
</html>
