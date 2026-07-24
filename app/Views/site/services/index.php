<?php $this->extend('layouts/site'); ?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'Services',
    'heading' => 'Four disciplines, run so they compound',
    'lede'    => 'Each one stands on its own. Together they stop competing for credit and start reinforcing each other.',
]) ?>

<section class="section rule" aria-label="All services">
    <div class="container-site">
        <ul class="grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 sm:grid-cols-2">
            <?php foreach ($services as $index => $service): ?>
                <li class="reveal lift bg-ink">
                    <a href="/services/<?= e($service['slug']) ?>" class="group flex h-full flex-col p-8">
                        <p class="font-mono text-xs text-muted/70">
                            <?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?>
                        </p>

                        <h2 class="display-md mt-4"><?= e($service['title']) ?></h2>
                        <p class="prose-body mt-3 text-sm"><?= e($service['short_description']) ?></p>

                        <?php $includes = is_array($service['includes']) ? $service['includes'] : []; ?>
                        <?php if ($includes !== []): ?>
                            <ul class="mt-6 space-y-1.5">
                                <?php foreach (array_slice($includes, 0, 4) as $item): ?>
                                    <li class="flex gap-2.5 text-sm text-muted">
                                        <span class="mt-2 h-px w-3 shrink-0 bg-line" aria-hidden="true"></span>
                                        <?= e($item) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <span class="mt-8 inline-flex items-center gap-2 text-sm text-muted transition-colors group-hover:text-body">
                            Read more
                            <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php if ($faqs !== []): ?>
    <section class="section rule" aria-labelledby="services-faq">
        <div class="container-site">
            <p class="section-index">FAQ</p>
            <h2 id="services-faq" class="display-lg reveal mt-3" data-split>Common questions</h2>

            <div class="mt-12 max-w-3xl">
                <?php foreach (array_slice($faqs, 0, 1) as $group => $items): ?>
                    <!-- h3: this accordion sits beneath the section's own h2. -->
                    <?= $this->include('partials/accordion', [
                        'items' => $items,
                        'group' => $group,
                        'level' => 3,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <a href="/faq" class="link-underline mt-10 inline-block text-sm text-muted hover:text-body">
                All questions
            </a>
        </div>
    </section>
<?php endif; ?>

<?= $this->include('partials/cta-band') ?>

<?php $this->stop(); ?>
