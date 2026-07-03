<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package security response');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Package security response</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'packages', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted">The emergency brake applies regardless of the package flag. Advisory ingest, acknowledgement, and the local blocklist live on the <a href="/admin/registries">registry trust console</a>.</p>

    <section class="card">
        <h2>Emergency execution brake
            <?= $execution_disabled ? '<span class="pill pill-admin">disabled</span>' : '<span class="pill">live</span>' ?></h2>
        <p class="muted"><?php if ($execution_disabled): ?>Package execution is halted: <?= (int) $affected_installs ?> integration install(s) paused. Operators can still view, revoke, export, and uninstall.<?php else: ?>Package-owned webhooks and credentials are live for <?= (int) $affected_installs ?> integration install(s).<?php endif; ?></p>
        <?php foreach (['execution', 'current_password'] as $ek): ?><?php if (!empty($errors[$ek])): ?><p class="field-error"><?= $e($errors[$ek]) ?></p><?php endif; ?><?php endforeach; ?>
        <form method="post" action="/admin/packages/security/execution" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="disabled" value="<?= $execution_disabled ? '0' : '1' ?>">
            <input type="text" name="reason" placeholder="Reason (optional)" value="<?= $e($old['reason'] ?? '') ?>">
            <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
            <button class="btn" type="submit"><?= $execution_disabled ? 'Resume package execution' : 'Emergency-disable all packages' ?></button>
        </form>
    </section>

    <section class="card">
        <h2>Publishers</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Publishers">
        <table class="audit">
            <thead><tr><th>Publisher</th><th>Status</th><th>Verified</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($publishers as $pub): ?>
                <tr>
                    <td><?= $e($pub['display_name']) ?> <code><?= $e($pub['publisher_uid']) ?></code></td>
                    <td><?= $e($pub['status'] ?? 'active') ?></td>
                    <td><?= $pub['verified_at'] !== null ? $e($pub['verified_at']) . ' UTC' : 'unverified' ?></td>
                    <td><a class="btn" href="/admin/packages/publishers/<?= (int) $pub['id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="card">
        <h2>Advisories &amp; blocklist</h2>
        <p class="muted"><?= count($advisories) ?> advisory record(s), <?= count($blocklist) ?> local block(s). Ingest, acknowledge, and block on the <a href="/admin/registries">registry trust console</a>.</p>
    </section>

    <section class="card">
        <h2>Transparency log</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Transparency log">
        <table class="audit">
            <thead><tr><th>When</th><th>Event</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($transparency as $row): ?>
                <tr><td class="nowrap"><?= $e($row['created_at']) ?></td><td><?= $e($row['event']) ?></td><td><?= $e($row['detail'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    </div>
</div>
