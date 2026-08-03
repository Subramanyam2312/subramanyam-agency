<?php
/**
 * Shared inner-page hero.
 *
 * Expects $eyebrow, $heading and optionally $lede. Deliberately shorter than the
 * home hero — an inner page should get to its content, not restage the entrance.
 *
 * The heading carries data-split so site.js animates it word by word, and is NOT
 * wrapped in .reveal: it is above the fold on every page that uses it.
 */
$eyebrow = $eyebrow ?? '';
$lede    = $lede ?? '';
?>
<section class="relative isolate overflow-hidden pb-16 pt-36 sm:pb-20 sm:pt-44">
    <div class="hero-motion absolute inset-0 -z-20 opacity-60" aria-hidden="true"></div>
    <div class="hero-grain pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-40 bg-gradient-to-t from-ink to-transparent"
         aria-hidden="true"></div>

    <div class="container-site">
        <?php if ($eyebrow !== ''): ?>
            <p class="eyebrow"><?= e($eyebrow) ?></p>
        <?php endif; ?>

        <h1 class="display-lg gilt mt-5 max-w-[20ch] is-visible" data-split><?= e($heading) ?></h1>

        <?php if ($lede !== ''): ?>
            <p class="lede mt-7"><?= e($lede) ?></p>
        <?php endif; ?>
    </div>
</section>
