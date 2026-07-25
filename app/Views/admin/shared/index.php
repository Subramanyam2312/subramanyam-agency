<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?><?= e($resource['plural']) ?><?php $this->stop(); ?>

<?php $this->start('actions'); ?>
<a href="<?= e($resource['route']) ?>/create" class="btn-primary">
    New <?= e(strtolower($resource['singular'])) ?>
</a>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<?php
/**
 * Generic list screen shared by every content module. Rendered from the column spec
 * the controller returns, so search, filtering, pagination, empty states and the
 * delete confirmation behave identically across all of them.
 */

// Preserve active filters when paginating.
$queryFor = static function (array $overrides) use ($filters): string {
    $params = array_filter(
        array_merge($filters, $overrides),
        static fn ($value): bool => $value !== '' && $value !== null
    );

    return $params === [] ? '' : '?' . http_build_query($params);
};

$hasFilters = ($filters['search'] ?? '') !== '';

foreach (array_keys($filterSpec) as $filterKey) {
    if (($filters[$filterKey] ?? '') !== '') {
        $hasFilters = true;
    }
}
?>

<form method="get" action="<?= e($resource['route']) ?>" class="mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[200px] flex-1">
        <label for="search" class="field-label">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
               class="field-input" placeholder="Search <?= e(strtolower($resource['plural'])) ?>…">
    </div>

    <?php foreach ($filterSpec as $key => $spec): ?>
        <div class="min-w-[160px]">
            <label for="filter-<?= e($key) ?>" class="field-label"><?= e($spec['label']) ?></label>
            <select id="filter-<?= e($key) ?>" name="<?= e($key) ?>" class="field-input">
                <option value="">All</option>
                <?php foreach ($spec['options'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) ($filters[$key] ?? '') === (string) $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn-ghost">Apply</button>

    <?php if ($hasFilters): ?>
        <a href="<?= e($resource['route']) ?>" class="btn-ghost">Clear</a>
    <?php endif; ?>
</form>

<div class="card overflow-hidden">
    <?php if ($rows === []): ?>
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-muted">
                <?php if ($hasFilters): ?>
                    No <?= e(strtolower($resource['plural'])) ?> match those filters.
                <?php else: ?>
                    No <?= e(strtolower($resource['plural'])) ?> yet.
                <?php endif; ?>
            </p>
            <a href="<?= e($resource['route']) ?>/create" class="btn-primary mt-5">
                Create the first one
            </a>
        </div>
    <?php else: ?>
        <!-- Wide tables scroll inside their own container; the page never scrolls sideways. -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line/70 text-xs uppercase tracking-wider text-muted">
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">
                                <?= e($column['label']) ?>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col" class="px-4 py-3 text-right font-medium">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line/70">
                    <?php foreach ($rows as $row): ?>
                        <tr class="hover:bg-raised/60">
                            <?php foreach ($columns as $column): ?>
                                <?php
                                $key   = $column['key'];
                                $value = $row[$key] ?? null;
                                $type  = $column['type'] ?? 'text';
                                ?>
                                <td class="px-4 py-3 align-top">
                                    <?php if ($type === 'primary'): ?>
                                        <a href="<?= e($resource['route']) ?>/<?= (int) $row['id'] ?>/edit"
                                           class="font-medium text-body hover:text-accent">
                                            <?= e(str_limit((string) $value, 70)) ?>
                                        </a>
                                        <?php if (!empty($column['sub']) && !empty($row[$column['sub']])): ?>
                                            <span class="mt-0.5 block text-xs text-muted">
                                                <?= e(str_limit((string) $row[$column['sub']], 80)) ?>
                                            </span>
                                        <?php endif; ?>

                                    <?php elseif ($type === 'badge'): ?>
                                        <?php
                                        $tone = $column['tones'][$value] ?? 'muted';
                                        $classes = [
                                            'positive' => 'bg-positive/15 text-positive border-positive/30',
                                            'warning'  => 'bg-warning/15 text-warning border-warning/30',
                                            'danger'   => 'bg-danger/15 text-danger border-danger/30',
                                            'muted'    => 'bg-raised text-muted border-line',
                                        ][$tone];
                                        ?>
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs <?= e($classes) ?>">
                                            <?= e($column['labels'][$value] ?? (string) $value) ?>
                                        </span>

                                    <?php elseif ($type === 'bool'): ?>
                                        <?php if ((int) $value === 1): ?>
                                            <span class="text-positive" title="Yes" aria-label="Yes">&check;</span>
                                        <?php else: ?>
                                            <span class="text-muted" title="No" aria-label="No">—</span>
                                        <?php endif; ?>

                                    <?php elseif ($type === 'score'): ?>
                                        <?php
                                        // RankMath traffic-light bands: 0 means no
                                        // focus keyword set, so show a neutral dash.
                                        $s = (int) $value;
                                        $tone = $s === 0 ? 'muted'
                                            : ($s >= 81 ? 'positive' : ($s >= 51 ? 'warning' : 'danger'));
                                        $toneClass = [
                                            'positive' => 'bg-positive/15 text-positive border-positive/30',
                                            'warning'  => 'bg-warning/15 text-warning border-warning/30',
                                            'danger'   => 'bg-danger/15 text-danger border-danger/30',
                                            'muted'    => 'bg-raised text-muted border-line',
                                        ][$tone];
                                        ?>
                                        <?php if ($s === 0): ?>
                                            <span class="text-muted" title="No focus keyword set">—</span>
                                        <?php else: ?>
                                            <span class="inline-flex min-w-[2.5rem] justify-center rounded-full border px-2 py-0.5 text-xs tabular-nums <?= e($toneClass) ?>">
                                                <?= e((string) $s) ?>
                                            </span>
                                        <?php endif; ?>

                                    <?php elseif ($type === 'date'): ?>
                                        <?php if ($value): ?>
                                            <time datetime="<?= e((string) $value) ?>" class="whitespace-nowrap text-muted">
                                                <?= e(format_date((string) $value, 'j M Y')) ?>
                                            </time>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>

                                    <?php elseif ($type === 'image'): ?>
                                        <?php if ($value): ?>
                                            <img src="/<?= e(ltrim((string) $value, '/')) ?>" alt=""
                                                 class="h-10 w-10 rounded object-cover" loading="lazy">
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <span class="text-muted"><?= e(str_limit((string) $value, 60)) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>

                            <td class="px-4 py-3 text-right align-top">
                                <div class="flex justify-end gap-2">
                                    <a href="<?= e($resource['route']) ?>/<?= (int) $row['id'] ?>/edit"
                                       class="text-sm text-accent hover:underline">Edit</a>

                                    <form method="post" action="<?= e($resource['route']) ?>/<?= (int) $row['id'] ?>"
                                          data-confirm="Delete this <?= e(strtolower($resource['singular'])) ?>? This cannot be undone from here.">
                                        <?= csrf_field() ?>
                                        <?= method_field('DELETE') ?>
                                        <button type="submit" class="text-sm text-danger hover:underline">Delete</button>
                                    </form>
                                </div>
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
    'baseUrl'    => $resource['route'],
    'queryFor'   => $queryFor,
]) ?>

<?php $this->stop(); ?>
