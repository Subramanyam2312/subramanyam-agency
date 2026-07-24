<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>API tokens<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<?php if ($freshToken !== null): ?>
    <div class="card mb-6 border-positive/40 p-5">
        <h2 class="text-sm font-semibold text-positive">Your new token</h2>
        <p class="mt-1 text-sm text-muted">
            Copy it now. Only a hash is stored, so this is the one and only time it can be shown.
        </p>
        <code class="mt-3 block overflow-x-auto rounded-lg border border-line bg-raised p-3 font-mono text-sm">
            <?= e($freshToken) ?>
        </code>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <?php if ($tokens === []): ?>
                <div class="px-6 py-16 text-center">
                    <p class="text-sm text-muted">No tokens yet. Create one to let an external client publish here.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-line/70 text-xs uppercase tracking-wider text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Name</th>
                                <th scope="col" class="px-4 py-3 font-medium">Abilities</th>
                                <th scope="col" class="px-4 py-3 font-medium">Last used</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            <?php foreach ($tokens as $token): ?>
                                <?php
                                $revoked = $token['revoked_at'] !== null;
                                $expired = $token['expires_at'] !== null && strtotime((string) $token['expires_at']) < time();
                                ?>
                                <tr class="<?= $revoked || $expired ? 'opacity-50' : '' ?>">
                                    <td class="px-4 py-3">
                                        <p class="font-medium"><?= e($token['name']) ?></p>
                                        <p class="mt-0.5 font-mono text-xs text-muted">
                                            sub_<?= e($token['prefix']) ?>…
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php foreach (json_column($token['abilities']) as $ability): ?>
                                            <span class="mr-1 inline-flex rounded-full border border-line bg-raised px-2 py-0.5 text-xs text-muted">
                                                <?= e($ability) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-muted">
                                        <?= $token['last_used_at'] ? e(format_date((string) $token['last_used_at'], 'j M, H:i')) : 'Never' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($revoked): ?>
                                            <span class="text-danger">Revoked</span>
                                        <?php elseif ($expired): ?>
                                            <span class="text-warning">Expired</span>
                                        <?php else: ?>
                                            <span class="text-positive">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <?php if (!$revoked): ?>
                                            <form method="post" action="/admin/api-tokens/<?= (int) $token['id'] ?>"
                                                  data-confirm="Revoke this token? Any client using it stops working immediately.">
                                                <?= csrf_field() ?>
                                                <?= method_field('DELETE') ?>
                                                <button type="submit" class="text-sm text-danger hover:underline">Revoke</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <p class="mt-4 text-xs text-muted">
            Tokens are stored as SHA-256 hashes and carry only the abilities you grant.
            Full endpoint documentation with curl examples is in <code>API.md</code>.
        </p>
    </div>

    <div>
        <form method="post" action="/admin/api-tokens" class="card p-5">
            <?= csrf_field() ?>
            <h2 class="mb-4 text-sm font-semibold">Create a token</h2>

            <?= $this->include('partials/field', [
                'name'     => 'name',
                'label'    => 'Name',
                'value'    => '',
                'required' => true,
                'hint'     => 'What will use it, for example: Publishing agent.',
            ]) ?>

            <fieldset class="mb-5">
                <legend class="field-label">Abilities</legend>
                <?php foreach ($abilities as $key => $description): ?>
                    <label class="mb-2 flex items-start gap-3">
                        <input type="checkbox" name="abilities[]" value="<?= e($key) ?>"
                               class="mt-0.5 rounded border-line bg-raised text-accent focus:ring-accent"
                               <?= $key === 'read' ? 'checked' : '' ?>>
                        <span class="text-sm text-muted"><?= e($description) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <?= $this->include('partials/field', [
                'name'  => 'expires_at',
                'label' => 'Expires',
                'type'  => 'date',
                'value' => '',
                'hint'  => 'Optional. Leave blank for a token that never expires.',
            ]) ?>

            <button type="submit" class="btn-primary w-full">Create token</button>
        </form>
    </div>
</div>
<?php $this->stop(); ?>
