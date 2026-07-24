<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?><?= $record ? 'Edit post' : 'New post' ?><?php $this->stop(); ?>

<?php $this->start('content'); ?>
<form method="post"
      action="<?= e($resource['route']) ?><?= $record ? '/' . (int) $record['id'] : '' ?>"
      novalidate>
    <?= csrf_field() ?>
    <?php if ($record): ?><?= method_field('PATCH') ?><?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main column -->
        <div class="lg:col-span-2">
            <div class="card p-5 sm:p-6">
                <?= $this->include('partials/field', [
                    'name'     => 'title',
                    'label'    => 'Title',
                    'value'    => $record['title'] ?? '',
                    'required' => true,
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'slug',
                    'label' => 'Slug',
                    'value' => $record['slug'] ?? '',
                    'hint'  => 'Leave blank to generate from the title. If the slug is taken, a number is appended.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'excerpt',
                    'label' => 'Excerpt',
                    'type'  => 'textarea',
                    'rows'  => 3,
                    'value' => $record['excerpt'] ?? '',
                    'hint'  => 'Shown on the blog index and used as the fallback meta description.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'content',
                    'label' => 'Content',
                    'type'  => 'richtext',
                    'value' => $record['content'] ?? '',
                ]) ?>
            </div>

            <div class="card mt-6 p-5 sm:p-6">
                <h2 class="mb-4 text-sm font-semibold">Search engine listing</h2>

                <?= $this->include('partials/field', [
                    'name'  => 'meta_title',
                    'label' => 'Meta title',
                    'value' => $record['meta_title'] ?? '',
                    'hint'  => 'Falls back to the post title. Around 60 characters shows in full.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'meta_description',
                    'label' => 'Meta description',
                    'type'  => 'textarea',
                    'rows'  => 2,
                    'value' => $record['meta_description'] ?? '',
                    'hint'  => 'Falls back to the excerpt. Around 155 characters shows in full.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'og_media_id',
                    'label' => 'Social share image',
                    'type'  => 'media',
                    'value' => $record['og_media_id'] ?? '',
                    'media' => $ogMedia,
                    'hint'  => 'Used by Facebook, LinkedIn and X. Falls back to the featured image.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'canonical_url',
                    'label' => 'Canonical URL',
                    'type'  => 'url',
                    'value' => $record['canonical_url'] ?? '',
                    'hint'  => 'Only set this if the piece was published elsewhere first.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'noindex',
                    'label' => 'Hide from search engines',
                    'type'  => 'checkbox',
                    'value' => (string) ($record['noindex'] ?? 0),
                    'hint'  => 'Adds noindex. The page stays reachable by direct link.',
                ]) ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <div class="card p-5">
                <h2 class="mb-4 text-sm font-semibold">Publishing</h2>

                <?= $this->include('partials/field', [
                    'name'     => 'status',
                    'label'    => 'Status',
                    'type'     => 'select',
                    'options'  => $statuses,
                    'value'    => $record['status'] ?? 'draft',
                    'required' => true,
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'published_at',
                    'label' => 'Publish date',
                    'type'  => 'datetime-local',
                    'value' => isset($record['published_at']) && $record['published_at']
                        ? date('Y-m-d\TH:i', strtotime((string) $record['published_at']))
                        : '',
                    'hint'  => 'Required to schedule. A past date publishes immediately.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'is_featured',
                    'label' => 'Feature this post',
                    'type'  => 'checkbox',
                    'value' => (string) ($record['is_featured'] ?? 0),
                ]) ?>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <?= $record ? 'Save changes' : 'Create post' ?>
                    </button>
                    <a href="<?= e($resource['route']) ?>" class="btn-ghost">Cancel</a>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-4 text-sm font-semibold">Organisation</h2>

                <?= $this->include('partials/field', [
                    'name'    => 'category_id',
                    'label'   => 'Category',
                    'type'    => 'select',
                    'options' => $categories,
                    'value'   => $record['category_id'] ?? '',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'tags',
                    'label' => 'Tags',
                    'type'  => 'tags',
                    'value' => $tagNames,
                    'hint'  => 'Comma separated. New tags are created automatically.',
                ]) ?>

                <?= $this->include('partials/field', [
                    'name'  => 'featured_media_id',
                    'label' => 'Featured image',
                    'type'  => 'media',
                    'value' => $record['featured_media_id'] ?? '',
                    'media' => $featuredMedia,
                ]) ?>
            </div>

            <?php if ($record): ?>
                <div class="card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Details</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Reading time</dt>
                            <dd><?= e((string) $record['reading_time']) ?> min</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Created</dt>
                            <dd><?= e(format_date((string) $record['created_at'], 'j M Y')) ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Last updated</dt>
                            <dd><?= e(format_date((string) $record['updated_at'], 'j M Y')) ?></dd>
                        </div>
                    </dl>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php $this->stop(); ?>
