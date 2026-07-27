<?php

use App\Models\PageBlock;

/**
 * Closing call to action, shared by every inner page so the copy is edited in
 * one place rather than repeated across templates.
 */
?>
<section class="section rule">
    <div class="container-site text-center">
        <h2 class="display-lg gilt reveal mx-auto max-w-[18ch]" data-split>
            <?= e(PageBlock::value('home', 'cta_heading', 'Tell us what is not working')) ?>
        </h2>

        <p class="lede reveal mx-auto mt-6 text-center">
            <?= e(PageBlock::value('home', 'cta_text')) ?>
        </p>

        <div class="reveal mt-10">
            <a href="/contact" class="btn-bone">
                <?= e(PageBlock::value('home', 'cta_button', 'Book a call')) ?>
            </a>
        </div>
    </div>
</section>
