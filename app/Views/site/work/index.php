<?php $this->extend('layouts/site'); ?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'Work',
    'heading' => 'Engagements worth writing up',
    'lede'    => 'Not everything makes it onto this page. These are the ones where the numbers moved enough to be worth explaining how.',
]) ?>

<section class="section rule" aria-label="Case studies">
    <div class="container-site">
        <?php if ($cases === []): ?>
            <p class="prose-body">Case studies are being written up. Check back shortly.</p>
        <?php else: ?>
            <ul class="space-y-px overflow-hidden rounded-card border border-line/70 bg-line/70">
                <?php foreach ($cases as $index => $case): ?>
                    <?php $metrics = is_array($case['metrics']) ? $case['metrics'] : []; ?>
                    <li class="reveal lift bg-ink">
                        <a href="/work/<?= e($case['slug']) ?>" class="group grid gap-8 p-8 lg:grid-cols-12 lg:items-center">
                            <div class="lg:col-span-1">
                                <p class="font-mono text-xs text-muted/70">
                                    <?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?>
                                </p>
                            </div>

                            <div class="lg:col-span-5">
                                <p class="eyebrow"><?= e($case['client_name'] ?: $case['industry']) ?></p>
                                <h2 class="display-md mt-3"><?= e($case['title']) ?></h2>
                                <p class="prose-body mt-3 text-sm">
                                    <?= e(str_limit((string) $case['challenge'], 130)) ?>
                                </p>
                            </div>

                            <?php if ($metrics !== []): ?>
                                <dl class="flex flex-wrap gap-8 lg:col-span-5 lg:justify-end">
                                    <?php foreach (array_slice($metrics, 0, 3) as $metric): ?>
                                        <div>
                                            <dd class="display-md"><?= e($metric['value'] ?? '') ?></dd>
                                            <dt class="mt-1 text-xs text-muted"><?= e($metric['label'] ?? '') ?></dt>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>

                            <div class="lg:col-span-1 lg:text-right">
                                <svg class="inline h-4 w-4 text-muted transition-transform duration-300 group-hover:translate-x-1"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6"/>
                                </svg>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?= $this->include('partials/cta-band') ?>

<?php $this->stop(); ?>
