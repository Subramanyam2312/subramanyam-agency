<?php

use App\Models\PageBlock;

$this->extend('layouts/site');

/** Every string on this page comes from Content -> Page copy -> about. */
$block = static fn (string $key, string $default = ''): string => PageBlock::value('about', $key, $default);

/* Founder portrait, cache-busted by mtime (same asset as the home hero). */
$founderPath = '/uploads/founder/founder.jpg';
$founderFile = PUBLIC_PATH . $founderPath;
$founderImg  = is_file($founderFile) ? $founderPath . '?v=' . filemtime($founderFile) : '';

/**
 * Repeated items are numbered blocks. Collect the filled ones and drop the rest,
 * so emptying a title in the CMS removes that row instead of leaving a gap.
 *
 * @param array<int,string> $fields suffix => block key suffix
 * @return array<int,array<string,string>>
 */
$repeat = static function (int $count, string $prefix, array $fields, string $requiredField) use ($block): array {
    $items = [];

    for ($i = 1; $i <= $count; $i++) {
        $row = [];

        foreach ($fields as $name) {
            $row[$name] = $block("{$prefix}_{$i}_{$name}");
        }

        if (($row[$requiredField] ?? '') !== '') {
            $items[] = $row;
        }
    }

    return $items;
};

/* Cards can be added in the CMS, so scan to the ceiling rather than a fixed count. */
$max = (int) config('repeatables.max', 12);

$approach    = $repeat($max, 'approach_step', ['title', 'body'], 'title');
$clients     = $repeat($max, 'client', ['name', 'meta', 'href', 'body'], 'name');
$credentials = $repeat($max, 'cred', ['title', 'body'], 'title');

$faqs = [];
foreach ($repeat($max, 'faq', ['q', 'a'], 'q') as $row) {
    $faqs[] = ['question' => $row['q'], 'answer' => $row['a']];
}
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => $block('hero_eyebrow'),
    'heading' => $block('hero_heading', 'About'),
    'lede'    => $block('hero_lede'),
    'editPage' => 'about',
]) ?>

<!-- ============================================================ INTRO -->
<section class="section rule">
    <div class="container-site grid items-center gap-12 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <p class="section-index"<?= editable('about','intro_label') ?>><?= e($block('intro_label')) ?></p>
            <h2 class="display-lg reveal mt-3" data-split<?= editable('about','intro_heading') ?>><?= e($block('intro_heading')) ?></h2>

            <!-- Rich text, sanitised on save. -->
            <div class="prose-editorial reveal mt-8"<?= editable('about','intro_body','html') ?>><?= $block('intro_body') ?></div>

            <div class="mt-8 flex flex-wrap gap-3">
                <?php if ($label = $block('intro_cta_primary')): ?>
                    <a href="<?= e($block('intro_cta_primary_href', '/contact')) ?>" class="btn-bone"><?= e($label) ?></a>
                <?php endif; ?>
                <?php if ($label = $block('intro_cta_secondary')): ?>
                    <a href="<?= e($block('intro_cta_secondary_href', '/services')) ?>" class="btn-outline"><?= e($label) ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($founderImg !== ''): ?>
            <div class="lg:col-span-4 lg:col-start-9">
                <div class="about-portrait-wrap reveal">
                    <img src="<?= e($founderImg) ?>" class="about-portrait"
                         alt="<?= e($block('hero_heading', 'Subramanyam M N')) ?>"
                         width="1080" height="1446" loading="lazy" decoding="async">
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ======================================================= PHILOSOPHY -->
<?php if ($block('philosophy_heading') !== ''): ?>
    <section class="section rule" aria-labelledby="philosophy-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="section-index"<?= editable('about','philosophy_label') ?>><?= e($block('philosophy_label')) ?></p>
                <h2 id="philosophy-heading" class="display-lg reveal mt-3" data-split<?= editable('about','philosophy_heading') ?>>
                    <?= e($block('philosophy_heading')) ?>
                </h2>
            </div>
            <div class="lg:col-span-6 lg:col-start-7">
                <div class="prose-editorial reveal"<?= editable('about','philosophy_body','html') ?>><?= $block('philosophy_body') ?></div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <?php for ($card = 1; $card <= 2; $card++): ?>
                        <?php if ($title = $block("philosophy_card_{$card}_title")): ?>
                            <div class="card-flat">
                                <p class="eyebrow"><?= e($title) ?></p>
                                <p class="prose-body mt-3 text-sm"><?= e($block("philosophy_card_{$card}_body")) ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ========================================================= APPROACH -->
<?php if ($approach !== []): ?>
    <section class="section rule" aria-labelledby="approach-heading">
        <div class="container-site">
            <p class="section-index"<?= editable('about','approach_label') ?>><?= e($block('approach_label')) ?></p>
            <h2 id="approach-heading" class="display-lg reveal mt-3" data-split<?= editable('about','approach_heading') ?>><?= e($block('approach_heading')) ?></h2>

            <ol class="mt-12 divide-y divide-line/60 border-y border-line/60">
                <?php foreach ($approach as $i => $step): ?>
                    <li class="reveal grid gap-4 py-8 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <p class="font-mono text-sm text-accent"><?= sprintf('%02d', $i + 1) ?></p>
                            <h3 class="display-md mt-2"><?= e($step['title']) ?></h3>
                        </div>
                        <p class="prose-body text-sm sm:col-span-7 sm:col-start-6"><?= e($step['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<!-- ================================================ CREATIVE ADVANTAGE -->
<?php if ($block('creative_heading') !== ''): ?>
    <section class="section rule" aria-labelledby="creative-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="section-index"<?= editable('about','creative_label') ?>><?= e($block('creative_label')) ?></p>
                <h2 id="creative-heading" class="display-lg reveal mt-3" data-split<?= editable('about','creative_heading') ?>><?= e($block('creative_heading')) ?></h2>
            </div>
            <div class="lg:col-span-6 lg:col-start-7">
                <div class="prose-editorial reveal"<?= editable('about','creative_body','html') ?>><?= $block('creative_body') ?></div>

                <?php if ($highlight = $block('creative_highlight')): ?>
                    <p class="prose-body reveal mt-5 border-l-2 border-accent/50 pl-5 text-body"><?= e($highlight) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ===================================================== TRACK RECORD -->
<?php if ($clients !== []): ?>
    <section class="section rule" aria-labelledby="track-heading">
        <div class="container-site">
            <p class="section-index"<?= editable('about','work_label') ?>><?= e($block('work_label')) ?></p>
            <h2 id="track-heading" class="display-lg reveal mt-3" data-split<?= editable('about','work_heading') ?>><?= e($block('work_heading')) ?></h2>
            <p class="lede mt-6"<?= editable('about','work_lede') ?>><?= e($block('work_lede')) ?></p>

            <div class="mt-12 grid gap-6 sm:grid-cols-2">
                <?php foreach ($clients as $client): ?>
                    <article class="card reveal lift p-7">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="display-md"><?= e($client['name']) ?></h3>
                            <?php if ($client['href'] !== ''): ?>
                                <a href="<?= e($client['href']) ?>" target="_blank" rel="noopener"
                                   class="shrink-0 text-xs text-accent hover:underline">Visit&nbsp;&#8599;</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($client['meta'] !== ''): ?>
                            <p class="eyebrow mt-2"><?= e($client['meta']) ?></p>
                        <?php endif; ?>
                        <p class="prose-body mt-4 text-sm"><?= e($client['body']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($footnote = $block('work_footnote')): ?>
                <p class="prose-body mt-10 max-w-2xl text-sm text-muted"><?= e($footnote) ?></p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ====================================================== CREDENTIALS -->
<?php if ($credentials !== []): ?>
    <section class="section rule" aria-labelledby="credentials-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="section-index"<?= editable('about','cred_label') ?>><?= e($block('cred_label')) ?></p>
                <h2 id="credentials-heading" class="display-lg reveal mt-3" data-split<?= editable('about','cred_heading') ?>><?= e($block('cred_heading')) ?></h2>
                <p class="prose-body mt-6 text-sm text-muted"><?= e($block('cred_intro')) ?></p>
            </div>
            <ul class="space-y-4 lg:col-span-6 lg:col-start-7">
                <?php foreach ($credentials as $cred): ?>
                    <li class="card-flat reveal">
                        <h3 class="text-base font-medium text-body"><?= e($cred['title']) ?></h3>
                        <p class="prose-body mt-2 text-sm"><?= e($cred['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- ========================================================== JOURNEY -->
<?php if ($block('journey_heading') !== ''): ?>
    <section class="section rule" aria-labelledby="journey-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="section-index"<?= editable('about','journey_label') ?>><?= e($block('journey_label')) ?></p>
                <h2 id="journey-heading" class="display-lg reveal mt-3" data-split<?= editable('about','journey_heading') ?>><?= e($block('journey_heading')) ?></h2>
            </div>
            <div class="lg:col-span-6 lg:col-start-7">
                <div class="prose-editorial reveal"<?= editable('about','journey_body','html') ?>><?= $block('journey_body') ?></div>
                <div class="prose-editorial reveal mt-6 border-l-2 border-accent/40 pl-5 text-sm">
                    <?= $block('journey_points') ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================== FAQ -->
<?php if ($faqs !== []): ?>
    <section class="section rule" aria-labelledby="about-faq-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <p class="section-index"<?= editable('about','faq_label') ?>><?= e($block('faq_label')) ?></p>
                <h2 id="about-faq-heading" class="display-lg reveal mt-3" data-split<?= editable('about','faq_heading') ?>><?= e($block('faq_heading')) ?></h2>
            </div>
            <div class="lg:col-span-7 lg:col-start-6">
                <?= $this->include('partials/accordion', ['items' => $faqs, 'level' => 3]) ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================== CTA -->
<?php if ($block('cta_heading') !== ''): ?>
    <section class="section rule">
        <div class="container-site text-center">
            <p class="eyebrow"<?= editable('about','cta_eyebrow') ?>><?= e($block('cta_eyebrow')) ?></p>
            <h2 class="display-lg gilt reveal mx-auto mt-4 max-w-[22ch]"<?= editable('about','cta_heading') ?>><?= e($block('cta_heading')) ?></h2>
            <p class="lede mx-auto mt-6"<?= editable('about','cta_lede') ?>><?= e($block('cta_lede')) ?></p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <?php if ($label = $block('cta_primary')): ?>
                    <a href="<?= e($block('cta_primary_href', '/contact')) ?>" class="btn-bone"><?= e($label) ?></a>
                <?php endif; ?>
                <?php if ($label = $block('cta_secondary')): ?>
                    <a href="<?= e($block('cta_secondary_href', '/services')) ?>" class="btn-outline"><?= e($label) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php $this->stop(); ?>
