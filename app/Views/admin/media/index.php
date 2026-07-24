<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Media library<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
$queryFor = static fn (array $overrides): string => ($params = array_filter(
    array_merge(['search' => $search], $overrides),
    static fn ($value): bool => $value !== '' && $value !== null
)) === [] ? '' : '?' . http_build_query($params);
?>

<form method="post" action="/admin/media" enctype="multipart/form-data"
      class="card mb-6 p-5">
    <?= csrf_field() ?>
    <label for="files" class="field-label">Upload images</label>
    <input type="file" id="files" name="files[]" multiple
           accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
           class="field-input file:mr-3 file:rounded-md file:border-0 file:bg-raised file:px-3 file:py-1.5 file:text-sm file:text-body">
    <p class="field-hint">
        JPG, PNG, WebP, GIF or SVG, up to <?= e((string) round($maxBytes / 1048576, 1)) ?> MB each.
        WebP copies are generated automatically for responsive loading.
    </p>
    <button type="submit" class="btn-primary mt-4">Upload</button>
</form>

<form method="get" action="/admin/media" class="mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[220px] flex-1">
        <label for="search" class="field-label">Search</label>
        <input type="search" id="search" name="search" value="<?= e($search) ?>"
               class="field-input" placeholder="Filename or alt text…">
    </div>
    <button type="submit" class="btn-ghost">Search</button>
    <?php if ($search !== ''): ?>
        <a href="/admin/media" class="btn-ghost">Clear</a>
    <?php endif; ?>
</form>

<?php if ($items === []): ?>
    <div class="card px-6 py-16 text-center">
        <p class="text-sm text-muted">
            <?= $search !== '' ? 'Nothing matches that search.' : 'The library is empty. Upload something above.' ?>
        </p>
    </div>
<?php else: ?>
    <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($items as $item): ?>
            <li class="card overflow-hidden">
                <div class="aspect-square bg-raised">
                    <img src="/<?= e(ltrim((string) $item['path'], '/')) ?>"
                         alt="<?= e($item['alt_text'] ?? '') ?>"
                         class="h-full w-full object-cover" loading="lazy"
                         <?= $item['width'] ? 'width="' . (int) $item['width'] . '"' : '' ?>
                         <?= $item['height'] ? 'height="' . (int) $item['height'] . '"' : '' ?>>
                </div>

                <div class="p-3">
                    <p class="truncate text-sm font-medium" title="<?= e($item['original_name']) ?>">
                        <?= e($item['original_name']) ?>
                    </p>
                    <p class="mt-0.5 text-xs text-muted">
                        <?= e(strtoupper(str_replace('image/', '', (string) $item['mime']))) ?>
                        <?php if ($item['width']): ?>
                            · <?= (int) $item['width'] ?>×<?= (int) $item['height'] ?>
                        <?php endif; ?>
                        · <?= e((string) round(((int) $item['size']) / 1024)) ?> KB
                    </p>

                    <?php if (($item['alt_text'] ?? '') === ''): ?>
                        <p class="mt-2 text-xs text-warning">No alt text</p>
                    <?php endif; ?>

                    <!-- Two separate forms, side by side. Forms cannot legally nest,
                         and a browser silently drops the inner one if they overlap. -->
                    <form method="post" action="/admin/media/<?= (int) $item['id'] ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <?= method_field('PATCH') ?>
                        <label class="sr-only" for="alt-<?= (int) $item['id'] ?>">Alt text</label>
                        <input type="text" id="alt-<?= (int) $item['id'] ?>" name="alt_text"
                               value="<?= e($item['alt_text'] ?? '') ?>"
                               class="field-input py-1.5 text-sm" placeholder="Describe this image">
                        <button type="submit" class="mt-2 text-sm text-accent hover:underline">Save</button>
                    </form>

                    <form method="post" action="/admin/media/<?= (int) $item['id'] ?>" class="mt-1"
                          data-confirm="Delete this file? The image and its generated copies are removed from the server.">
                        <?= csrf_field() ?>
                        <?= method_field('DELETE') ?>
                        <button type="submit" class="text-sm text-danger hover:underline">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?= $this->include('partials/pagination', [
    'pagination' => $pagination,
    'baseUrl'    => '/admin/media',
    'queryFor'   => $queryFor,
]) ?>

<?php $this->stop(); ?>
