<?php $this->extend('layouts/auth'); ?>

<?php $this->start('title'); ?>Reset password<?php $this->stop(); ?>
<?php $this->start('heading'); ?>Reset your password<?php $this->stop(); ?>
<?php $this->start('subheading'); ?>We'll email you a link that works for one hour.<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<form method="post" action="/admin/forgot-password" novalidate>
    <?= csrf_field() ?>

    <div class="mb-6">
        <label for="email" class="field-label">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= e(old('email')) ?>"
            class="field-input"
            autocomplete="username"
            required
            autofocus
            <?= error_for('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
        >
        <?php if ($message = error_for('email')): ?>
            <p class="field-error" id="email-error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn-primary w-full">Send reset link</button>

    <p class="mt-6 text-center text-sm">
        <a href="/admin/login" class="text-accent hover:underline">Back to sign in</a>
    </p>
</form>
<?php $this->stop(); ?>
