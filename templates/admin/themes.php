<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Themes');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Themes</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'themes', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="field-error"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <section class="card">
        <h2>Safe mode</h2>
        <?php if (!empty($safe_mode)): ?>
            <p class="field-error">Theme safe mode is on. The built-in system theme is being served.</p>
        <?php else: ?>
            <p class="muted">Safe mode is off. Active package themes are eligible to serve.</p>
        <?php endif; ?>
        <p><a href="/admin/themes/safe-mode">Open recovery page</a></p>
    </section>

    <section class="card">
        <h2>Active theme</h2>
        <?php if (($active ?? null) === null): ?>
            <p class="muted">No package theme is active.</p>
        <?php else: ?>
            <table class="audit">
                <tbody>
                    <tr><th>Package</th><td><strong><?= $e($active['package_name']) ?></strong><br><code><?= $e($active['package_uid']) ?></code></td></tr>
                    <tr><th>Version</th><td><?= $e($active['release_version']) ?></td></tr>
                    <tr><th>CSS digest</th><td><code><?= $e($active['css_digest']) ?></code></td></tr>
                    <tr><th>Install state</th><td><?= $e($active['install_state']) ?></td></tr>
                    <tr><th>Activated</th><td><?= $e($state['activated_at'] ?? '') ?> UTC</td></tr>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (($lkg ?? null) !== null): ?>
            <p class="muted">Last-known-good: <code><?= $e($lkg['css_digest']) ?></code> from <?= $e($lkg['package_uid']) ?> <?= $e($lkg['release_version']) ?>.</p>
            <form method="post" action="/admin/themes/rollback" class="stacked">
                <?= $this->csrfField() ?>
                <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <button type="submit">Roll back</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Installed theme packages</h2>
        <?php if (($installs ?? []) === []): ?>
            <p class="muted">No theme packages are installed.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Installed theme packages">
        <table class="audit">
            <thead><tr><th>Package</th><th>Version</th><th>State</th><th>Latest build</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($installs as $install): ?>
                <tr>
                    <td><strong><?= $e($install['package_name']) ?></strong><br><code><?= $e($install['package_uid']) ?></code></td>
                    <td><?= $e($install['release_version'] ?? '') ?></td>
                    <td><span class="pill"><?= $e(ucfirst((string) $install['state'])) ?></span></td>
                    <td>
                        <?php if (($install['latest_build'] ?? null) !== null): ?>
                            <code><?= $e($install['latest_build']['css_digest']) ?></code>
                        <?php else: ?>
                            <span class="muted">not built</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-cell">
                        <?php if ($install['state'] === 'enabled'): ?>
                            <form method="post" action="/admin/themes/<?= (int) $install['id'] ?>/preview" class="inline-form">
                                <?= $this->csrfField() ?>
                                <button type="submit">Preview</button>
                            </form>
                            <form method="post" action="/admin/themes/<?= (int) $install['id'] ?>/activate" class="stacked">
                                <?= $this->csrfField() ?>
                                <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
                                <button type="submit">Activate</button>
                            </form>
                        <?php else: ?>
                            <a href="/admin/packages/<?= (int) $install['package_id'] ?>">Enable it from Packages first</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Preview</h2>
        <?php if (($preview ?? null) === null): ?>
            <p class="muted">No session preview is active.</p>
        <?php else: ?>
            <p>Previewing <strong><?= $e($preview['package_name']) ?></strong> <code><?= $e($preview['css_digest']) ?></code> in this admin session only.</p>
            <form method="post" action="/admin/themes/preview/clear" class="inline-form">
                <?= $this->csrfField() ?>
                <button type="submit">End preview</button>
            </form>
        <?php endif; ?>
    </section>
    </div>
</div>
