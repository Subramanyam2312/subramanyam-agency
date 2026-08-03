<?php

use App\Models\PageBlock;
use App\Models\Setting;

$this->extend('layouts/site');

/** Every string on this page comes from Content -> Page copy -> contact. */
$block = static fn (string $key, string $default = ''): string => PageBlock::value('contact', $key, $default);

/* Contact channels come from Settings, so they stay in one place site-wide. */
$email     = Setting::get('contact_email');
$phone     = Setting::get('contact_phone');
$whatsapp  = Setting::get('whatsapp_number');
$linkedin  = Setting::get('social_linkedin');
$instagram = Setting::get('social_instagram');
$address   = Setting::get('address');

$waLink = $whatsapp ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', (string) $whatsapp) : '';

/* Numbered blocks: an empty title removes that row rather than leaving a gap.
   Cards can be added in the CMS, so scan to the ceiling rather than a fixed count. */
$max   = (int) config('repeatables.max', 12);
$steps = [];
for ($i = 1; $i <= $max; $i++) {
    if (($title = $block("step_{$i}_title")) !== '') {
        $steps[] = ['title' => $title, 'body' => $block("step_{$i}_body")];
    }
}

$faqs = [];
for ($i = 1; $i <= $max; $i++) {
    if (($question = $block("faq_{$i}_q")) !== '') {
        $faqs[] = ['question' => $question, 'answer' => $block("faq_{$i}_a")];
    }
}
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => $block('hero_eyebrow', 'Contact'),
    'heading' => $block('hero_heading', 'Start a conversation'),
    'lede'    => $block('hero_lede'),
    'editPage' => 'contact',
]) ?>

<!-- ===================================================== FORM + DETAILS -->
<section id="say-hello" class="section rule">
    <div class="container-site grid gap-14 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <p class="section-index"<?= editable('contact','form_label') ?>><?= e($block('form_label')) ?></p>
            <h2 class="display-md reveal mt-3"<?= editable('contact','form_heading') ?>><?= e($block('form_heading')) ?></h2>
            <p class="prose-body reveal mt-4 max-w-xl text-sm"<?= editable('contact','form_intro') ?>><?= e($block('form_intro')) ?></p>

            <?= $this->include('partials/site-flash') ?>

            <!-- data-async: site.js intercepts the submit and swaps in the thank-you
                 state without a reload. Without JS it posts normally and redirects
                 back with a flash message — same outcome, one extra page load. -->
            <form method="post" action="/contact" novalidate data-async class="card-flat mt-8 p-7 sm:p-9">
                <?= csrf_field() ?>

                <div class="grid gap-5">
                    <div>
                        <label for="name" class="field-label">Your name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" id="name" name="name" required autocomplete="name"
                               value="<?= e(old('name')) ?>" class="field-input"
                               <?= error_for('name') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($m = error_for('name')): ?><p class="field-error"><?= e($m) ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="email" class="field-label">Your email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               value="<?= e(old('email')) ?>" class="field-input"
                               <?= error_for('email') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($m = error_for('email')): ?><p class="field-error"><?= e($m) ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="message" class="field-label">Message <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea id="message" name="message" rows="6" required class="field-input"
                                  placeholder="What you're trying to move, and what's in the way."
                                  <?= error_for('message') ? 'aria-invalid="true"' : '' ?>><?= e(old('message')) ?></textarea>
                        <?php if ($m = error_for('message')): ?>
                            <p class="field-error"><?= e($m) ?></p>
                        <?php else: ?>
                            <p class="field-hint">A couple of sentences is plenty. Minimum 20 characters.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Honeypot. Hidden from people, offered to bots. -->
                <div class="absolute left-[-9999px]" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="mt-7 flex flex-wrap items-center gap-4">
                    <button type="submit" class="btn-bone"><?= e($block('form_button', 'Send message')) ?></button>
                    <p class="text-xs text-muted"><?= e($block('response_note')) ?></p>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-4 lg:col-start-9">
            <p class="section-index"<?= editable('contact','details_label') ?>><?= e($block('details_label')) ?></p>
            <p class="prose-body reveal mt-3 text-sm"<?= editable('contact','details_intro') ?>><?= e($block('details_intro')) ?></p>

            <div class="mt-7 space-y-7">
                <?php if ($email): ?>
                    <div>
                        <p class="eyebrow">Email</p>
                        <p class="mt-2"><a href="mailto:<?= e($email) ?>" class="link-underline text-body"><?= e($email) ?></a></p>
                    </div>
                <?php endif; ?>

                <?php if ($phone || $waLink): ?>
                    <div>
                        <p class="eyebrow">Call / WhatsApp</p>
                        <p class="mt-2 text-body">
                            <?php if ($phone): ?>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $phone)) ?>" class="link-underline"><?= e($phone) ?></a>
                            <?php endif; ?>
                            <?php if ($waLink): ?>
                                <span class="mx-1.5 text-muted">·</span>
                                <a href="<?= e($waLink) ?>" rel="noopener" target="_blank" class="link-underline">WhatsApp</a>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($linkedin): ?>
                    <div>
                        <p class="eyebrow">LinkedIn</p>
                        <p class="mt-2"><a href="<?= e($linkedin) ?>" rel="me noopener" target="_blank" class="link-underline text-body">Subramanyam M N</a></p>
                    </div>
                <?php endif; ?>

                <?php if ($instagram): ?>
                    <div>
                        <p class="eyebrow">Instagram</p>
                        <p class="mt-2"><a href="<?= e($instagram) ?>" rel="me noopener" target="_blank" class="link-underline text-body">Message on Instagram</a></p>
                    </div>
                <?php endif; ?>

                <?php if ($address): ?>
                    <div>
                        <p class="eyebrow">Location</p>
                        <p class="mt-2 text-sm text-muted"><?= e($address) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>

<!-- ==================================================== WHAT HAPPENS NEXT -->
<?php if ($steps !== []): ?>
    <section class="section rule" aria-labelledby="next-heading">
        <div class="container-site">
            <p class="section-index"<?= editable('contact','next_label') ?>><?= e($block('next_label')) ?></p>
            <h2 id="next-heading" class="display-lg reveal mt-3" data-split<?= editable('contact','next_heading') ?>><?= e($block('next_heading')) ?></h2>
            <p class="lede mt-6"<?= editable('contact','next_lede') ?>><?= e($block('next_lede')) ?></p>

            <ol class="mt-12 divide-y divide-line/60 border-y border-line/60">
                <?php foreach ($steps as $i => $step): ?>
                    <li class="reveal grid gap-4 py-8 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <p class="font-mono text-sm text-accent"><?= sprintf('%02d', $i + 1) ?></p>
                            <h3 class="display-md mt-2"<?= editable('contact', 'step_' . ($i + 1) . '_title') ?>><?= e($step['title']) ?></h3>
                        </div>
                        <p class="prose-body text-sm sm:col-span-7 sm:col-start-6"<?= editable('contact', 'step_' . ($i + 1) . '_body') ?>><?= e($step['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================== FAQ -->
<?php if ($faqs !== []): ?>
    <section class="section rule" aria-labelledby="contact-faq-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <p class="section-index"<?= editable('contact','faq_label') ?>><?= e($block('faq_label')) ?></p>
                <h2 id="contact-faq-heading" class="display-lg reveal mt-3" data-split<?= editable('contact','faq_heading') ?>><?= e($block('faq_heading')) ?></h2>
            </div>
            <div class="lg:col-span-7 lg:col-start-6">
                <?= $this->include('partials/accordion', ['items' => $faqs, 'level' => 3]) ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================== CTA -->
<?php if ($block('cta_heading') !== ''): ?>
    <section class="section rule">
        <div class="container-site text-center">
            <p class="eyebrow"<?= editable('contact','cta_eyebrow') ?>><?= e($block('cta_eyebrow')) ?></p>
            <h2 class="display-lg gilt reveal mx-auto mt-4 max-w-[24ch]"<?= editable('contact','cta_heading') ?>><?= e($block('cta_heading')) ?></h2>
            <p class="lede mx-auto mt-6"<?= editable('contact','cta_lede') ?>><?= e($block('cta_lede')) ?></p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <?php if ($label = $block('cta_primary')): ?>
                    <a href="#say-hello" class="btn-bone"><?= e($label) ?></a>
                <?php endif; ?>
                <?php if ($waLink): ?>
                    <a href="<?= e($waLink) ?>" rel="noopener" target="_blank" class="btn-outline">Message on WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php $this->stop(); ?>
