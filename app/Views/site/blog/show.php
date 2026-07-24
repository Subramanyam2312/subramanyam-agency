<?php $this->extend('layouts/site'); ?>

<?php $this->start('head'); ?>
<?php
/**
 * Article and BreadcrumbList structured data.
 */
$article = [
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => $post['title'],
    'description'   => $post['excerpt'],
    'datePublished' => date(DATE_ATOM, (int) strtotime((string) $post['published_at'])),
    'dateModified'  => date(DATE_ATOM, (int) strtotime((string) $post['updated_at'])),
    'author'        => ['@type' => 'Person', 'name' => $post['author_name'] ?: config('app.name')],
    'publisher'     => ['@type' => 'Organization', 'name' => config('app.name')],
    'mainEntityOfPage' => url('/blog/' . $post['slug']),
];

if ($featured !== null) {
    $article['image'] = url('/' . ltrim((string) $featured['path'], '/'));
}

$breadcrumbs = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Journal', 'item' => url('/blog')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => url('/blog/' . $post['slug'])],
    ],
];
?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
<?= json_encode($article, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>">
<?= json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<article>
    <header class="relative isolate overflow-hidden pb-14 pt-36 sm:pt-44">
        <div class="hero-motion absolute inset-0 -z-20 opacity-50" aria-hidden="true"></div>
        <div class="hero-grain pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>

        <div class="container-site max-w-3xl">
            <nav class="mb-8 text-xs text-muted" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><a href="/" class="link-underline">Home</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="/blog" class="link-underline">Journal</a></li>
                    <?php if ($post['category_slug']): ?>
                        <li aria-hidden="true">/</li>
                        <li>
                            <a href="/blog/category/<?= e($post['category_slug']) ?>" class="link-underline">
                                <?= e($post['category_name']) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ol>
            </nav>

            <h1 class="display-lg is-visible" data-split><?= e($post['title']) ?></h1>

            <p class="mt-7 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                <?php if ($post['author_name']): ?>
                    <span><?= e($post['author_name']) ?></span>
                    <span aria-hidden="true">·</span>
                <?php endif; ?>
                <time datetime="<?= e((string) $post['published_at']) ?>">
                    <?= e(format_date((string) $post['published_at'], 'j F Y')) ?>
                </time>
                <span aria-hidden="true">·</span>
                <span><?= e((string) $post['reading_time']) ?> min read</span>
            </p>
        </div>
    </header>

    <?php if ($featured !== null): ?>
        <div class="container-site max-w-4xl">
            <div class="img-reveal rounded-card border border-line/70">
                <img src="/<?= e(ltrim((string) $featured['path'], '/')) ?>"
                     alt="<?= e($featured['alt_text'] ?? '') ?>"
                     <?= $featured['width'] ? 'width="' . (int) $featured['width'] . '"' : '' ?>
                     <?= $featured['height'] ? 'height="' . (int) $featured['height'] . '"' : '' ?>
                     class="w-full object-cover" fetchpriority="high" decoding="async">
            </div>
        </div>
    <?php endif; ?>

    <div class="section">
        <div class="container-site max-w-3xl">
            <!-- Sanitised on save, safe to render as markup. -->
            <div class="prose-editorial"><?= $post['content'] ?></div>

            <?php if ($tags !== []): ?>
                <ul class="mt-14 flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                        <li class="rounded-full border border-line px-3 py-1 text-xs text-muted">
                            <?= e($tag['name']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Share links. Plain anchors rather than platform SDKs: no third-party
                 script, no tracking, and nothing to break when an SDK changes. -->
            <div class="rule mt-12 flex flex-wrap items-center gap-4 pt-8">
                <p class="eyebrow">Share</p>
                <?php
                $shareUrl   = rawurlencode(url('/blog/' . $post['slug']));
                $shareTitle = rawurlencode((string) $post['title']);
                ?>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>"
                   target="_blank" rel="noopener" class="link-underline text-sm text-muted hover:text-body">LinkedIn</a>
                <a href="https://x.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>"
                   target="_blank" rel="noopener" class="link-underline text-sm text-muted hover:text-body">X</a>
                <a href="https://wa.me/?text=<?= $shareTitle ?>%20<?= $shareUrl ?>"
                   target="_blank" rel="noopener" class="link-underline text-sm text-muted hover:text-body">WhatsApp</a>
                <a href="mailto:?subject=<?= $shareTitle ?>&body=<?= $shareUrl ?>"
                   class="link-underline text-sm text-muted hover:text-body">Email</a>
            </div>
        </div>
    </div>
</article>

<?php if ($related !== []): ?>
    <section class="section rule" aria-labelledby="related-heading">
        <div class="container-site">
            <p class="section-index">Related</p>
            <h2 id="related-heading" class="display-lg reveal mt-3" data-split>Keep reading</h2>

            <ul class="mt-12 grid gap-px overflow-hidden rounded-card border border-line/70 bg-line/70 lg:grid-cols-3">
                <?php foreach ($related as $item): ?>
                    <li class="reveal lift bg-ink">
                        <a href="/blog/<?= e($item['slug']) ?>" class="flex h-full flex-col p-7">
                            <p class="eyebrow"><?= e($item['category_name'] ?? 'Journal') ?></p>
                            <h3 class="mt-4 text-lg leading-snug"><?= e($item['title']) ?></h3>
                            <p class="prose-body mt-3 text-sm"><?= e(str_limit((string) $item['excerpt'], 100)) ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php $this->stop(); ?>
