<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Traffic<?php $this->stop(); ?>

<?php $this->start('actions'); ?>
<div class="flex gap-1">
    <?php foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $d => $label): ?>
        <a href="/admin/traffic?days=<?= $d ?>"
           class="btn-ghost h-9 px-3 text-sm <?= $days === $d ? 'bg-raised text-body' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<?php if (!$enabled): ?>
    <div class="alert-warning mb-6">
        The Traffic Manager is turned off. Enable it in
        <a href="/admin/plugins" class="underline">Plugins</a> to start counting.
    </div>
<?php endif; ?>

<?php
$series = $data['series'];
$max = 1;
foreach ($series as $pt) { $max = max($max, (int) $pt['views']); }
?>

<!-- Stat tiles -->
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5">
        <p class="stat-value"><?= e(number_format((int) $data['today_views'])) ?></p>
        <p class="stat-label mt-1">Views today</p>
    </div>
    <div class="card p-5">
        <p class="stat-value"><?= e(number_format((int) $data['today_visitors'])) ?></p>
        <p class="stat-label mt-1">Unique visitors today</p>
    </div>
    <div class="card p-5">
        <p class="stat-value"><?= e(number_format(array_sum(array_column($series, 'views')))) ?></p>
        <p class="stat-label mt-1">Views · last <?= (int) $days ?>d</p>
    </div>
    <div class="card p-5">
        <p class="stat-value"><?= e(number_format((int) $data['total_views'])) ?></p>
        <p class="stat-label mt-1">Views · all time</p>
    </div>
</div>

<!-- Chart -->
<div class="card mt-6 p-5 sm:p-6">
    <h2 class="mb-5 text-sm font-semibold">Views per day</h2>
    <?php if (array_sum(array_column($series, 'views')) === 0): ?>
        <p class="py-12 text-center text-sm text-muted">
            No visits recorded yet. Data appears here as people browse the public site.
        </p>
    <?php else: ?>
        <!-- Inline bar chart. Bars scale to the busiest day; visitors overlaid darker. -->
        <div class="flex h-48 items-end gap-px overflow-hidden rounded-lg">
            <?php foreach ($series as $pt): ?>
                <?php
                $vh = max(2, (int) round(((int) $pt['views'] / $max) * 100));
                $uh = max(0, (int) round(((int) $pt['visitors'] / $max) * 100));
                $title = $pt['day'] . ' — ' . (int) $pt['views'] . ' views, ' . (int) $pt['visitors'] . ' visitors';
                ?>
                <div class="group relative flex flex-1 items-end" style="height:100%" title="<?= e($title) ?>">
                    <div class="w-full rounded-t bg-accent/25 transition-colors group-hover:bg-accent/40"
                         style="height:<?= $vh ?>%">
                        <div class="w-full rounded-t bg-accent" style="height:<?= $max ? (int) round($uh / max(1,$vh) * 100) : 0 ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 flex justify-between text-xs text-muted">
            <span><?= e(format_date((string) $series[0]['day'], 'j M')) ?></span>
            <span class="flex items-center gap-4">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-accent"></span> Visitors</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-accent/25"></span> Views</span>
            </span>
            <span><?= e(format_date((string) $series[count($series) - 1]['day'], 'j M')) ?></span>
        </div>
    <?php endif; ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <!-- Top pages -->
    <div class="card overflow-hidden">
        <div class="p-5"><h2 class="text-sm font-semibold">Top pages</h2></div>
        <?php if ($data['top_paths'] === []): ?>
            <p class="px-5 pb-5 text-sm text-muted">Nothing yet.</p>
        <?php else: ?>
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-line/70 border-t border-line/70">
                    <?php foreach ($data['top_paths'] as $row): ?>
                        <tr>
                            <td class="max-w-0 truncate px-5 py-2.5 font-mono text-xs text-muted"><?= e($row['path']) ?></td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums"><?= e(number_format((int) $row['views'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Referrers -->
    <div class="card overflow-hidden">
        <div class="p-5"><h2 class="text-sm font-semibold">Where visitors come from</h2></div>
        <?php if ($data['referrers'] === []): ?>
            <p class="px-5 pb-5 text-sm text-muted">No external referrers yet — most traffic is direct.</p>
        <?php else: ?>
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-line/70 border-t border-line/70">
                    <?php foreach ($data['referrers'] as $row): ?>
                        <tr>
                            <td class="max-w-0 truncate px-5 py-2.5"><?= e($row['host']) ?></td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums"><?= e(number_format((int) $row['views'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<p class="mt-6 text-xs text-muted">
    Counts are server-side and cookieless. Note: when the page cache is on, pages
    served straight from cache are not counted here — Google Analytics remains the
    fuller picture of total traffic.
</p>
<?php $this->stop(); ?>
