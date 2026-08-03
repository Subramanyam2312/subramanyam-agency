<?php

use App\Models\Setting;

$this->extend('layouts/site');

/* Contact channels, from Settings so they stay CMS-editable. */
$email     = Setting::get('contact_email');
$phone     = Setting::get('contact_phone');
$whatsapp  = Setting::get('whatsapp_number');
$linkedin  = Setting::get('social_linkedin');
$instagram = Setting::get('social_instagram');
$address   = Setting::get('address');

$waLink = $whatsapp ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', (string) $whatsapp) : '';

/* "What happens next" — the actual sequence. */
$steps = [
    ['t' => 'We talk, no obligation',
     'b' => "A short call or exchange to understand the business, not to sell you anything. I want to know who your real buyer is, what you've already tried, and where the funnel leaks."],
    ['t' => 'I give you an honest read',
     'b' => "I'll tell you what I think is actually going on — including the parts that aren't flattering — and whether strategy, creative, SEO or some mix of them is where your money should go first. If I'm not the right person for it, you'll hear that here."],
    ['t' => 'A plan you can see',
     'b' => "If we're a fit, I put together a clear plan: what we're doing, in what order, and what we're spending where. Nothing hidden behind jargon."],
    ['t' => 'Make it, test it, scale what works',
     'b' => "We build the creative and set things live, start small, and let the evidence decide what to scale. I'll flag early if something isn't working instead of defending it — correcting course fast is cheaper than dressing it up."],
];

/* Honest answers. */
$faqs = [
    ['question' => 'Is the first call just a sales pitch in disguise?',
     'answer'   => "No. It's there to figure out whether I can actually help your business. If I can't see a real result in it for you, I'll say so directly rather than take the work."],
    ['question' => 'Do you only take big brands?',
     'answer'   => 'No. I keep a small roster and work with both new brands on tight budgets and established ones. For smaller brands, the whole approach is testing before scaling so nothing gets wasted.'],
];
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'Contact',
    'heading' => "Let's have a straight conversation",
    'lede'    => 'Tell me where you\'re trying to get to. I work with a small number of brands at a time, so I read every enquiry myself and reply within one business day.',
]) ?>

<!-- ===================================================== FORM + DETAILS -->
<section id="say-hello" class="section rule">
    <div class="container-site grid gap-14 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <p class="section-index">Say hello</p>
            <h2 class="display-md reveal mt-3">No pitch dressed up as a call</h2>
            <p class="prose-body reveal mt-4 max-w-xl text-sm">
                Fill in the form and tell me what you're working on — what the brand is, where you feel stuck,
                and what "working" would look like for you. If I think I can help, I'll say how. If I don't,
                I'll tell you that too and point you somewhere better.
            </p>

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
                    <button type="submit" class="btn-bone">Send message</button>
                    <p class="text-xs text-muted">
                        <?= e(\App\Models\PageBlock::value('contact', 'response_note', 'I reply within one business day.')) ?>
                    </p>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-4 lg:col-start-9">
            <p class="section-index">Contact details</p>
            <p class="prose-body reveal mt-3 text-sm">Prefer a direct line? Reach me on any of these.</p>

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
<section class="section rule" aria-labelledby="next-heading">
    <div class="container-site">
        <p class="section-index">What happens next</p>
        <h2 id="next-heading" class="display-lg reveal mt-3" data-split>From first message to live work</h2>
        <p class="lede mt-6">I'd rather you know exactly how this goes than wonder. Here's the actual sequence.</p>

        <ol class="mt-12 divide-y divide-line/60 border-y border-line/60">
            <?php foreach ($steps as $i => $step): ?>
                <li class="reveal grid gap-4 py-8 sm:grid-cols-12">
                    <div class="sm:col-span-4">
                        <p class="font-mono text-sm text-accent"><?= sprintf('%02d', $i + 1) ?></p>
                        <h3 class="display-md mt-2"><?= e($step['t']) ?></h3>
                    </div>
                    <p class="prose-body text-sm sm:col-span-7 sm:col-start-6"><?= e($step['b']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- ============================================================== FAQ -->
<section class="section rule" aria-labelledby="contact-faq-heading">
    <div class="container-site grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <p class="section-index">Straight answers</p>
            <h2 id="contact-faq-heading" class="display-lg reveal mt-3" data-split>A couple of honest answers</h2>
        </div>
        <div class="lg:col-span-7 lg:col-start-6">
            <?= $this->include('partials/accordion', ['items' => $faqs, 'level' => 3]) ?>
        </div>
    </div>
</section>

<!-- ============================================================== CTA -->
<section class="section rule">
    <div class="container-site text-center">
        <p class="eyebrow">Let's work together</p>
        <h2 class="display-lg gilt reveal mx-auto mt-4 max-w-[24ch]">Strategy and creative from the same place</h2>
        <p class="lede mx-auto mt-6">
            Grounded in what actually works rather than what sounds good on a deck. Reach out and tell me where you're headed.
        </p>
        <div class="mt-9 flex flex-wrap justify-center gap-3">
            <a href="#say-hello" class="btn-bone">Send a message</a>
            <?php if ($waLink): ?>
                <a href="<?= e($waLink) ?>" rel="noopener" target="_blank" class="btn-outline">Message on WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php $this->stop(); ?>
