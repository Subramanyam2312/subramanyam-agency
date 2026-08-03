<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?><?= e(ucfirst(str_replace('-', ' ', $pageKey))) ?> copy<?php foreach ($repeatables as $groupId => $spec): ?>
    <form method="post" id="add-<?= e($groupId) ?>"
          action="/admin/page-content/<?= e($pageKey) ?>/items/<?= e($groupId) ?>"><?= csrf_field() ?></form>

    <form method="post" id="remove-<?= e($groupId) ?>"
          action="/admin/page-content/<?= e($pageKey) ?>/items/<?= e($groupId) ?>">
        <?= csrf_field() ?><?= method_field('DELETE') ?>
    </form>
<?php endforeach; ?>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
/**
 * The form builds itself from whatever page_blocks rows exist, so adding a new
 * editable string to a template is an INSERT — this file never changes.
 *
 * Groups listed in config/repeatables.php also get Add and Remove buttons. Those
 * submit to their own tiny forms rendered after this one and referenced by the
 * HTML `form` attribute, because a form cannot be nested inside another.
 */
$repeatables = $repeatables ?? [];

/** Repeatable groups keyed by the group heading they belong under. */
$repeatByGroup = [];
foreach ($repeatables as $groupId => $spec) {
    $repeatByGroup[$spec['group']][$groupId] = $spec;
}
?>
<form method="post" action="/admin/page-content/<?= e($pageKey) ?>" novalidate>
    <?= csrf_field() ?>
    <?= method_field('PATCH') ?>

    <div class="mx-auto max-w-3xl">
        <p class="mb-5">
            <a href="/admin/page-content" class="text-sm text-accent hover:underline">← All pages</a>
        </p>

        <?php foreach ($grouped as $groupName => $blocks): ?>
            <div class="card mb-5 p-5 sm:p-6">
                <h2 class="mb-5 text-sm font-semibold"><?= e($groupName) ?></h2>

                <?php foreach ($blocks as $block): ?>
                    <?php
                    $key   = (string) $block['block_key'];
                    $type  = (string) $block['type'];
                    $value = (string) ($block['value'] ?? '');

                    $fieldType = match ($type) {
                        'textarea' => 'textarea',
                        'html'     => 'richtext',
                        'number'   => 'number',
                        'url'      => 'url',
                        'image'    => 'media',
                        default    => 'text',
                    };
                    ?>
                    <?php if ($fieldType === 'media'): ?>
                        <?= $this->include('partials/field', [
                            'name'  => 'block_media[' . $key . ']',
                            'label' => $block['label'],
                            'type'  => 'media',
                            'value' => $block['media_id'] ?? '',
                            'media' => $media[(int) ($block['media_id'] ?? 0)] ?? null,
                        ]) ?>
                    <?php else: ?>
                        <?= $this->include('partials/field', [
                            'name'  => 'blocks[' . $key . ']',
                            'label' => $block['label'],
                            'type'  => $fieldType,
                            'rows'  => 3,
                            'value' => $value,
                        ]) ?>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php foreach ($repeatByGroup[$groupName] ?? [] as $groupId => $spec): ?>
                    <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-line/70 pt-5">
                        <button type="submit" form="add-<?= e($groupId) ?>" class="btn-ghost">
                            Add another <?= e($spec['noun']) ?>
                        </button>
                        <button type="submit" form="remove-<?= e($groupId) ?>" class="text-sm text-danger hover:underline"
                                data-confirm="Remove the last <?= e($spec['noun']) ?>? Anything typed into it is lost.">
                            Remove the last one
                        </button>
                        <span class="text-xs text-muted">
                            Cards are added empty — fill one in and save, or leave it blank and it will not appear on the site.
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary">Save copy</button>
            <a href="/admin/page-content" class="btn-ghost">Cancel</a>
        </div>
    </div>
</form>
<?php $this->stop(); ?>
