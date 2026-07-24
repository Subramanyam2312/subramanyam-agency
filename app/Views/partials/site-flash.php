<?php

use App\Core\Session;

/**
 * Flash messages on the public site. Given role="status" so a screen reader
 * announces a successful submission without the focus having to move.
 */
?>
<?php if (Session::hasFlash('success')): ?>
    <div class="mb-8 rounded-card border border-positive/40 bg-positive/10 px-5 py-4 text-sm text-positive"
         role="status">
        <?= e(Session::pull('success')) ?>
    </div>
<?php endif; ?>

<?php if (Session::hasFlash('error')): ?>
    <div class="mb-8 rounded-card border border-danger/40 bg-danger/10 px-5 py-4 text-sm text-danger"
         role="alert">
        <?= e(Session::pull('error')) ?>
    </div>
<?php endif; ?>
