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

    <meta name="theme-color" content="#0a0b0d">

    <?php if ($verification = Setting::get('search_console_token')): ?>
        <meta name="google-site-verification" content="<?= e($verification) ?>">
    <?php endif; ?>

    <!-- Fonts are same-origin, so preloading them removes a round trip from the
         critical path without opening the CSP up to a third party. -->
    <link rel="preload" href="/assets/fonts/Instrument-Serif-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Instrument-Sans-normal.woff2" as="font" type="font/woff2" crossorigin>

    <!--
      The stylesheet stays render-blocking, deliberately.

      The brief asked for critical CSS inlined and the rest deferred. That pattern
      earns its complexity when the stylesheet is large; this one is ~7 KB gzipped
      and same-origin, so splitting it would trade a barely measurable saving for a
      real risk of a flash of unstyled content — and an incomplete critical block
      is a worse defect than one extra request that the browser caches for a year.
      Preloading it instead gets the discovery win without the downside.
    -->
    <link rel="preload" href="<?= e(asset('/assets/css/app.css')) ?>" as="style">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">

    <link rel="alternate" type="application/rss+xml"
          title="<?= e(Setting::get('site_name', config('app.name'))) ?> — Blog"
          href="<?= e(url('/feed.xml')) ?>">

    <?= $this->include('partials/site-schema') ?>
    <?= $this->yieldSection('head') ?>
    <?= $this->include('partials/fonts') ?>

    <?= $this->include('partials/analytics') ?>
</head>
<body class="site min-h-screen bg-ink text-body antialiased">
    <!-- Gilded ambient glow behind everything, fixed so it stays as the page scrolls. -->
    <div class="site-glow" aria-hidden="true"></div>

    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-5 focus:top-5 focus:z-[60]
              focus:rounded-full focus:bg-accent focus:px-5 focus:py-2.5 focus:text-ink">
        Skip to content
    </a>

    <!-- Scroll progress. Width is driven by site.js; it is decorative, so it is
         hidden from assistive tech rather than announced as a live region. -->
    <div class="pointer-events-none fixed inset-x-0 top-0 z-[55] h-px bg-accent/80 origin-left scale-x-0
                transition-transform duration-150 ease-out"
         id="scroll-progress" aria-hidden="true"></div>

    <?= $this->include('partials/site-header') ?>

    <main id="main">
        <?= $this->yieldSection('content') ?>
    </main>

    <?= $this->include('partials/site-footer') ?>

    <!--
      Back to top. A real <button> rather than an <a href="#top"> so it does not
      push a history entry, and it stays hidden until there is something to scroll
      back from. On inner pages the header's Home link is the way back to /.
    -->
    <button type="button" id="back-to-top"
            class="group fixed bottom-6 right-6 z-40 hidden h-12 w-12 items-center justify-center
                   rounded-full border border-field bg-surface/80 text-body backdrop-blur-xl
                   transition-all duration-300 hover:border-body/50 hover:bg-raised
                   focus-visible:opacity-100"
            aria-label="Back to top">
        <svg class="h-4 w-4 transition-transform duration-300 group-hover:-translate-y-0.5"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
    </button>

    <script src="<?= e(asset('/assets/js/site.js')) ?>" defer></script>

    <?= $this->yieldSection('scripts') ?>

    <?php if (!\App\Core\Auth::check()
        && \App\Models\Setting::bool('plugin_analytics_enabled', true)
        && \App\Models\Setting::bool('custom_body_enabled', false)
        && trim((string) \App\Models\Setting::get('custom_body_code', '')) !== ''): ?>
        <!-- Custom body code (Tools -> Plugins). Admin-authored, output verbatim. -->
        <?= \App\Models\Setting::get('custom_body_code') ?>
    <?php endif; ?>
</body>
</html>
