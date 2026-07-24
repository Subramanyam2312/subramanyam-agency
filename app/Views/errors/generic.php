<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e((string) $status) ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="h-full">
    <main class="flex min-h-full items-center justify-center px-4 py-16">
        <div class="w-full max-w-md text-center">
            <p class="font-mono text-sm tracking-[0.3em] text-muted"><?= e((string) $status) ?></p>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight"><?= e($message) ?></h1>
            <p class="mt-3 text-sm text-muted">
                If this keeps happening, get in touch and we'll look into it.
            </p>
            <a href="/" class="btn-ghost mt-8">Back to safety</a>
        </div>
    </main>
</body>
</html>
