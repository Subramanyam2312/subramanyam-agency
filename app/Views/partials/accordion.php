<?php
/**
 * FAQ accordion.
 *
 * Built on <details>/<summary> rather than divs plus JavaScript: the browser
 * gives correct keyboard behaviour, correct semantics for assistive tech, and
 * in-page find still reaches collapsed answers. The chevron rotation is the only
 * thing CSS has to add.
 *
 * Expects $items (rows with question/answer) and optionally $group.
 */
$items = $items ?? [];
$group = $group ?? '';
?>
<?php if ($group !== ''): ?>
    <h3 class="eyebrow mb-5"><?= e($group) ?></h3>
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
