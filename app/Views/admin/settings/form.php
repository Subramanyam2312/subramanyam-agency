<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Settings<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<div class="mx-auto max-w-3xl">
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Settings sections">
        <?php foreach ($groups as $key => $label): ?>
            <a href="/admin/settings/<?= e($key) ?>"
               class="btn-ghost <?= $group === $key ? 'bg-raised text-body' : '' ?>"
               <?= $group === $key ? 'aria-current="page"' : '' ?>>
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="/admin/settings/<?= e($group) ?>" novalidate>
        <?= csrf_field() ?>
        <?= method_field('PATCH') ?>

        <div class="card p-5 sm:p-6">
            <?php if ($settings === []): ?>
                <p class="py-8 text-center text-sm text-muted">Nothing configurable in this section yet.</p>
            <?php else: ?>
                <?php foreach ($settings as $setting): ?>
                    <?php
                    $key = (string) $setting['setting_key'];

                    // Turn the key into a readable label: seo_default_title -> Seo default title
                    $label = ucfirst(str_replace('_', ' ', $key));
                    ?>
                    <?= $this->include('partials/field', [
                        'name'  => 'settings[' . $key . ']',
                        'label' => $label,
                        'type'  => in_array($key, $multiline, true) ? 'textarea' : 'text',
                        'rows'  => 3,
                        'value' => (string) ($setting['setting_value'] ?? ''),
                    ]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">Save settings</button>
        </div>
    </form>

    <?php if ($group === 'seo'): ?>
        <p class="mt-6 text-xs text-muted">
            Analytics IDs entered here are rendered into the public pages in Phase 6.
        </p>
    <?php endif; ?>

    <p class="mt-4 text-xs text-muted">
        Database and SMTP credentials are not editable here — they live in <code>.env</code>,
        outside the web root, so a database compromise never hands over the mail account too.
    </p>
</div>
<?php $this->stop(); ?>
