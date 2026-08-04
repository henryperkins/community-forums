<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Themes');
$this->section('variant', 'admin');
$errorContext = $error_context ?? null;
$errorInstallId = isset($error_install_id) ? (int) $error_install_id : null;
$errorOriginAvailable = false;
if (in_array($errorContext, ['preview', 'activate'], true) && $errorInstallId !== null) {
    foreach (($installs ?? []) as $install) {
        if ((int) $install['id'] === $errorInstallId) {
            $errorOriginAvailable = true;
            break;
        }
    }
} elseif ($errorContext === 'rollback') {
    $errorOriginAvailable = ($active ?? null) !== null && ($lkg ?? null) !== null;
}
$fallbackErrorMessages = $errorOriginAvailable
    ? []
    : array_values(array_unique(array_map('strval', $errors ?? [])));
?>
<?= $this->partial('admin/_console', [
    'area' => 'appearance',
    'tab' => 'themes',
    'pane_class' => 'admin-appearance admin-appearance-themes',
]) ?>
    <p class="pane-intro">Package themes are installed from the registry and built before they can serve. Safe mode returns the site to the built-in chrome without uninstalling anything.</p>

    <?php if ($fallbackErrorMessages !== []): ?>
        <div class="callout callout-danger theme-action-error" role="alert">
            <strong>Theme action could not be completed.</strong>
            <?php foreach ($fallbackErrorMessages as $message): ?>
                <p><?= $e($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="card theme-safe-mode-card">
        <div class="theme-card-row">
            <div>
                <h2>Safe mode</h2>
                <?php if (!empty($safe_mode)): ?>
                    <p class="theme-safe-mode-status">Safe mode is on. Every visitor sees the built-in chrome, whatever is installed.</p>
                <?php else: ?>
                    <p class="muted">Safe mode is off. Active package themes are eligible to serve.</p>
                <?php endif; ?>
            </div>
            <?php if (!empty($safe_mode)): ?>
                <a class="btn btn-secondary theme-card-action" href="/admin/themes/safe-mode">Open recovery page</a>
            <?php else: ?>
                <form method="post" action="/admin/themes/safe-mode" class="inline-form theme-card-action">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-secondary" type="submit">Enter safe mode</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="card theme-active-card">
        <h2>Active theme</h2>
        <?php if (($active ?? null) === null): ?>
            <p class="theme-empty-copy">No package theme is active. The site uses the built-in chrome.</p>
        <?php else: ?>
            <p class="theme-serving">Serving <strong><?= $e($active['package_name']) ?></strong> <code><?= $e($active['release_version']) ?></code></p>
            <?php if (($lkg ?? null) !== null): ?>
                <p class="theme-lkg-copy">Last-known-good: <code><?= $e($lkg['css_digest']) ?></code> from <?= $e($lkg['package_uid']) ?> <?= $e($lkg['release_version']) ?>.</p>
            <?php endif; ?>
            <dl class="spec-list theme-active-facts">
                <dt>CSS digest</dt>
                <dd><?= $e($active['css_digest']) ?></dd>
                <dt>Install state</dt>
                <dd><?= $e($active['install_state']) ?></dd>
                <dt>Activated</dt>
                <dd><?= $e(human_datetime($state['activated_at'] ?? null)) ?></dd>
            </dl>

            <?php if (($lkg ?? null) !== null): ?>
                <?php $rollbackErrors = $errorContext === 'rollback' ? ($errors ?? []) : []; ?>
                <form method="post" action="/admin/themes/rollback" class="theme-rollback-form">
                    <?= $this->csrfField() ?>
                    <label class="reauth-field">
                        <span>Current password</span>
                        <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($rollbackErrors, 'current_password', 'err-theme-rollback-password') ?> required>
                    </label>
                    <?= field_error($rollbackErrors, 'current_password', 'err-theme-rollback-password') ?>
                    <?= field_error($rollbackErrors, 'theme', 'err-theme-rollback-policy', alert: true) ?>
                    <button class="btn btn-secondary theme-rollback-button" type="submit">Roll back to last-known-good</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card theme-packages-card">
        <h2>Installed theme packages</h2>
        <?php if (($installs ?? []) === []): ?>
            <p class="theme-empty-copy">No theme packages are installed.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Installed theme packages">
        <table class="audit theme-package-table">
            <thead><tr><th scope="col">Package</th><th scope="col">Version</th><th scope="col">State</th><th scope="col">Latest build</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($installs as $install): ?>
                <?php
                $installId = (int) $install['id'];
                $installState = (string) $install['state'];
                $isEnabled = $installState === 'enabled';
                $stateClass = $isEnabled ? 'state-active' : 'state-' . $installState;
                $rowErrorContext = $errorInstallId === $installId ? $errorContext : null;
                $rowErrors = $rowErrorContext !== null ? ($errors ?? []) : [];
                ?>
                <tr>
                    <td class="theme-package-cell"><strong><?= $e($install['package_name']) ?></strong><code><?= $e($install['package_uid']) ?></code></td>
                    <td class="theme-package-version"><?= $e($install['release_version'] ?? '') ?></td>
                    <td><span class="state <?= $e($stateClass) ?>"><?= $e(ucfirst($installState)) ?></span></td>
                    <td>
                        <?php if (($install['latest_build'] ?? null) !== null): ?>
                            <code class="theme-build-digest"><?= $e($install['latest_build']['css_digest']) ?></code>
                        <?php else: ?>
                            <span class="theme-not-built">not built</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-cell">
                        <?php if ($isEnabled): ?>
                            <div class="theme-row-actions">
                                <form method="post" action="/admin/themes/<?= $installId ?>/preview" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-secondary theme-row-button" type="submit">Preview</button>
                                    <?php if ($rowErrorContext === 'preview'): ?>
                                        <?= field_error($rowErrors, 'theme', 'err-theme-preview-policy-' . $installId, alert: true) ?>
                                    <?php endif; ?>
                                </form>

                                <form method="post" action="/admin/themes/<?= $installId ?>/activate" class="theme-activate-form">
                                    <?= $this->csrfField() ?>
                                    <h2 class="theme-activate-title">Activate <?= $e($install['package_name']) ?>?</h2>
                                    <p class="theme-activation-impact">Every visitor sees this theme on their next page. The current build is kept as last-known-good, so safe mode can always restore the built-in chrome.</p>
                                    <label class="reauth-field">
                                        <span>Current password</span>
                                        <input class="input" type="password" name="current_password" autocomplete="current-password"<?= $rowErrorContext === 'activate' ? field_attrs($rowErrors, 'current_password', 'err-theme-activate-password-' . $installId) : '' ?> required>
                                    </label>
                                    <?php if ($rowErrorContext === 'activate'): ?>
                                        <?= field_error($rowErrors, 'current_password', 'err-theme-activate-password-' . $installId) ?>
                                        <?= field_error($rowErrors, 'theme', 'err-theme-activate-policy-' . $installId, alert: true) ?>
                                    <?php endif; ?>
                                    <button class="btn theme-row-button" type="submit">Activate</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <a class="theme-enable-link" href="/admin/packages/<?= (int) $install['package_id'] ?>">Enable it from Packages first</a>
                            <?php if (in_array($rowErrorContext, ['preview', 'activate'], true)): ?>
                                <?= field_error(
                                    $rowErrors,
                                    'current_password',
                                    'err-theme-' . $rowErrorContext . '-password-' . $installId,
                                    alert: true,
                                ) ?>
                                <?= field_error(
                                    $rowErrors,
                                    'theme',
                                    'err-theme-' . $rowErrorContext . '-policy-' . $installId,
                                    alert: true,
                                ) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card theme-preview-card">
        <h2>Preview</h2>
        <?php if (($preview ?? null) === null): ?>
            <p class="theme-empty-copy">No session preview is active.</p>
        <?php else: ?>
            <div class="theme-card-row">
                <p>Previewing <strong><?= $e($preview['package_name']) ?></strong> <code class="theme-preview-digest"><?= $e($preview['css_digest']) ?></code> in this admin session only.</p>
                <form method="post" action="/admin/themes/preview/clear" class="inline-form theme-card-action">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-secondary" type="submit">End preview</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
