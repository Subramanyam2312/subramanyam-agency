<?php $this->extend('layouts/site'); ?>

<?php $metrics = is_array($case['metrics']) ? $case['metrics'] : []; ?>

<?php
/** File-based cover for this case study, cache-busted by mtime; '' if none. */
$coverPath = '/uploads/work/' . $case['slug'] . '.jpg';
$coverFile = PUBLIC_PATH . $coverPath;
$cover     = is_file($coverFile) ? $coverPath . '?v=' . filemtime($coverFile) : '';
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => trim(($case['client_name'] ?? '') . ' · ' . ($case['industry'] ?? ''), ' ·'),
    'heading' => $case['title'],
]) ?>

<?php if ($cover !== ''): ?>
    <div class="rule">
        <div class="container-site pb-4">
            <img src="<?= e($cover) ?>" alt="<?= e($case['title']) ?>"
                 class="aspect-video w-full rounded-card object-cover"
                 width="1600" height="900" fetchpriority="high" decoding="async">
        </div>
    </div>
<?php endif; ?>

<?php if ($metrics !== []): ?>
    <section class="rule py-12" aria-label="Results">
        <div class="container-site">
            <dl class="grid gap-10 sm:grid-cols-2 lg:grid-cols-<?= min(4, max(1, count($metrics))) ?>">
                <?php foreach ($metrics as $metric): ?>
                    <div class="reveal">
                        <dd class="display-lg"><?= e($metric['value'] ?? '') ?></dd>
                        <dt class="rule mt-4 pt-4 text-sm text-muted"><?= e($metric['label'] ?? '') ?></dt>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>
<?php endif; ?>

<section class="section rule">
    <div class="container-site grid gap-14 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <?php foreach ([
                'Challenge' => $case['challenge'],
                'Solution'  => $case['solution'],
                'Results'   => $case['results'],
            ] as $heading => $body): ?>
                <?php if ((string) $body === '') { continue; } ?>
                <div class="reveal mb-12">
                    <p class="section-index"><?= e($heading) ?></p>
                    <p class="prose-body mt-4 text-base leading-relaxed"><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="lg:col-span-4">
            <div class="card-flat sticky top-28">
                <p class="eyebrow">Engagement</p>
                <dl class="mt-5 space-y-4 text-sm">
                    <?php if ($case['client_name']): ?>
                        <div>
                            <dt class="text-muted">Client</dt>
                            <dd class="mt-0.5"><?= e($case['client_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($case['industry']): ?>
                        <div>
                            <dt class="text-muted">Industry</dt>
                            <dd class="mt-0.5"><?= e($case['industry']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($service !== null): ?>
                        <div>
                            <dt class="text-muted">Service</dt>
                            <dd class="mt-0.5">
                                <a href="/services/<?= e($service['slug']) ?>" class="link-underline">
                                    <?= e($service['title']) ?>
                                </a>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <a href="/contact" class="btn-bone mt-7 w-full">Start something similar</a>
            </div>
        </aside>
    </div>
</section>

<?php if ($more !== []): ?>
    <section class="section rule" aria-label="More work">
        <div class="container-site">
            <p class="section-index">Next</p>
            <ul class="mt-8 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 sm:grid-cols-2">
                <?php foreach ($more as $other): ?>
                    <li class="reveal lift bg-ink">
                        <a href="/work/<?= e($other['slug']) ?>" class="block p-7">
                            <p class="eyebrow"><?= e($other['client_name']) ?></p>
                            <h2 class="display-md mt-3"><?= e($other['title']) ?></h2>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php $this->stop(); ?>
