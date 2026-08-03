<?php

use App\Models\Service;
use App\Models\Setting;

$siteName = Setting::get('site_name', config('app.name'));
$services = Service::all(['is_active' => 1], 'sort_order ASC', 6);

$socials = array_filter([
    'Instagram' => Setting::get('social_instagram'),
    'LinkedIn'  => Setting::get('social_linkedin'),
    'Facebook'  => Setting::get('social_facebook'),
    'X'         => Setting::get('social_x'),
    'YouTube'   => Setting::get('social_youtube'),
]);
?>
<footer class="rule mt-px bg-surface/40">
    <div class="container-site py-16 sm:py-20">
        <div class="grid gap-12 lg:grid-cols-12">
            <!-- Newsletter -->
            <div class="lg:col-span-5">
                <p class="display-md"><?= e($siteName) ?></p>
                <p class="prose-body mt-4 max-w-sm text-sm">
                    <?= e(Setting::get('footer_copy', '')) ?>
                </p>

                <form method="post" action="/newsletter" class="mt-8 max-w-sm" data-async>
                    <?= csrf_field() ?>
                    <label for="newsletter-email" class="eyebrow">Occasional notes, no spam</label>
                    <div class="mt-3 flex gap-2">
                        <input type="email" id="newsletter-email" name="email" required
                               placeholder="you@company.com"
                               class="field-input rounded-full bg-raised/60 px-5">
                        <button type="submit" class="btn-bone shrink-0 px-5">Join</button>
                    </div>
                    <!-- Honeypot: a real person never fills a hidden field. -->
                    <div class="absolute left-[-9999px]" aria-hidden="true">
                        <label for="newsletter-website">Leave this empty</label>
                        <input type="text" id="newsletter-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                </form>
            </div>

            <div class="grid gap-10 sm:grid-cols-3 lg:col-span-7">
                <div>
                    <p class="eyebrow">Services</p>
                    <ul class="mt-4 space-y-2.5">
                        <?php foreach ($services as $service): ?>
                            <li>
                                <a href="/services/<?= e($service['slug']) ?>"
                                   class="text-sm text-muted transition-colors hover:text-body">
                                    <?= e($service['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <p class="eyebrow">Company</p>
                    <ul class="mt-4 space-y-2.5">
                        <?php foreach ([
                            'About'    => '/about',
                            'Blog'     => '/blog',
                            'Services' => '/services',
                            'Work'     => '/work',
                            'FAQ'      => '/faq',
                            'Contact'  => '/contact',
                        ] as $label => $href): ?>
                            <li>
                                <a href="<?= e($href) ?>" class="text-sm text-muted transition-colors hover:text-body">
                                    <?= e($label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <p class="eyebrow">Elsewhere</p>
                    <ul class="mt-4 space-y-2.5">
                        <?php if ($socials === []): ?>
                            <li class="text-sm text-muted/60">Add social links in Settings</li>
                        <?php else: ?>
                            <?php foreach ($socials as $label => $href): ?>
                                <li>
                                    <a href="<?= e($href) ?>" rel="me noopener" target="_blank"
                                       class="text-sm text-muted transition-colors hover:text-body">
                                        <?= e($label) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <?php if ($email = Setting::get('contact_email')): ?>
                        <p class="mt-6 text-sm">
                            <a href="mailto:<?= e($email) ?>" class="link-underline text-muted hover:text-body">
                                <?= e($email) ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if ($phone = Setting::get('contact_phone')): ?>
                        <p class="mt-2 text-sm text-muted">
                            <a href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string) $phone)) ?>"
                               class="link-underline hover:text-body"><?= e($phone) ?></a>
                            <?php if ($wa = Setting::get('whatsapp_number')): ?>
                                <span class="mx-1.5 text-muted">·</span>
                                <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', (string) $wa)) ?>"
                                   rel="noopener" target="_blank" class="link-underline hover:text-body">WhatsApp</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="rule mt-14 flex flex-wrap items-center justify-between gap-4 pt-8">
            <p class="text-xs text-muted">
                © <?= e(date('Y')) ?> <?= e($siteName) ?>.
                <?php if ($address = Setting::get('address')): ?>
                    <span class="ml-1"><?= e($address) ?></span>
                <?php endif; ?>
            </p>

            <ul class="flex gap-6">
                <li><a href="/privacy" class="text-xs text-muted transition-colors hover:text-body">Privacy</a></li>
                <li><a href="/terms" class="text-xs text-muted transition-colors hover:text-body">Terms</a></li>
            </ul>
        </div>
    </div>
</footer>
