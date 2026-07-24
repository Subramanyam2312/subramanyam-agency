<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Something went wrong · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="h-full">
    <main class="flex min-h-full items-center justify-center px-4 py-16">
        <div class="w-full max-w-lg text-center">
            <p class="font-mono text-sm tracking-[0.3em] text-muted">500</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                Something broke on our end
            </h1>
            <p class="mt-4 text-sm leading-relaxed text-muted">
                This one is ours, not yours. The error has been logged and we'll take a look.
            </p>

            <?php if (config('app.debug')): ?>
                <pre class="mt-8 overflow-x-auto rounded-lg border border-line bg-surface p-4 text-left
                            font-mono text-xs text-danger"><?= e($message) ?></pre>
            <?php endif; ?>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="/" class="btn-primary">Back to the homepage</a>
            </div>
        </div>
    </main>
</body>
</html>
