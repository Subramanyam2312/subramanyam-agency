<?php $this->extend('layouts/auth'); ?>

<?php $this->start('title'); ?>Sign in<?php $this->stop(); ?>
<?php $this->start('heading'); ?>Sign in<?php $this->stop(); ?>

<?php $this->start('content'); ?>
<form method="post" action="/admin/login" novalidate>
    <?= csrf_field() ?>

    <div class="mb-4">
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

    <div class="mb-4">
        <label for="password" class="field-label">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            class="field-input"
            autocomplete="current-password"
            required
            <?= error_for('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>
        >
        <?php if ($message = error_for('password')): ?>
            <p class="field-error" id="password-error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <div class="mb-6 flex items-center justify-between">
        <label for="remember" class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" id="remember" name="remember" value="1"
                   class="rounded border-line bg-raised text-accent focus:ring-accent">
            Remember this device
        </label>

        <a href="/admin/forgot-password" class="text-sm text-accent hover:underline">Forgot password?</a>
    </div>

    <button type="submit" class="btn-primary w-full">Sign in</button>
</form>
<?php $this->stop(); ?>
