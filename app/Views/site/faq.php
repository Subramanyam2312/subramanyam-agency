<?php $this->extend('layouts/site'); ?>

<?php $this->start('head'); ?>
<?php
/**
 * FAQPage structured data across every group on the page.
 *
 * This is why the FAQ answer field in the CMS is plain text rather than rich
 * text: Google rejects FAQPage entries containing markup, so allowing HTML there
 * would silently invalidate the whole block.
 */
$questions = [];

foreach ($groups as $items) {
    foreach ($items as $item) {
        $questions[] = [
            '@type'          => 'Question',
            'name'           => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ];
    }
}
?>
<?php if ($questions !== []): ?>
    <script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
    <?= json_encode([
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $questions,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
    </script>
<?php endif; ?>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'FAQ',
    'heading' => 'Questions people actually ask',
    'lede'    => 'How engagements start, how reporting works, and what things cost. If yours is not here, ask.',
]) ?>

<section class="section rule">
    <div class="container-site max-w-3xl">
        <?php if ($groups === []): ?>
            <p class="prose-body">No questions have been published yet.</p>
        <?php else: ?>
            <?php foreach ($groups as $group => $items): ?>
                <div class="reveal mb-14">
                    <?= $this->include('partials/accordion', ['items' => $items, 'group' => $group]) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?= $this->include('partials/cta-band') ?>

<?php $this->stop(); ?>
