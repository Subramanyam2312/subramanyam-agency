<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>
<?= $record ? 'Edit ' . strtolower($resource['singular']) : 'New ' . strtolower($resource['singular']) ?>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
/**
 * Generic create/edit form, rendered from the field specs a controller returns.
 * Modules needing a bespoke layout set $formView and supply their own template.
 */
?>
<form method="post"
      action="<?= e($resource['route']) ?><?= $record ? '/' . (int) $record['id'] : '' ?>"
      novalidate>
    <?= csrf_field() ?>
    <?php if ($record): ?><?= method_field('PATCH') ?><?php endif; ?>

    <div class="mx-auto max-w-3xl">
        <div class="card p-5 sm:p-6">
            <?php foreach ($fields as $field): ?>
                <?php if (($field['type'] ?? '') === 'section'): ?>
                    <h2 class="mb-4 mt-8 border-t border-line/70 pt-6 text-sm font-semibold first:mt-0 first:border-0 first:pt-0">
                        <?= e($field['label']) ?>
                    </h2>
                <?php else: ?>
                    <?= $this->include('partials/field', $field) ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">
                <?= $record ? 'Save changes' : 'Create ' . e(strtolower($resource['singular'])) ?>
            </button>
            <a href="<?= e($resource['route']) ?>" class="btn-ghost">Cancel</a>

            <?php if ($record): ?>
                <span class="ml-auto text-xs text-muted">
                    Last updated <?= e(format_date((string) ($record['updated_at'] ?? $record['created_at'] ?? ''), 'j M Y')) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php $this->stop(); ?>
