<?php $this->extend('layouts/site'); ?>

<?php $this->start('content'); ?>

<?php
/** Resolve a case study's file-based cover, cache-busted by mtime; '' if none. */
$coverFor = static function (string $slug): string {
    $path = '/uploads/work/' . $slug . '.jpg';
    $file = PUBLIC_PATH . $path;
    return is_file($file) ? $path . '?v=' . filemtime($file) : '';
};
?>

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
                <?php foreach ($cases as $case): ?>
                    <?php
                    $metrics = is_array($case['metrics']) ? $case['metrics'] : [];
                    $cover   = $coverFor((string) $case['slug']);
                    ?>
                    <li class="reveal lift bg-ink">
                        <a href="/work/<?= e($case['slug']) ?>" class="group grid items-center gap-6 p-6 sm:gap-8 sm:p-8 lg:grid-cols-12">
                            <?php if ($cover !== ''): ?>
                                <div class="overflow-hidden rounded-lg lg:col-span-4">
                                    <img src="<?= e($cover) ?>"
                                         alt="<?= e($case['title']) ?> — <?= e($case['client_name'] ?: $case['industry']) ?>"
                                         class="aspect-video w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                         width="1600" height="900" loading="lazy" decoding="async">
                                </div>
                            <?php endif; ?>

                            <div class="<?= $cover !== '' ? 'lg:col-span-8' : 'lg:col-span-12' ?>">
                                <p class="eyebrow"><?= e($case['client_name'] ?: $case['industry']) ?></p>
                                <h2 class="display-md mt-3 flex items-center gap-3">
                                    <?= e($case['title']) ?>
                                    <svg class="hidden h-5 w-5 shrink-0 text-muted transition-transform duration-300 group-hover:translate-x-1 sm:inline"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path d="M5 12h14M13 6l6 6-6 6"/>
                                    </svg>
                                </h2>
                                <p class="prose-body mt-3 max-w-2xl text-sm">
                                    <?= e(str_limit((string) $case['challenge'], 150)) ?>
                                </p>

                                <?php if ($metrics !== []): ?>
                                    <ul class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs text-muted">
                                        <?php foreach (array_slice($metrics, 0, 3) as $metric): ?>
                                            <li>
                                                <span class="font-medium text-body"><?= e($metric['value'] ?? '') ?></span>
                                                <span class="ml-1"><?= e($metric['label'] ?? '') ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
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
