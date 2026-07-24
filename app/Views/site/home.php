<?php

use App\Models\Media;
use App\Models\PageBlock;

$this->extend('layouts/site');

/** Shorthand for a home page copy block. */
$block = static fn (string $key, string $default = ''): string => PageBlock::value('home', $key, $default);

$heroVideo  = $block('hero_video');
$heroPoster = $block('hero_poster');
?>

<?php $this->start('content'); ?>

<!-- ============================================================ HERO -->
<section class="relative isolate flex min-h-[92vh] items-end overflow-hidden pb-16 pt-32 sm:pb-24">
    <!-- Motion layers, cheapest first. The CSS gradient always runs; the optional
         self-hosted video is attached by site.js only after the hero has painted,
         only on wide viewports, and never on a metered connection. -->
    <div class="hero-motion absolute inset-0 -z-20" aria-hidden="true"></div>

    <?php if ($heroVideo !== ''): ?>
        <video class="hero-video -z-10" data-hero-video muted loop playsinline preload="none"
               <?= $heroPoster !== '' ? 'poster="' . e($heroPoster) . '"' : '' ?>
               data-src="<?= e($heroVideo) ?>" aria-hidden="true"></video>
    <?php endif; ?>

    <div class="hero-grain pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-64 bg-gradient-to-t from-ink to-transparent"
         aria-hidden="true"></div>

    <div class="container-site">
        <p class="eyebrow"><?= e($block('hero_eyebrow', 'Performance marketing studio')) ?></p>

        <h1 class="display-xl mt-6 max-w-[16ch]">
            <?= e($block('hero_headline', 'Marketing that earns its line on the P&L')) ?>
        </h1>

        <p class="lede mt-8">
            <?= e($block('hero_subheadline')) ?>
        </p>

        <div class="mt-10 flex flex-wrap items-center gap-3">
            <a href="<?= e($block('hero_cta_primary_href', '/contact')) ?>" class="btn-bone">
                <?= e($block('hero_cta_primary', 'Start a project')) ?>
            </a>
            <a href="<?= e($block('hero_cta_secondary_href', '/work')) ?>" class="btn-outline">
                <?= e($block('hero_cta_secondary', 'See the work')) ?>
            </a>
        </div>
    </div>
</section>

<!-- ====================================================== TRUST BAR -->
<?php if ($logos !== []): ?>
    <section class="rule py-10" aria-label="Selected clients">
        <p class="container-site eyebrow mb-8"><?= e($block('trust_bar_label')) ?></p>

        <div class="marquee" data-marquee>
            <!-- Two identical tracks: the second covers the seam as the first
                 scrolls out, which is what makes the loop read as continuous.
                 aria-hidden on the duplicate so it is announced only once. -->
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

<!-- ======================================================= SERVICES -->
<section class="section rule" aria-labelledby="services-heading">
    <div class="container-site">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="section-index">01</p>
                <h2 id="services-heading" class="display-lg reveal mt-3">
                    <?= e($block('services_heading', 'What we do')) ?>
                </h2>
            </div>
            <p class="prose-body reveal max-w-sm text-sm"><?= e($block('services_intro')) ?></p>
        </div>

        <ul class="mt-14 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($services as $service): ?>
                <li class="reveal bg-ink transition-colors hover:bg-surface/60">
                    <a href="/services/<?= e($service['slug']) ?>" class="group flex h-full flex-col p-7">
                        <h3 class="display-md"><?= e($service['title']) ?></h3>
                        <p class="prose-body mt-3 text-sm"><?= e($service['short_description']) ?></p>
                        <span class="mt-6 inline-flex items-center gap-2 text-sm text-muted transition-colors group-hover:text-body">
                            Explore
                            <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- ======================================================== PROCESS -->
<section class="section rule" aria-labelledby="process-heading">
    <div class="container-site">
        <p class="section-index">02</p>
        <h2 id="process-heading" class="display-lg reveal mt-3">
            <?= e($block('process_heading', 'How we work')) ?>
        </h2>
        <p class="lede reveal mt-6"><?= e($block('process_intro')) ?></p>

        <ol class="mt-16 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <?php for ($step = 1; $step <= 4; $step++): ?>
                <?php $title = $block("process_step_{$step}_title"); ?>
                <?php if ($title === '') { continue; } ?>
                <li class="reveal">
                    <p class="font-mono text-xs text-muted/70">0<?= $step ?></p>
                    <div class="rule mt-4 pt-5">
                        <h3 class="text-lg"><?= e($title) ?></h3>
                        <p class="prose-body mt-2.5 text-sm"><?= e($block("process_step_{$step}_body")) ?></p>
                    </div>
                </li>
            <?php endfor; ?>
        </ol>
    </div>
</section>

<!-- =========================================================== WORK -->
<?php if ($caseStudies !== []): ?>
    <section class="section rule" aria-labelledby="work-heading">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="section-index">03</p>
                    <h2 id="work-heading" class="display-lg reveal mt-3">
                        <?= e($block('work_heading', 'Selected work')) ?>
                    </h2>
                </div>
                <a href="/work" class="link-underline reveal text-sm text-muted hover:text-body">All work</a>
            </div>

            <ul class="mt-14 space-y-px overflow-hidden rounded-card border border-line/70 bg-line/70">
                <?php foreach ($caseStudies as $study): ?>
                    <?php $metrics = is_array($study['metrics']) ? $study['metrics'] : []; ?>
                    <li class="reveal bg-ink transition-colors hover:bg-surface/60">
                        <a href="/work/<?= e($study['slug']) ?>"
                           class="group grid gap-6 p-7 lg:grid-cols-12 lg:items-center">
                            <div class="lg:col-span-5">
                                <p class="eyebrow"><?= e($study['client_name'] ?: $study['industry']) ?></p>
                                <h3 class="display-md mt-3"><?= e($study['title']) ?></h3>
                            </div>

                            <p class="prose-body text-sm lg:col-span-4">
                                <?= e(str_limit((string) $study['challenge'], 140)) ?>
                            </p>

                            <?php if ($metrics !== []): ?>
                                <dl class="flex gap-8 lg:col-span-3 lg:justify-end">
                                    <?php foreach (array_slice($metrics, 0, 2) as $metric): ?>
                                        <div>
                                            <dd class="display-md"><?= e($metric['value'] ?? '') ?></dd>
                                            <dt class="mt-1 text-xs text-muted"><?= e($metric['label'] ?? '') ?></dt>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- ==================================================== TESTIMONIALS -->
<?php if ($testimonials !== []): ?>
    <section class="section rule" aria-labelledby="testimonials-heading" data-slider>
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="section-index">04</p>
                    <h2 id="testimonials-heading" class="display-lg reveal mt-3">
                        <?= e($block('testimonials_heading', 'What clients say')) ?>
                    </h2>
                </div>

                <?php if (count($testimonials) > 1): ?>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline h-11 w-11 p-0" data-slider-prev aria-label="Previous testimonial">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.5" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                        </button>
                        <button type="button" class="btn-outline h-11 w-11 p-0" data-slider-next aria-label="Next testimonial">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Scroll-snap carousel: native scrolling means it is keyboard and
                 touch accessible for free, and works with JS disabled. -->
            <ul class="mt-14 flex snap-x snap-mandatory gap-6 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                data-slider-track>
                <?php foreach ($testimonials as $testimonial): ?>
                    <li class="w-[min(30rem,85vw)] shrink-0 snap-start" data-slider-item>
                        <figure class="card-flat flex h-full flex-col">
                            <blockquote class="display-md leading-tight">
                                “<?= e($testimonial['quote']) ?>”
                            </blockquote>
                            <figcaption class="rule mt-auto flex items-center gap-3 pt-6">
                                <?php if ($testimonial['media_id']): ?>
                                    <?php $photo = Media::find((int) $testimonial['media_id']); ?>
                                    <?php if ($photo !== null): ?>
                                        <img src="/<?= e(ltrim((string) $photo['path'], '/')) ?>"
                                             alt="" class="h-10 w-10 rounded-full object-cover" loading="lazy">
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div>
                                    <p class="text-sm text-body"><?= e($testimonial['author_name']) ?></p>
                                    <p class="text-xs text-muted">
                                        <?= e(trim(($testimonial['author_role'] ?? '') . ' · ' . ($testimonial['company'] ?? ''), ' ·')) ?>
                                    </p>
                                </div>
                            </figcaption>
                        </figure>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- Stats band removed at the client's request. The stat_* page_blocks rows are
     left in place rather than deleted, so the section can be restored later
     without a migration or any data re-entry. -->

<!-- ======================================================== JOURNAL -->
<?php if ($posts !== []): ?>
    <section class="section rule" aria-labelledby="journal-heading">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="section-index">05</p>
                    <h2 id="journal-heading" class="display-lg reveal mt-3">
                        <?= e($block('blog_heading', 'From the journal')) ?>
                    </h2>
                </div>
                <a href="/blog" class="link-underline reveal text-sm text-muted hover:text-body">All posts</a>
            </div>

            <ul class="mt-14 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 lg:grid-cols-3">
                <?php foreach ($posts as $post): ?>
                    <li class="reveal bg-ink transition-colors hover:bg-surface/60">
                        <a href="/blog/<?= e($post['slug']) ?>" class="flex h-full flex-col p-7">
                            <p class="eyebrow"><?= e($post['category_name'] ?? 'Journal') ?></p>
                            <h3 class="mt-4 text-xl leading-snug"><?= e($post['title']) ?></h3>
                            <p class="prose-body mt-3 text-sm"><?= e(str_limit((string) $post['excerpt'], 120)) ?></p>
                            <p class="mt-6 text-xs text-muted">
                                <time datetime="<?= e((string) $post['published_at']) ?>">
                                    <?= e(format_date((string) $post['published_at'], 'j M Y')) ?>
                                </time>
                                · <?= e((string) $post['reading_time']) ?> min read
                            </p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================ CTA -->
<section class="section rule">
    <div class="container-site text-center">
        <h2 class="display-lg reveal mx-auto max-w-[18ch]">
            <?= e($block('cta_heading', 'Tell us what is not working')) ?>
        </h2>
        <p class="lede reveal mx-auto mt-6 text-center"><?= e($block('cta_text')) ?></p>
        <div class="reveal mt-10">
            <a href="/contact" class="btn-bone"><?= e($block('cta_button', 'Book a call')) ?></a>
        </div>
    </div>
</section>

<?php $this->stop(); ?>
