<?php $this->extend('layouts/site'); ?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'Legal',
    'heading' => $heading,
]) ?>

<section class="section rule">
    <div class="container-site max-w-3xl">
        <?php if ($updated !== ''): ?>
            <p class="eyebrow mb-8">Last updated <?= e($updated) ?></p>
        <?php endif; ?>

        <!-- Rich text, sanitised on save. -->
        <div class="prose-editorial"><?= $body ?></div>
    </div>
</section>

<?php $this->stop(); ?>
