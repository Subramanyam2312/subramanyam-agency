<?php

use App\Models\PageBlock;

$this->extend('layouts/site');

$block = static fn (string $key, string $default = ''): string => PageBlock::value('about', $key, $default);
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'About',
    'heading' => $block('story_heading', 'Built the long way round'),
]) ?>

<section class="section rule">
    <div class="container-site grid gap-14 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <!-- Rich text, sanitised on save. -->
            <div class="prose-editorial reveal"><?= $block('story_body') ?></div>
        </div>

        <aside class="lg:col-span-4 lg:col-start-9">
            <div class="card-flat">
                <p class="eyebrow">Working with us</p>
                <ul class="mt-5 space-y-3 text-sm text-muted">
                    <li>Senior people on the work, no account layer</li>
                    <li>Flat monthly fee, never a percentage of spend</li>
                    <li>You own every account and dashboard</li>
                    <li>Three-month minimum on retainers</li>
                </ul>
                <a href="/contact" class="btn-outline mt-7 w-full">Talk to us</a>
            </div>
        </aside>
    </div>
</section>

<!-- ========================================================== VALUES -->
<section class="section rule" aria-labelledby="values-heading">
    <div class="container-site">
        <p class="section-index">Values</p>
        <h2 id="values-heading" class="display-lg reveal mt-3" data-split>
            <?= e($block('values_heading', 'How we operate')) ?>
        </h2>

        <ul class="mt-14 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 sm:grid-cols-2">
            <?php for ($value = 1; $value <= 4; $value++): ?>
                <?php $title = $block("value_{$value}_title"); ?>
                <?php if ($title === '') { continue; } ?>
                <li class="reveal lift bg-ink p-8">
                    <p class="font-mono text-xs text-muted/70">0<?= $value ?></p>
                    <h3 class="display-md mt-4"><?= e($title) ?></h3>
                    <p class="prose-body mt-3 text-sm"><?= e($block("value_{$value}_body")) ?></p>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
</section>

<!-- ======================================================== TIMELINE -->
<?php if ($timeline !== []): ?>
    <section class="section rule" aria-labelledby="timeline-heading">
        <div class="container-site">
            <p class="section-index">Timeline</p>
            <h2 id="timeline-heading" class="display-lg reveal mt-3" data-split>How we got here</h2>

            <ol class="mt-14">
                <?php foreach ($timeline as $entry): ?>
                    <li class="reveal rule-draw grid gap-4 py-8 sm:grid-cols-12">
                        <p class="display-md sm:col-span-3"><?= e($entry['year']) ?></p>
                        <div class="sm:col-span-9">
                            <h3 class="text-lg"><?= e($entry['title']) ?></h3>
                            <?php if ($entry['description']): ?>
                                <p class="prose-body mt-2 text-sm"><?= e($entry['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<!-- =========================================================== TOOLS -->
<?php if ($logos !== []): ?>
    <section class="rule py-14" aria-label="Platforms we work in">
        <p class="container-site eyebrow mb-8"><?= e($block('tools_heading', 'Platforms we work in daily')) ?></p>

        <div class="marquee" data-marquee>
            <?php for ($copy = 0; $copy < 2; $copy++): ?>
                <ul class="marquee-track" <?= $copy === 1 ? 'aria-hidden="true"' : '' ?>>
                    <?php foreach ($logos as $logo): ?>
                        <li class="shrink-0">
                            <img src="/<?= e(ltrim((string) $logo['media_path'], '/')) ?>"
                                 alt="<?= e($logo['media_alt'] ?: $logo['name']) ?>"
                                 class="h-7 w-auto opacity-50 transition-opacity hover:opacity-90"
                                 loading="lazy" decoding="async">
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endfor; ?>
        </div>
    </section>
<?php endif; ?>

<?= $this->include('partials/cta-band') ?>

<?php $this->stop(); ?>
