<?php $this->extend('layouts/site'); ?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => $category !== null ? 'Category' : 'Journal',
    'heading' => $category !== null ? $category['name'] : 'Working notes',
    'lede'    => $category !== null
        ? (string) ($category['description'] ?? '')
        : 'Search, spend and measurement — what we are learning, and what we would do differently.',
]) ?>

<section class="section rule">
    <div class="container-site">
        <!-- Filters -->
        <div class="flex flex-wrap items-center justify-between gap-6">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="/blog" class="btn-outline h-9 px-4 text-sm <?= $category === null ? 'border-body/50 text-body' : '' ?>">
                        All
                    </a>
                </li>
                <?php foreach ($categories as $item): ?>
                    <li>
                        <a href="/blog/category/<?= e($item['slug']) ?>"
                           class="btn-outline h-9 px-4 text-sm <?= $category !== null && $category['id'] === $item['id'] ? 'border-body/50 text-body' : '' ?>">
                            <?= e($item['name']) ?>
                            <span class="text-muted"><?= e((string) $item['post_count']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="get" action="/blog" class="flex gap-2" role="search">
                <label for="q" class="sr-only">Search posts</label>
                <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Search…"
                       class="field-input h-9 rounded-full px-4 text-sm">
                <button type="submit" class="btn-outline h-9 px-4 text-sm">Search</button>
            </form>
        </div>

        <?php if ($posts === []): ?>
            <p class="prose-body mt-16">
                <?= $search !== '' ? 'Nothing matches that search.' : 'No posts published yet.' ?>
            </p>
        <?php else: ?>
            <ul class="mt-14 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($posts as $post): ?>
                    <li class="reveal lift bg-ink">
                        <a href="/blog/<?= e($post['slug']) ?>" class="flex h-full flex-col p-7">
                            <p class="eyebrow"><?= e($post['category_name'] ?? 'Journal') ?></p>
                            <h2 class="mt-4 text-xl leading-snug"><?= e($post['title']) ?></h2>
                            <p class="prose-body mt-3 text-sm"><?= e(str_limit((string) $post['excerpt'], 130)) ?></p>
                            <p class="mt-auto pt-6 text-xs text-muted">
                                <time datetime="<?= e((string) $post['published_at']) ?>">
                                    <?= e(format_date((string) $post['published_at'], 'j M Y')) ?>
                                </time>
                                · <?= e((string) $post['reading_time']) ?> min read
                            </p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            $baseUrl  = $category !== null ? '/blog/category/' . $category['slug'] : '/blog';
            $queryFor = static fn (array $overrides): string => ($params = array_filter(
                array_merge(['q' => $search], $overrides),
                static fn ($value): bool => $value !== '' && $value !== null
            )) === [] ? '' : '?' . http_build_query($params);
            ?>
            <?= $this->include('partials/pagination', [
                'pagination' => $pagination,
                'baseUrl'    => $baseUrl,
                'queryFor'   => $queryFor,
            ]) ?>
        <?php endif; ?>
    </div>
</section>

<?php $this->stop(); ?>
