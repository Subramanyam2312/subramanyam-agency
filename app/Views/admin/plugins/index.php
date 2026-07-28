<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Plugins<?php $this->stop(); ?>

<?php
/** Small helpers to read a setting's value / checked state. */
$val = fn (string $k, string $d = ''): string => (string) ($s[$k] ?? $d);
$on  = fn (string $k, bool $d = false): bool => in_array(strtolower((string) ($s[$k] ?? ($d ? '1' : '0'))), ['1','true','yes','on'], true);

$toggle = function (string $name, string $label, bool $checked, string $hint = '') {
    ob_start(); ?>
    <label class="flex items-start gap-3">
        <input type="hidden" name="<?= e($name) ?>" value="0">
        <input type="checkbox" name="<?= e($name) ?>" value="1"
               class="mt-0.5 rounded border-field bg-raised text-accent focus:ring-accent"
               <?= $checked ? 'checked' : '' ?>>
        <span>
            <span class="block text-sm font-medium text-body"><?= e($label) ?></span>
            <?php if ($hint !== ''): ?><span class="block text-xs text-muted"><?= e($hint) ?></span><?php endif; ?>
        </span>
    </label>
    <?php return ob_get_clean();
};
?>

<?php $this->start('content'); ?>
<form method="post" action="/admin/plugins" novalidate>
    <?= csrf_field() ?>
    <?= method_field('PATCH') ?>

    <p class="mb-6 max-w-2xl text-sm text-muted">
        Optional capabilities for the site. Because this runs on a real server, these
        are working features — not just tags injected into a static page.
    </p>

    <div class="space-y-6">

        <!-- SEO ------------------------------------------------------------- -->
        <section class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold">SEO analysis <span class="text-muted">· RankMath-style</span></h2>
                    <p class="mt-1 max-w-lg text-xs text-muted">
                        Scores every post 0–100 against a focus keyword with a fix-it checklist —
                        the same on-page checks RankMath runs, built in. Set a keyword on any post to use it.
                    </p>
                </div>
                <a href="/admin/posts" class="btn-ghost shrink-0">Open a post</a>
            </div>
            <div class="mt-4 border-t border-line/70 pt-4">
                <?= $toggle('plugin_seo_enabled', 'Enabled', $on('plugin_seo_enabled', true)) ?>
            </div>
        </section>

        <!-- Analytics ------------------------------------------------------- -->
        <section class="card p-5 sm:p-6">
            <h2 class="text-sm font-semibold">Analytics &amp; tags</h2>
            <p class="mt-1 text-xs text-muted">GA4, Google Tag Manager, Meta Pixel and custom code. Never loaded for signed-in staff, and only when an ID is valid.</p>

            <div class="mt-4 space-y-5 border-t border-line/70 pt-4">
                <?= $toggle('plugin_analytics_enabled', 'Analytics enabled (master switch)', $on('plugin_analytics_enabled', true)) ?>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="ga4" class="field-label">Google Analytics 4 — Measurement ID</label>
                        <input type="text" id="ga4" name="ga_measurement_id" value="<?= e($val('ga_measurement_id')) ?>"
                               class="field-input" placeholder="G-XXXXXXXXXX">
                    </div>
                    <div>
                        <label for="gtm" class="field-label">Google Tag Manager — Container ID</label>
                        <input type="text" id="gtm" name="gtm_id" value="<?= e($val('gtm_id')) ?>"
                               class="field-input" placeholder="GTM-XXXXXXX">
                        <p class="field-hint">If both are set, GTM takes precedence.</p>
                    </div>
                </div>

                <div class="border-t border-line/60 pt-4">
                    <?= $toggle('meta_pixel_enabled', 'Meta (Facebook) Pixel', $on('meta_pixel_enabled'), 'Widens the content-security policy to Meta only while enabled.') ?>
                    <div class="mt-3">
                        <label for="pixel" class="field-label">Pixel ID</label>
                        <input type="text" id="pixel" name="meta_pixel_id" value="<?= e($val('meta_pixel_id')) ?>"
                               class="field-input max-w-sm" placeholder="000000000000000">
                    </div>
                </div>

                <div class="border-t border-line/60 pt-4">
                    <?= $toggle('custom_head_enabled', 'Custom <head> code', $on('custom_head_enabled'), 'Injected into every public page head. Admin-only; entered verbatim.') ?>
                    <textarea name="custom_head_code" rows="3" class="field-input mt-3 font-mono text-xs"
                              placeholder="<!-- verification tags, fonts, etc. -->"><?= e($val('custom_head_code')) ?></textarea>

                    <div class="mt-4">
                        <?= $toggle('custom_body_enabled', 'Custom end-of-<body> code', $on('custom_body_enabled'), 'Injected before the closing body tag — chat widgets, etc.') ?>
                        <textarea name="custom_body_code" rows="3" class="field-input mt-3 font-mono text-xs"
                                  placeholder="<!-- widget embed -->"><?= e($val('custom_body_code')) ?></textarea>
                    </div>
                </div>
            </div>
        </section>

        <!-- Traffic --------------------------------------------------------- -->
        <section class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold">Traffic Manager</h2>
                    <p class="mt-1 max-w-lg text-xs text-muted">
                        A cookieless, in-house count of page views and unique visitors, stored in
                        your own database — works even when a visitor blocks Google. No raw IP is ever kept.
                    </p>
                </div>
                <a href="/admin/traffic" class="btn-ghost shrink-0">View traffic</a>
            </div>
            <div class="mt-4 border-t border-line/70 pt-4">
                <?= $toggle('plugin_traffic_enabled', 'Enabled', $on('plugin_traffic_enabled', true)) ?>
            </div>
        </section>

        <!-- Spam ------------------------------------------------------------ -->
        <section class="card p-5 sm:p-6">
            <h2 class="text-sm font-semibold">Spam protection <span class="text-muted">· Akismet</span></h2>
            <p class="mt-1 text-xs text-muted">
                Layered on top of the honeypot and rate limiting already guarding the forms.
                <?= (int) $spamBlocked > 0 ? e((string) $spamBlocked) . ' submissions flagged as spam so far.' : '' ?>
            </p>

            <div class="mt-4 space-y-4 border-t border-line/70 pt-4">
                <?= $toggle('plugin_spam_enabled', 'Enabled', $on('plugin_spam_enabled', true)) ?>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="akismet" class="field-label">Akismet API key <span class="text-muted">(optional)</span></label>
                        <input type="text" id="akismet" name="akismet_key" value="<?= e($val('akismet_key')) ?>"
                               class="field-input" placeholder="your akismet key">
                        <p class="field-hint">Local heuristics run without a key; Akismet adds a second opinion.</p>
                    </div>
                    <div>
                        <label for="maxlinks" class="field-label">Block messages with more than</label>
                        <div class="flex items-center gap-2">
                            <input type="number" id="maxlinks" name="spam_max_links" value="<?= e($val('spam_max_links', '4')) ?>"
                                   min="1" max="20" class="field-input w-24">
                            <span class="text-sm text-muted">links</span>
                        </div>
                    </div>
                </div>

                <?php if ($akismetSet): ?>
                    <div>
                        <button type="submit" formaction="/admin/plugins/verify-akismet" class="btn-ghost">Test Akismet key</button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Cache ----------------------------------------------------------- -->
        <section class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold">Page cache <span class="text-muted">· LiteSpeed</span></h2>
                    <p class="mt-1 max-w-lg text-xs text-muted">
                        Serves guests a stored copy of each page instead of rebuilding it. On Hostinger's
                        LiteSpeed this is served without starting PHP at all; elsewhere a built-in file
                        cache does the same. Cleared automatically whenever you publish.
                        <?= (int) $cacheFiles > 0 ? e((string) $cacheFiles) . ' pages currently cached.' : '' ?>
                    </p>
                </div>
                <button type="submit" formaction="/admin/plugins/purge-cache" class="btn-ghost shrink-0">Purge now</button>
            </div>
            <div class="mt-4 space-y-4 border-t border-line/70 pt-4">
                <?= $toggle('plugin_cache_enabled', 'Enabled', $on('plugin_cache_enabled'), 'Off by default. Turn on once the site content is settled.') ?>
                <div>
                    <label for="ttl" class="field-label">Cache lifetime (seconds)</label>
                    <input type="number" id="ttl" name="cache_ttl" value="<?= e($val('cache_ttl', '3600')) ?>"
                           min="60" max="86400" class="field-input w-40">
                    <p class="field-hint">How long a cached page is served before it is rebuilt. 3600 = one hour.</p>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="btn-primary">Save plugin settings</button>
    </div>
</form>
<?php $this->stop(); ?>
