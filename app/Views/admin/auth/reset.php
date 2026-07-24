<?php $this->extend('layouts/auth'); ?>

<?php $this->start('title'); ?>Choose a new password<?php $this->stop(); ?>
<?php $this->start('heading'); ?>Choose a new password<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<form method="post" action="/admin/reset-password" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="mb-4">
        <label for="password" class="field-label">New password</label>
        <input
            type="password"
            id="password"
            name="password"
            class="field-input"
            autocomplete="new-password"
            minlength="12"
            required
            autofocus
            <?= error_for('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>
        >
        <?php if ($message = error_for('password')): ?>
            <p class="field-error" id="password-error"><?= e($message) ?></p>
        <?php else: ?>
            <p class="field-hint">At least 12 characters. A passphrase beats a short complex password.</p>
        <?php endif; ?>
    </div>

    <div class="mb-6">
        <label for="password_confirmation" class="field-label">Confirm new password</label>
        <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            class="field-input"
            autocomplete="new-password"
            required
        >
    </div>

    <button type="submit" class="btn-primary w-full">Update password</button>
</form>
<?php $this->stop(); ?>
