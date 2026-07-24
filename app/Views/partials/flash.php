<?php

use App\Core\Session;

/**
 * Renders queued flash messages and any validation errors that were not attached
 * to a specific field.
 */
$messages = [
    'success' => 'alert-success',
    'error'   => 'alert-error',
    'warning' => 'alert-warning',
];
?>
<?php foreach ($messages as $key => $class): ?>
    <?php if (Session::hasFlash($key)): ?>
        <div class="<?= e($class) ?> mb-4" role="<?= $key === 'error' ? 'alert' : 'status' ?>">
            <span><?= e(Session::pull($key)) ?></span>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
