<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Page not found · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="h-full">
    <main class="flex min-h-full items-center justify-center px-4 py-16">
        <div class="w-full max-w-lg text-center">
            <p class="font-mono text-sm tracking-[0.3em] text-muted">404</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                That page isn't here
            </h1>
            <p class="mt-4 text-sm leading-relaxed text-muted">
                The link may be out of date, or the page may have moved.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="/" class="btn-primary">Go to the homepage</a>
                <a href="/contact" class="btn-ghost">Get in touch</a>
            </div>
        </div>
    </main>
</body>
</html>
