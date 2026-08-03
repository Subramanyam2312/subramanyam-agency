<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Appearance<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<form method="post" action="/admin/appearance" novalidate>
    <?= csrf_field() ?>
    <?= method_field('PATCH') ?>

    <p class="mb-6 max-w-2xl text-sm text-muted">
        The typeface pairing used across the public site. Each option previews below in
        the real fonts, so what you see here is what visitors get.
    </p>

    <!-- Self-hosted pairings ------------------------------------------------ -->
    <section class="card p-5 sm:p-6">
        <h2 class="text-sm font-semibold">Curated pairings <span class="text-muted">· self-hosted</span></h2>
        <p class="mt-1 max-w-2xl text-xs text-muted">
            Bundled with the site, so there is no third-party request, no delay before
            text appears, and the strict content-security policy stays intact.
        </p>

        <div class="mt-5 grid gap-3">
            <?php foreach ($pairings as $key => $pairing): ?>
                <label class="flex cursor-pointer items-start gap-4 rounded-card border p-5 transition-colors
                              <?= $current === $key && $source !== 'google' ? 'border-accent/70 bg-raised' : 'border-line/70 hover:bg-raised/50' ?>">
                    <input type="radio" name="font_pairing" value="<?= e($key) ?>"
                           class="mt-1 shrink-0 border-field bg-raised text-accent focus:ring-accent"
                           <?= $current === $key ? 'checked' : '' ?>>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-body"><?= e($pairing['label']) ?></span>
                        <span class="mt-0.5 block text-xs text-muted"><?= e($pairing['note']) ?></span>

                        <!-- Preview, set in the pairing's own faces. -->
                        <span class="mt-4 block border-t border-line/60 pt-4">
                            <span class="block text-[26px] leading-tight text-body"
                                  style="font-family: <?= e($pairing['display']) ?>">
                                Marketing that earns its line
                            </span>
                            <span class="mt-2 block text-sm text-muted"
                                  style="font-family: <?= e($pairing['body']) ?>">
                                Strategy, creative and SEO for brands across South India.
                            </span>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Google Fonts -------------------------------------------------------- -->
    <section class="card mt-6 p-5 sm:p-6">
        <h2 class="text-sm font-semibold">Any Google font <span class="text-muted">· opt-in</span></h2>
        <p class="mt-1 max-w-2xl text-xs text-muted">
            Opens the whole Google library by name. Worth knowing before you switch:
            every page then makes a request to Google, text can flash before the font
            arrives, and the security policy widens to allow Google's servers. The
            curated pairings above have none of those costs.
        </p>

        <div class="mt-5 space-y-5 border-t border-line/70 pt-5">
            <label class="flex items-start gap-3">
                <input type="hidden" name="fonts_source" value="self">
                <input type="checkbox" name="fonts_source" value="google"
                       class="mt-0.5 rounded border-field bg-raised text-accent focus:ring-accent"
                       <?= $source === 'google' ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-medium text-body">Use Google Fonts instead</span>
                    <span class="block text-xs text-muted">Overrides the pairing chosen above while it is on.</span>
                </span>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="gdisplay" class="field-label">Headline font</label>
                    <input type="text" id="gdisplay" name="font_google_display" value="<?= e($google['display']) ?>"
                           class="field-input" placeholder="Playfair Display">
                    <p class="field-hint">Exact family name as Google spells it.</p>
                </div>
                <div>
                    <label for="gbody" class="field-label">Body font</label>
                    <input type="text" id="gbody" name="font_google_body" value="<?= e($google['body']) ?>"
                           class="field-input" placeholder="Inter">
                </div>
            </div>

            <?php if ($source === 'google' && !$usesGoogle): ?>
                <p class="alert-warning">
                    Google Fonts is switched on but one of the family names is missing or
                    not a valid font name, so the site is still using the pairing above.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <div class="mt-6">
        <button type="submit" class="btn-primary">Save typography</button>
    </div>
</form>
<?php $this->stop(); ?>
