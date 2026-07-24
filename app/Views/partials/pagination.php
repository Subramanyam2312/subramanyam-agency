<?php

/**
 * Pagination. Expects $pagination (the array a paginate() call returns), $baseUrl,
 * and $queryFor — a closure that rebuilds the query string with the active filters
 * preserved, so paging never silently drops a search term.
 */

if (($pagination['last_page'] ?? 1) <= 1) {
    return;
}

$current = (int) $pagination['current_page'];
$last    = (int) $pagination['last_page'];

// A window around the current page, always including the first and last.
$window = [];

foreach (range(1, $last) as $page) {
    if ($page === 1 || $page === $last || abs($page - $current) <= 2) {
        $window[] = $page;
    }
}

$from = (($current - 1) * (int) $pagination['per_page']) + 1;
$to   = min($current * (int) $pagination['per_page'], (int) $pagination['total']);
?>
<nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
    <p class="text-sm text-muted">
        Showing <?= e((string) $from) ?>–<?= e((string) $to) ?> of <?= e((string) $pagination['total']) ?>
    </p>

    <ul class="flex flex-wrap items-center gap-1">
        <li>
            <?php if ($current > 1): ?>
                <a href="<?= e($baseUrl . $queryFor(['page' => $current - 1])) ?>"
                   class="btn-ghost h-9 px-3" rel="prev">Previous</a>
            <?php else: ?>
                <span class="btn-ghost h-9 px-3 opacity-40" aria-disabled="true">Previous</span>
            <?php endif; ?>
        </li>

        <?php $previous = 0; ?>
        <?php foreach ($window as $page): ?>
            <?php if ($previous !== 0 && $page - $previous > 1): ?>
                <li aria-hidden="true" class="px-1 text-muted">…</li>
            <?php endif; ?>
            <li>
                <?php if ($page === $current): ?>
                    <span class="btn-ghost h-9 w-9 bg-raised p-0 text-body" aria-current="page"><?= e((string) $page) ?></span>
                <?php else: ?>
                    <a href="<?= e($baseUrl . $queryFor(['page' => $page])) ?>"
                       class="btn-ghost h-9 w-9 p-0"><?= e((string) $page) ?></a>
                <?php endif; ?>
            </li>
            <?php $previous = $page; ?>
        <?php endforeach; ?>

        <li>
            <?php if ($current < $last): ?>
                <a href="<?= e($baseUrl . $queryFor(['page' => $current + 1])) ?>"
                   class="btn-ghost h-9 px-3" rel="next">Next</a>
            <?php else: ?>
                <span class="btn-ghost h-9 px-3 opacity-40" aria-disabled="true">Next</span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
