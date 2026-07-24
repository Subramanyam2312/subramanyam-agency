<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Page copy<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<p class="mb-5 max-w-2xl text-sm text-muted">
    Headlines, intros, stat numbers and calls to action for the main pages. These are
    the strings that would otherwise need a developer to change.
</p>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($pages as $pageKey => $count): ?>
        <a href="/admin/page-content/<?= e($pageKey) ?>"
           class="card p-5 transition-colors hover:border-accent/50">
            <p class="text-sm font-semibold capitalize"><?= e(str_replace('-', ' ', (string) $pageKey)) ?></p>
            <p class="mt-1 text-sm text-muted">
                <?= e((string) $count) ?> editable <?= $count === 1 ? 'field' : 'fields' ?>
            </p>
        </a>
    <?php endforeach; ?>
</div>
<?php $this->stop(); ?>
