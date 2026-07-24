<?php
/**
 * FAQ accordion.
 *
 * Built on <details>/<summary> rather than divs plus JavaScript: the browser
 * gives correct keyboard behaviour, correct semantics for assistive tech, and
 * in-page find still reaches collapsed answers. The chevron rotation is the only
 * thing CSS has to add.
 *
 * Expects $items (rows with question/answer), optionally $group, and $level —
 * the heading level for the group label.
 *
 * $level is explicit rather than hardcoded because the correct level depends on
 * where the accordion sits: on /faq each group is a top-level section (h2), but
 * on a service page the accordion lives under a section heading, so its groups
 * are h3. Hardcoding either one produces a heading-order violation on the other
 * page — which is exactly what it did before this was a parameter.
 */
$items = $items ?? [];
$group = $group ?? '';
$level = in_array($level ?? 2, [2, 3, 4], true) ? ($level ?? 2) : 2;
?>
<?php if ($group !== ''): ?>
    <h<?= $level ?> class="eyebrow mb-5"><?= e($group) ?></h<?= $level ?>>
<?php endif; ?>

<div class="divide-y divide-line/60 border-y border-line/60">
    <?php foreach ($items as $item): ?>
        <details class="group py-5" name="faq-<?= e(preg_replace('/[^a-z0-9]+/i', '-', (string) $group)) ?>">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-6
                            text-left text-base text-body marker:hidden [&::-webkit-details-marker]:hidden">
                <span class="flex-1"><?= e($item['question']) ?></span>
                <svg class="mt-1 h-4 w-4 shrink-0 text-muted transition-transform duration-300 group-open:rotate-45"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            </summary>
            <p class="prose-body mt-4 text-sm"><?= e($item['answer']) ?></p>
        </details>
    <?php endforeach; ?>
</div>
