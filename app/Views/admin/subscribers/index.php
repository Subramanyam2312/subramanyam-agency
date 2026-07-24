<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Newsletter subscribers<?php $this->stop(); ?>

<?php $this->start('actions'); ?>
<a href="/admin/subscribers/export" class="btn-ghost">Export CSV</a>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
$queryFor = static fn (array $overrides): string => ($params = array_filter(
    array_merge(['search' => $search], $overrides),
    static fn ($value): bool => $value !== '' && $value !== null
)) === [] ? '' : '?' . http_build_query($params);
?>

<p class="mb-5 text-sm text-muted">
    <?= e((string) $active) ?> active <?= $active === 1 ? 'subscriber' : 'subscribers' ?>.
</p>

<form method="get" action="/admin/subscribers" class="mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[220px] flex-1">
        <label for="search" class="field-label">Search</label>
        <input type="search" id="search" name="search" value="<?= e($search) ?>"
               class="field-input" placeholder="Email address…">
    </div>
    <button type="submit" class="btn-ghost">Search</button>
    <?php if ($search !== ''): ?>
        <a href="/admin/subscribers" class="btn-ghost">Clear</a>
    <?php endif; ?>
</form>

<div class="card overflow-hidden">
    <?php if ($rows === []): ?>
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-muted">No subscribers yet.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line/70 text-xs uppercase tracking-wider text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Email</th>
                        <th scope="col" class="px-4 py-3 font-medium">Source</th>
                        <th scope="col" class="px-4 py-3 font-medium">Status</th>
                        <th scope="col" class="px-4 py-3 font-medium">Signed up</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line/70">
                    <?php foreach ($rows as $row): ?>
                        <tr class="hover:bg-raised/60">
                            <td class="px-4 py-3"><?= e($row['email']) ?></td>
                            <td class="px-4 py-3 text-muted"><?= e($row['source']) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($row['unsubscribed_at']): ?>
                                    <span class="rounded-full border border-line bg-raised px-2 py-0.5 text-xs text-muted">
                                        Unsubscribed
                                    </span>
                                <?php else: ?>
                                    <span class="rounded-full border border-positive/30 bg-positive/15 px-2 py-0.5 text-xs text-positive">
                                        Active
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted">
                                <?= e(format_date((string) $row['created_at'], 'j M Y')) ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="post" action="/admin/subscribers/<?= (int) $row['id'] ?>"
                                      data-confirm="Remove this subscriber permanently? Someone who asked to be removed should be removed for real, so this is not recoverable.">
                                    <?= csrf_field() ?>
                                    <?= method_field('DELETE') ?>
                                    <button type="submit" class="text-sm text-danger hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->include('partials/pagination', [
    'pagination' => $pagination,
    'baseUrl'    => '/admin/subscribers',
    'queryFor'   => $queryFor,
]) ?>

<?php $this->stop(); ?>
