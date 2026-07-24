<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Enquiry from <?= e($submission['name']) ?><?php $this->stop(); ?>

<?php $this->start('content'); ?>
<div class="mx-auto max-w-3xl">
    <p class="mb-5">
        <a href="/admin/submissions" class="text-sm text-accent hover:underline">← All enquiries</a>
    </p>

    <div class="card p-5 sm:p-6">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-line/70 pb-5">
            <div>
                <h2 class="text-lg font-semibold"><?= e($submission['name']) ?></h2>
                <p class="mt-1 text-sm text-muted">
                    <a href="mailto:<?= e($submission['email']) ?>" class="text-accent hover:underline">
                        <?= e($submission['email']) ?>
                    </a>
                </p>
            </div>
            <time class="text-sm text-muted" datetime="<?= e((string) $submission['created_at']) ?>">
                <?= e(format_date((string) $submission['created_at'], 'j M Y, H:i')) ?>
            </time>
        </div>

        <dl class="mb-6 grid gap-4 sm:grid-cols-2">
            <?php
            $details = [
                'Phone'    => $submission['phone'] ?? '',
                'Company'  => $submission['company'] ?? '',
                'Service'  => $submission['service_title'] ?? '',
                'Budget'   => $submission['budget_range'] ?? '',
            ];
            ?>
            <?php foreach ($details as $label => $value): ?>
                <?php if ((string) $value !== ''): ?>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted"><?= e($label) ?></dt>
                        <dd class="mt-0.5 text-sm"><?= e($value) ?></dd>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>

        <div class="rounded-lg border border-line bg-raised p-4">
            <h3 class="mb-2 text-xs uppercase tracking-wider text-muted">Message</h3>
            <!-- nl2br over an escaped string: line breaks are preserved without
                 letting any submitted markup through. -->
            <p class="whitespace-pre-line text-sm leading-relaxed"><?= e($submission['message']) ?></p>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="mailto:<?= e($submission['email']) ?>?subject=<?= e(rawurlencode('Re: your enquiry')) ?>"
               class="btn-primary">Reply by email</a>

            <form method="post" action="/admin/submissions/<?= (int) $submission['id'] ?>/read">
                <?= csrf_field() ?>
                <?= method_field('PATCH') ?>
                <button type="submit" class="btn-ghost">
                    Mark as <?= (int) $submission['is_read'] === 1 ? 'unread' : 'read' ?>
                </button>
            </form>

            <form method="post" action="/admin/submissions/<?= (int) $submission['id'] ?>/spam">
                <?= csrf_field() ?>
                <?= method_field('PATCH') ?>
                <button type="submit" class="btn-ghost">
                    <?= (int) $submission['is_spam'] === 1 ? 'Not spam' : 'Mark as spam' ?>
                </button>
            </form>

            <form method="post" action="/admin/submissions/<?= (int) $submission['id'] ?>" class="ml-auto"
                  data-confirm="Delete this enquiry? This cannot be undone from here.">
                <?= csrf_field() ?>
                <?= method_field('DELETE') ?>
                <button type="submit" class="btn-danger">Delete</button>
            </form>
        </div>
    </div>

    <p class="mt-4 text-xs text-muted">
        Received from a hashed IP address — the raw address is never stored.
        <?php if ($submission['referrer']): ?>
            Referred from <?= e($submission['referrer']) ?>.
        <?php endif; ?>
    </p>
</div>
<?php $this->stop(); ?>
