<?php

use App\Core\Fonts;

/**
 * Applies the chosen type pairing.
 *
 * Self-hosted pairings need one small nonce'd style block to repoint two custom
 * properties — the faces are already in the stylesheet. Google mode additionally
 * emits the stylesheet link and is the only case that reaches off-site, which is
 * why SecurityHeaders widens the policy for it and not otherwise.
 *
 * Nothing is printed at all when the brand default is in use, so the common case
 * costs zero bytes.
 */
$stacks = Fonts::stacks();

if ($stacks === null) {
    return;
}
?>
<?php if (Fonts::usesGoogle()): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e(Fonts::googleUrl()) ?>">
<?php endif; ?>
<style nonce="<?= e(csp_nonce()) ?>">
:root, .site {
    --font-display: <?= $stacks['display'] ?>;
    --font-body: <?= $stacks['body'] ?>;
}
</style>
