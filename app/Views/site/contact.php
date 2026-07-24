<?php

use App\Models\Setting;

$this->extend('layouts/site');
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'Contact',
    'heading' => \App\Models\PageBlock::value('contact', 'heading', 'Start a conversation'),
    'lede'    => \App\Models\PageBlock::value('contact', 'intro'),
]) ?>

<section class="section rule">
    <div class="container-site grid gap-14 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <?= $this->include('partials/site-flash') ?>

            <!-- data-async: site.js intercepts the submit and swaps in the
                 thank-you state without a reload. Without JS this posts normally
                 and redirects back with a flash message — same outcome, one extra
                 page load. -->
            <form method="post" action="/contact" novalidate data-async
                  class="card-flat p-7 sm:p-9">
                <?= csrf_field() ?>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="field-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" id="name" name="name" required autocomplete="name"
                               value="<?= e(old('name')) ?>" class="field-input"
                               <?= error_for('name') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($message = error_for('name')): ?>
                            <p class="field-error"><?= e($message) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="email" class="field-label">Email <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               value="<?= e(old('email')) ?>" class="field-input"
                               <?= error_for('email') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($message = error_for('email')): ?>
                            <p class="field-error"><?= e($message) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="phone" class="field-label">Phone</label>
                        <input type="tel" id="phone" name="phone" autocomplete="tel"
                               value="<?= e(old('phone')) ?>" class="field-input">
                        <?php if ($message = error_for('phone')): ?>
                            <p class="field-error"><?= e($message) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="company" class="field-label">Company</label>
                        <input type="text" id="company" name="company" autocomplete="organization"
                               value="<?= e(old('company')) ?>" class="field-input">
                    </div>

                    <div>
                        <label for="service_id" class="field-label">Service of interest</label>
                        <select id="service_id" name="service_id" class="field-input">
                            <option value="">Not sure yet</option>
                            <?php foreach ($services as $id => $title): ?>
                                <option value="<?= e($id) ?>" <?= (string) old('service_id') === (string) $id ? 'selected' : '' ?>>
                                    <?= e($title) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="budget_range" class="field-label">Budget</label>
                        <select id="budget_range" name="budget_range" class="field-input">
                            <option value="">Prefer not to say</option>
                            <?php foreach ($budgets as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= (string) old('budget_range') === (string) $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="message" class="field-label">
                            What are you working on? <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <textarea id="message" name="message" rows="6" required class="field-input"
                                  placeholder="What you are trying to move, and what is in the way."
                                  <?= error_for('message') ? 'aria-invalid="true"' : '' ?>><?= e(old('message')) ?></textarea>
                        <?php if ($message = error_for('message')): ?>
                            <p class="field-error"><?= e($message) ?></p>
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
                        <?= e(\App\Models\PageBlock::value('contact', 'response_note')) ?>
                    </p>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-4 lg:col-start-9">
            <div class="space-y-8">
                <?php if ($email = Setting::get('contact_email')): ?>
                    <div>
                        <p class="eyebrow">Email</p>
                        <p class="mt-2">
                            <a href="mailto:<?= e($email) ?>" class="link-underline text-body"><?= e($email) ?></a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($phone = Setting::get('contact_phone')): ?>
                    <div>
                        <p class="eyebrow">Phone</p>
                        <p class="mt-2">
                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>" class="link-underline text-body">
                                <?= e($phone) ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($whatsapp = Setting::get('whatsapp_number')): ?>
                    <div>
                        <p class="eyebrow">WhatsApp</p>
                        <p class="mt-2">
                            <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $whatsapp)) ?>"
                               rel="noopener" target="_blank" class="link-underline text-body">
                                Message us
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($hours = Setting::get('business_hours')): ?>
                    <div>
                        <p class="eyebrow">Hours</p>
                        <p class="mt-2 text-sm text-muted"><?= e($hours) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($address = Setting::get('address')): ?>
                    <div>
                        <p class="eyebrow">Where we are</p>
                        <p class="mt-2 text-sm text-muted"><?= e($address) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>

<?php $this->stop(); ?>
