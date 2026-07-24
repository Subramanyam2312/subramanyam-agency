<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?><?= e(ucfirst(str_replace('-', ' ', $pageKey))) ?> copy<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
/**
 * The form builds itself from whatever page_blocks rows exist, so adding a new
 * editable string to a template is an INSERT — this file never changes.
 */
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
            </div>
        <?php endforeach; ?>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary">Save copy</button>
            <a href="/admin/page-content" class="btn-ghost">Cancel</a>
        </div>
    </div>
</form>
<?php $this->stop(); ?>
