<?php

use App\Models\Setting;

/**
 * Public site layout.
 *
 * SEO fields fall back sensibly: page value, then the site default from settings,
 * then the site name — so no page can ship with an empty title or description.
 */
$siteName = Setting::get('site_name', config('app.name'));

$metaTitle = ($meta['title'] ?? '') !== ''
    ? $meta['title'] . ' · ' . $siteName
    : Setting::get('seo_default_title', $siteName);

$metaDescription = ($meta['description'] ?? '') !== ''
    ? str_limit((string) $meta['description'], 300)
    : (string) Setting::get('seo_default_description', '');

$canonical = $meta['canonical'] ?? url($currentPath === '/' ? '' : $currentPath);
$ogImage   = $meta['og_image'] ?? null;
$noindex   = (bool) ($meta['noindex'] ?? false);
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php if ($noindex): ?>
        <meta name="robots" content="noindex, follow">
    <?php endif; ?>

    <meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($ogImage !== null): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="<?= $ogImage !== null ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">

    <!-- Fonts are same-origin, so preloading them removes a round trip from the
         critical path without opening the CSP up to a third party. -->
    <link rel="preload" href="/assets/fonts/Instrument-Serif-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Instrument-Sans-normal.woff2" as="font" type="font/woff2" crossorigin>

    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">

    <?= $this->yieldSection('head') ?>
</head>
<body class="site min-h-screen bg-ink text-body antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-5 focus:top-5 focus:z-[60]
              focus:rounded-full focus:bg-accent focus:px-5 focus:py-2.5 focus:text-ink">
        Skip to content
    </a>

    <?= $this->include('partials/site-header') ?>

    <main id="main">
        <?= $this->yieldSection('content') ?>
    </main>

    <?= $this->include('partials/site-footer') ?>

    <script src="<?= e(asset('/assets/js/site.js')) ?>" defer></script>

    <?= $this->yieldSection('scripts') ?>
</body>
</html>
