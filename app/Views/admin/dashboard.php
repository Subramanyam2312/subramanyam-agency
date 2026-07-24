<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Dashboard<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<?php
$stats = [
    ['label' => 'Published posts', 'value' => $counts['posts_published'], 'href' => null],
    ['label' => 'Scheduled',       'value' => $counts['posts_scheduled'], 'href' => null],
    ['label' => 'Drafts',          'value' => $counts['posts_draft'],     'href' => null],
    ['label' => 'Unread enquiries', 'value' => $counts['submissions_unread'], 'href' => null],
];
?>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?php foreach ($stats as $stat): ?>
        <div class="card p-5">
            <p class="stat-value"><?= e((string) $stat['value']) ?></p>
            <p class="stat-label mt-1"><?= e($stat['label']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="card p-5 lg:col-span-2" aria-labelledby="enquiries-heading">
        <div class="mb-4 flex items-baseline justify-between">
            <h2 id="enquiries-heading" class="text-sm font-semibold">Recent enquiries</h2>
            <p class="text-xs text-muted"><?= e((string) $counts['subscribers']) ?> newsletter subscribers</p>
        </div>

        <?php if ($submissions === []): ?>
            <p class="py-8 text-center text-sm text-muted">
                No enquiries yet. They will appear here as soon as the contact form goes live.
            </p>
        <?php else: ?>
            <ul class="divide-y divide-line/70">
                <?php foreach ($submissions as $submission): ?>
                    <li class="flex items-start gap-3 py-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= (int) $submission['is_read'] === 0 ? 'bg-accent' : 'bg-line' ?>"
                              aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-baseline gap-x-2">
                                <span class="truncate text-sm font-medium"><?= e($submission['name']) ?></span>
                                <span class="truncate text-xs text-muted"><?= e($submission['email']) ?></span>
                            </p>
                            <p class="mt-0.5 truncate text-sm text-muted"><?= e(str_limit((string) $submission['message'], 110)) ?></p>
                        </div>
                        <time class="shrink-0 text-xs text-muted" datetime="<?= e((string) $submission['created_at']) ?>">
                            <?= e(format_date((string) $submission['created_at'], 'j M')) ?>
                        </time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card p-5" aria-labelledby="activity-heading">
        <h2 id="activity-heading" class="mb-4 text-sm font-semibold">Recent activity</h2>

        <?php if ($activity === []): ?>
            <p class="py-8 text-center text-sm text-muted">Nothing logged yet.</p>
        <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($activity as $entry): ?>
                    <li class="text-sm">
                        <p class="text-body"><?= e(str_replace(['.', '_'], [' ', ' '], (string) $entry['action'])) ?></p>
                        <p class="text-xs text-muted">
                            <?= e($entry['user_name'] ?? 'System') ?> ·
                            <?= e(format_date((string) $entry['created_at'], 'j M, H:i')) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<p class="mt-6 text-xs text-muted">
    Content modules (blog, services, FAQ, testimonials, case studies, timeline, logos,
    page copy), the media library and settings arrive in Phase 3.
</p>

<?php $this->stop(); ?>
