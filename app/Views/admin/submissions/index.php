<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Enquiries<?php $this->stop(); ?>

<?php $this->start('actions'); ?>
<a href="/admin/submissions/export" class="btn-ghost">Export CSV</a>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
$queryFor = static fn (array $overrides): string => ($params = array_filter(
    array_merge($filters, $overrides),
    static fn ($value): bool => $value !== '' && $value !== null
)) === [] ? '' : '?' . http_build_query($params);

$states = ['' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'spam' => 'Spam'];
?>

<form method="get" action="/admin/submissions" class="mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[220px] flex-1">
        <label for="search" class="field-label">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search']) ?>"
               class="field-input" placeholder="Name, email, company or message…">
    </div>
    <div class="min-w-[140px]">
        <label for="state" class="field-label">Show</label>
        <select id="state" name="state" class="field-input">
            <?php foreach ($states as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['state'] === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-ghost">Apply</button>
</form>

<div class="card overflow-hidden">
    <?php if ($rows === []): ?>
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-muted">No enquiries here.</p>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-line/70">
            <?php foreach ($rows as $row): ?>
                <li class="flex items-start gap-3 px-4 py-4 hover:bg-raised/60">
                    <span class="mt-2 h-2 w-2 shrink-0 rounded-full <?= (int) $row['is_read'] === 0 ? 'bg-accent' : 'bg-line' ?>"
                          aria-hidden="true"></span>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-baseline gap-x-2">
                            <a href="/admin/submissions/<?= (int) $row['id'] ?>"
                               class="font-medium text-body hover:text-accent">
                                <?= e($row['name']) ?>
                            </a>
                            <span class="text-xs text-muted"><?= e($row['email']) ?></span>
                            <?php if ($row['service_title']): ?>
                                <span class="rounded-full border border-line bg-raised px-2 py-0.5 text-xs text-muted">
                                    <?= e($row['service_title']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ((int) $row['is_spam'] === 1): ?>
                                <span class="rounded-full border border-danger/40 bg-danger/10 px-2 py-0.5 text-xs text-danger">
                                    Spam
                                </span>
                            <?php endif; ?>
                        </p>
                        <p class="mt-1 text-sm text-muted"><?= e(str_limit((string) $row['message'], 150)) ?></p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <time class="text-xs text-muted" datetime="<?= e((string) $row['created_at']) ?>">
                            <?= e(format_date((string) $row['created_at'], 'j M, H:i')) ?>
                        </time>
                        <a href="/admin/submissions/<?= (int) $row['id'] ?>" class="text-sm text-accent hover:underline">
                            Open
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?= $this->include('partials/pagination', [
    'pagination' => $pagination,
    'baseUrl'    => '/admin/submissions',
    'queryFor'   => $queryFor,
]) ?>

<?php $this->stop(); ?>
