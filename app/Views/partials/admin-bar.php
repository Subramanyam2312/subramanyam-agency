<?php

use App\Core\Auth;

/**
 * Floating "edit this page" shortcut, shown only to signed-in staff.
 *
 * It maps the page you are looking at to the screen that edits it, so you never
 * have to work out which CMS module owns a given page. Guests never see it, and
 * the full-page cache never stores a signed-in response, so it cannot leak into
 * a cached page.
 *
 * Expects $currentPath, and $editId for detail pages (the post / service / case
 * study being viewed).
 */
if (!Auth::check()) {
    return;
}

$path   = $currentPath ?? '/';
$editId = isset($editId) && $editId !== null ? (int) $editId : null;

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
<div class="admin-bar" role="complementary" aria-label="Editing shortcuts">
    <a href="<?= e($target) ?>" class="admin-bar-edit">
        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>
        </svg>
        <?= e($label) ?>
    </a>

    <a href="/admin" class="admin-bar-link">Dashboard</a>

    <form method="post" action="/admin/logout" class="contents">
        <?= csrf_field() ?>
        <button type="submit" class="admin-bar-link">Sign out</button>
    </form>
</div>
