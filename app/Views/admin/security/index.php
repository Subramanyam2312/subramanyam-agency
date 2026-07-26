<?php $this->extend('layouts/admin'); ?>

<?php $this->start('title'); ?>Security<?php $this->stop(); ?>

<?php $this->start('content'); ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Left: rules + blocklist -->
    <div class="space-y-6 lg:col-span-2">
        <!-- Firewall rules -->
        <form method="post" action="/admin/security/settings" class="card p-5 sm:p-6">
            <?= csrf_field() ?>
            <?= method_field('PATCH') ?>
            <h2 class="mb-1 text-sm font-semibold">Firewall</h2>
            <p class="mb-5 text-xs text-muted">
                Blocks attack probes, scanner tools and request floods before they reach the app.
                Signed-in staff are never affected by these rules.
            </p>

            <?php foreach ($toggles as $key => $label): ?>
                <label for="fw-<?= e($key) ?>" class="flex items-start gap-3 border-t border-line/60 py-3 first:border-0 first:pt-0">
                    <input type="hidden" name="<?= e($key) ?>" value="0">
                    <input type="checkbox" id="fw-<?= e($key) ?>" name="<?= e($key) ?>" value="1"
                           class="mt-0.5 rounded border-field bg-raised text-accent focus:ring-accent"
                           <?= ($settings[$key] ?? false) ? 'checked' : '' ?>>
                    <span class="text-sm text-body"><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>

            <button type="submit" class="btn-primary mt-5">Save firewall settings</button>
        </form>

        <!-- Blocklist -->
        <div class="card overflow-hidden">
            <div class="p-5 sm:p-6">
                <h2 class="text-sm font-semibold">Blocked IP addresses</h2>
                <p class="mt-1 text-xs text-muted">
                    Automatic bans expire on their own. Manual blocks stay until you remove them.
                </p>

                <form method="post" action="/admin/security/block" class="mt-4 flex flex-wrap items-end gap-3">
                    <?= csrf_field() ?>
                    <div class="min-w-[180px] flex-1">
                        <label for="ip" class="field-label">IP address</label>
                        <input type="text" id="ip" name="ip" class="field-input" placeholder="203.0.113.5"
                               value="<?= e(old('ip')) ?>" <?= error_for('ip') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($m = error_for('ip')): ?><p class="field-error"><?= e($m) ?></p><?php endif; ?>
                    </div>
                    <div class="min-w-[180px] flex-1">
                        <label for="reason" class="field-label">Reason (optional)</label>
                        <input type="text" id="reason" name="reason" class="field-input"
                               placeholder="e.g. persistent login attempts" value="<?= e(old('reason')) ?>">
                    </div>
                    <button type="submit" class="btn-primary">Block</button>
                </form>
            </div>

            <?php if ($blocks === []): ?>
                <p class="px-6 pb-6 text-sm text-muted">No addresses are blocked right now.</p>
            <?php else: ?>
                <div class="overflow-x-auto border-t border-line/70">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-line/70 text-xs uppercase tracking-wider text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">IP</th>
                                <th scope="col" class="px-4 py-3 font-medium">Reason</th>
                                <th scope="col" class="px-4 py-3 font-medium">Source</th>
                                <th scope="col" class="px-4 py-3 font-medium">Expires</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            <?php foreach ($blocks as $block): ?>
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs"><?= e($block['ip']) ?></td>
                                    <td class="px-4 py-3 text-muted"><?= e(str_limit((string) ($block['reason'] ?? ''), 40)) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full border border-line bg-raised px-2 py-0.5 text-xs text-muted">
                                            <?= $block['source'] === 'manual' ? 'Manual' : 'Auto' ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-muted">
                                        <?= $block['expires_at'] ? e(format_date((string) $block['expires_at'], 'j M, H:i')) : 'Never' ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="post" action="/admin/security/block/<?= (int) $block['id'] ?>">
                                            <?= csrf_field() ?>
                                            <?= method_field('DELETE') ?>
                                            <button type="submit" class="text-sm text-accent hover:underline">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent events -->
        <div class="card overflow-hidden">
            <div class="p-5 sm:p-6">
                <h2 class="text-sm font-semibold">Recent blocked requests</h2>
                <p class="mt-1 text-xs text-muted">The last 40 requests the firewall stopped.</p>
            </div>

            <?php if ($events === []): ?>
                <p class="px-6 pb-6 text-sm text-muted">Nothing blocked recently. Quiet is good.</p>
            <?php else: ?>
                <div class="overflow-x-auto border-t border-line/70">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-line/70 text-xs uppercase tracking-wider text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">When</th>
                                <th scope="col" class="px-4 py-3 font-medium">IP</th>
                                <th scope="col" class="px-4 py-3 font-medium">Rule</th>
                                <th scope="col" class="px-4 py-3 font-medium">Request</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-muted">
                                        <?= e(format_date((string) $event['created_at'], 'j M, H:i')) ?>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs"><?= e($event['ip']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full border border-danger/30 bg-danger/10 px-2 py-0.5 text-xs text-danger">
                                            <?= e($event['rule']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-muted">
                                        <?= e($event['method']) ?> <?= e(str_limit((string) $event['path'], 46)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: status -->
    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 text-sm font-semibold">Status</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Firewall</dt>
                    <dd>
                        <?php if ($settings['firewall_enabled'] ?? false): ?>
                            <span class="rounded-full border border-positive/30 bg-positive/15 px-2 py-0.5 text-xs text-positive">On</span>
                        <?php else: ?>
                            <span class="rounded-full border border-danger/30 bg-danger/15 px-2 py-0.5 text-xs text-danger">Off</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Blocked (24h)</dt>
                    <dd class="tabular-nums"><?= e((string) $blocked24h) ?></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Active blocks</dt>
                    <dd class="tabular-nums"><?= e((string) count($blocks)) ?></dd>
                </div>
            </dl>
        </div>

        <div class="card p-5">
            <h2 class="mb-2 text-sm font-semibold">Your address</h2>
            <p class="font-mono text-sm"><?= e($currentIp) ?></p>
            <p class="mt-2 text-xs text-muted">
                You can't block this address from here, and signed-in staff bypass the
                automatic rules — so the firewall can't lock you out.
            </p>

            <?php if ($allowlist !== []): ?>
                <p class="mt-4 text-xs text-muted">
                    Always-allowed (from <code>FIREWALL_ALLOWLIST</code>):
                    <?= e(implode(', ', $allowlist)) ?>
                </p>
            <?php else: ?>
                <p class="mt-4 text-xs text-muted">
                    Tip: set <code>FIREWALL_ALLOWLIST</code> in <code>.env</code> to your own IP as a
                    permanent safety net against being locked out.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $this->stop(); ?>
