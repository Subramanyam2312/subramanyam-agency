<?php

use App\Core\Auth;
use App\Models\PageBlock;

/**
 * Review bar, shown when a page is opened from the CMS's "view live page" arrow.
 *
 * The public site carries no admin interface of its own: this appears only when
 * the ?from=cms hint is present AND there is a signed-in session, so a visitor
 * who guesses the parameter still sees nothing. Dismissing it is a link back to
 * the same page without the parameter — no script, and the page it returns to is
 * exactly what a visitor gets.
 *
 * Expects $currentPath, $fromCms, and $editId for record pages.
 */
if (empty($fromCms) || !Auth::check()) {
    return;
}

$path    = $currentPath ?? '/';
$drafts  = PageBlock::draftCount();
$preview = PageBlock::previewing() && $drafts > 0;
$editId = isset($editId) && $editId !== null ? (int) $editId : null;

/* Only the block-driven pages carry inline-editable copy. */
$editable = in_array($currentPath ?? '/', ['/', '/about', '/contact', '/privacy', '/terms'], true);

/** Pages whose whole content is one CMS screen. */
$exact = [
    '/'         => ['/admin/page-content/home',    'Edit this page'],
    '/about'    => ['/admin/page-content/about',   'Edit this page'],
    '/contact'  => ['/admin/page-content/contact', 'Edit this page'],
    '/privacy'  => ['/admin/page-content/privacy', 'Edit this page'],
    '/terms'    => ['/admin/page-content/terms',   'Edit this page'],
    '/services' => ['/admin/services',             'Edit services'],
    '/work'     => ['/admin/case-studies',         'Edit case studies'],
    '/blog'     => ['/admin/posts',                'Edit posts'],
    '/faq'      => ['/admin/faqs',                 'Edit FAQs'],
];

if (isset($exact[$path])) {
    [$target, $label] = $exact[$path];
} elseif (str_starts_with($path, '/blog/category/')) {
    // Checked before /blog/ — a category URL is not a post.
    [$target, $label] = ['/admin/categories', 'Edit categories'];
} elseif ($editId !== null && str_starts_with($path, '/services/')) {
    [$target, $label] = ['/admin/services/' . $editId . '/edit', 'Edit this service'];
} elseif ($editId !== null && str_starts_with($path, '/work/')) {
    [$target, $label] = ['/admin/case-studies/' . $editId . '/edit', 'Edit this case study'];
} elseif ($editId !== null && str_starts_with($path, '/blog/')) {
    [$target, $label] = ['/admin/posts/' . $editId . '/edit', 'Edit this post'];
} else {
    [$target, $label] = ['/admin', 'Open the CMS'];
}
?>
<div class="cms-bar" role="complementary" aria-label="Content review"
     data-cms-bar data-csrf="<?= e(csrf_token()) ?>">
    <p class="cms-bar-note">
        <svg class="h-3.5 w-3.5 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>
        </svg>
        <span class="font-medium text-body">SUBRAMANYAM CMS</span>
        <span class="hidden sm:inline" data-cms-status>
            <?php if ($preview): ?>
                Showing your unpublished draft — visitors still see the published version.
            <?php else: ?>
                You are signed in — this is the live page, exactly as visitors see it.
            <?php endif; ?>
        </span>
    </p>

    <div class="cms-bar-actions">
        <?php if ($preview): ?>
            <form method="post" action="/admin/page-content/publish">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="/admin/page-content">
                <button type="submit" class="cms-bar-primary">Publish &rarr; live</button>
            </form>
        <?php endif; ?>

        <?php if ($editable): ?>
            <!-- Falls back to the CMS screen when JavaScript is unavailable; the
                 editor upgrades it to an in-page toggle. -->
            <a href="<?= e($target) ?>" class="cms-bar-primary" data-cms-edit>Edit this page</a>
        <?php else: ?>
            <a href="<?= e($target) ?>" class="<?= $preview ? 'cms-bar-link' : 'cms-bar-primary' ?>"><?= e($label) ?></a>
        <?php endif; ?>

        <a href="<?= e($target) ?>" class="cms-bar-link">Full CMS&nbsp;&#8599;</a>
        <a href="<?= e($path) ?>" class="cms-bar-close" title="Hide this bar" aria-label="Hide this bar">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                 stroke-linecap="round" aria-hidden="true">
                <path d="M18 6 6 18M6 6l12 12"/>
            </svg>
        </a>
    </div>
</div>

<?php if ($editable): ?>
    <!-- Preview-only, so it is never sent to a visitor. Self-hosted: 'self' covers it. -->
    <script src="<?= e(asset('/assets/js/inline-edit.js')) ?>" defer></script>
<?php endif; ?>
