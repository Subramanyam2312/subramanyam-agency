<?php $this->extend('layouts/site'); ?>

<?php
$includes     = is_array($service['includes']) ? $service['includes'] : [];
$process      = is_array($service['process']) ? $service['process'] : [];
$deliverables = is_array($service['deliverables']) ? $service['deliverables'] : [];
?>

<?php $this->start('head'); ?>
<?php
/**
 * Service and FAQPage structured data.
 *
 * Emitted with the CSP nonce, and built with json_encode rather than string
 * concatenation so a quotation mark in CMS copy cannot break out of the script.
 */
$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Service',
    'name'        => $service['title'],
    'description' => $service['short_description'] ?: $service['hero_subheadline'],
    'url'         => url('/services/' . $service['slug']),
    'provider'    => [
        '@type' => 'Organization',
        'name'  => config('app.name'),
        'url'   => url('/'),
    ],
];
?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
<?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>

<?php if ($faqs !== []): ?>
    <?php
    $faqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(static fn (array $faq): array => [
            '@type'          => 'Question',
            'name'           => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ], $faqs),
    ];
    ?>
    <script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
    <?= json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
    </script>
<?php endif; ?>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'Service',
    'heading' => $service['hero_headline'] ?: $service['title'],
    'lede'    => $service['hero_subheadline'] ?: $service['short_description'],
    'glyph'   => (string) ($service['icon'] ?? ''),
]) ?>

<?php if ($service['problem_statement']): ?>
    <section class="section rule" aria-label="The problem">
        <div class="container-site">
            <p class="section-index">The problem</p>
            <p class="display-md reveal mt-6 max-w-3xl leading-tight">
                <?= e($service['problem_statement']) ?>
            </p>
        </div>
    </section>
<?php endif; ?>

<?php if ($includes !== [] || $deliverables !== []): ?>
    <section class="section rule" aria-labelledby="included-heading">
        <div class="container-site grid gap-14 lg:grid-cols-2">
            <?php if ($includes !== []): ?>
                <div>
                    <p class="section-index">Included</p>
                    <h2 id="included-heading" class="display-md reveal mt-3">What you get</h2>
                    <ul class="mt-8 divide-y divide-line/60 border-y border-line/60">
                        <?php foreach ($includes as $item): ?>
                            <li class="reveal flex gap-4 py-4 text-sm">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path d="m5 13 4 4L19 7"/>
                                </svg>
                                <span class="text-body"><?= e($item) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($deliverables !== []): ?>
                <div>
                    <p class="section-index">Deliverables</p>
                    <h2 class="display-md reveal mt-3">What lands</h2>
                    <ul class="mt-8 space-y-3">
                        <?php foreach ($deliverables as $item): ?>
                            <li class="reveal card-flat lift py-4 text-sm"><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($process !== []): ?>
    <section class="section rule" aria-labelledby="process-heading">
        <div class="container-site">
            <p class="section-index">Process</p>
            <h2 id="process-heading" class="display-lg reveal mt-3" data-split>How it runs</h2>

            <ol class="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($process as $index => $step): ?>
                    <li class="reveal rule-draw pt-6">
                        <p class="font-mono text-xs text-muted/70">
                            <?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?>
                        </p>
                        <h3 class="mt-3 text-lg"><?= e($step['title'] ?? '') ?></h3>
                        <p class="prose-body mt-2.5 text-sm"><?= e($step['description'] ?? '') ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<?php if ($service['content']): ?>
    <section class="section rule">
        <div class="container-site">
            <!-- Sanitised on save, so it is safe to render as markup here. -->
            <div class="prose-editorial reveal max-w-3xl"><?= $service['content'] ?></div>
        </div>
    </section>
<?php endif; ?>

<?php if ($cases !== []): ?>
    <section class="section rule" aria-labelledby="service-work">
        <div class="container-site">
            <p class="section-index">Proof</p>
            <h2 id="service-work" class="display-lg reveal mt-3" data-split>This work, in practice</h2>

            <ul class="mt-12 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 sm:grid-cols-2">
                <?php foreach ($cases as $case): ?>
                    <li class="reveal lift bg-ink">
                        <a href="/work/<?= e($case['slug']) ?>" class="flex h-full flex-col p-7">
                            <p class="eyebrow"><?= e($case['client_name']) ?></p>
                            <h3 class="display-md mt-3"><?= e($case['title']) ?></h3>
                            <?php $metrics = is_array($case['metrics']) ? $case['metrics'] : []; ?>
                            <?php if ($metrics !== []): ?>
                                <p class="display-md mt-6 text-body"><?= e($metrics[0]['value'] ?? '') ?></p>
                                <p class="text-xs text-muted"><?= e($metrics[0]['label'] ?? '') ?></p>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if ($faqs !== []): ?>
    <section class="section rule" aria-labelledby="service-faq">
        <div class="container-site">
            <p class="section-index">FAQ</p>
            <h2 id="service-faq" class="display-lg reveal mt-3" data-split>Questions about this service</h2>
            <div class="mt-12 max-w-3xl">
                <?= $this->include('partials/accordion', ['items' => $faqs]) ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($related !== []): ?>
    <section class="section rule" aria-label="Other services">
        <div class="container-site">
            <p class="section-index">Also</p>
            <ul class="mt-8 flex flex-wrap gap-3">
                <?php foreach ($related as $other): ?>
                    <li>
                        <a href="/services/<?= e($other['slug']) ?>" class="btn-outline">
                            <?= e($other['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?= $this->include('partials/cta-band') ?>

<?php $this->stop(); ?>
