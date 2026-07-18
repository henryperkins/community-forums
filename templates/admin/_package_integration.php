<?php /** @var \App\Core\View $this */ ?>
<?php
/** @var array<string,mixed> $integration */
/** @var array<string,mixed> $settings */
/** @var array<string,mixed>|null $reveal */
/** @var array<string,string> $errors */
/** @var string $base */
$reveal = $reveal ?? null;
$errors = $errors ?? [];
$hasSecretField = false;
foreach (($settings['fields'] ?? []) as $f) {
    if (!empty($f['secret'])) {
        $hasSecretField = true;
    }
}
$classList = array_map(
    static fn (array $d): string => (string) ($d['permission_key'] ?? $d['key'] ?? $d['label'] ?? ''),
    $integration['data_classes'] ?? [],
);
$jobList = array_map(
    static fn (array $j): string => (string) ($j['permission_key'] ?? $j['key'] ?? $j['label'] ?? ''),
    $integration['jobs'] ?? [],
);
?>
<section class="card" id="integration">
    <h2>Integration</h2>
    <p class="muted">
        This package <?= ($integration['type'] ?? '') === 'remote_app' ? 'runs remotely' : 'runs declaratively' ?>.
        RetroBoards never executes package code in-process — it only exchanges the data these grants allow,
        through the read-only API and package-owned webhooks below.
    </p>

    <?php if (!empty($integration['execution_disabled'])): ?>
        <p class="field-error">Package execution is emergency-disabled site-wide. Credentials cannot authenticate and delivery is paused until an operator re-enables execution.</p>
    <?php endif; ?>
    <?php if (($integration['refusal'] ?? null) !== null): ?>
        <p class="field-error"><?= $e($integration['refusal']['code'] . ': ' . $integration['refusal']['message']) ?></p>
    <?php endif; ?>

    <h3>Granted permissions</h3>
    <table class="audit">
        <tbody>
            <tr><th>API scopes</th><td><?= ($integration['granted_scopes'] ?? []) ? $e(implode(', ', $integration['granted_scopes'])) : 'none' ?></td></tr>
            <tr><th>Webhook events</th><td><?= ($integration['granted_events'] ?? []) ? $e(implode(', ', $integration['granted_events'])) : 'none' ?></td></tr>
            <tr><th>Outbound hosts</th><td><?= ($integration['outbound_hosts'] ?? []) ? $e(implode(', ', $integration['outbound_hosts'])) : 'none' ?></td></tr>
            <tr><th>Data classes</th><td><?= $classList ? $e(implode(', ', array_filter($classList))) : 'none' ?></td></tr>
            <tr><th>Jobs (consent metadata only)</th><td><?= $jobList ? $e(implode(', ', array_filter($jobList))) : 'none' ?></td></tr>
        </tbody>
    </table>

    <h3>Settings</h3>
    <?php if (empty($settings['fields'])): ?>
        <p class="muted">This package declares no configurable settings.</p>
    <?php else: ?>
    <form method="post" action="<?= $e($base) ?>/integration/settings">
        <?= $this->csrfField() ?>
        <?php foreach ($settings['fields'] as $field): $key = (string) $field['key']; ?>
            <label class="field">
                <span><?= $e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></span>
                <?php if (($field['type'] ?? '') === 'select'): ?>
                    <select name="<?= $e($key) ?>">
                        <?php foreach (($field['options'] ?? []) as $opt): ?>
                            <option value="<?= $e($opt) ?>"<?= (string) ($settings['values'][$key] ?? '') === (string) $opt ? ' selected' : '' ?>><?= $e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (!empty($field['secret'])): ?>
                    <input type="password" name="<?= $e($key) ?>" autocomplete="new-password"
                           placeholder="<?= !empty($settings['has_secret'][$key]) ? 'stored — leave blank to keep' : 'not set' ?>">
                <?php else: ?>
                    <input type="text" name="<?= $e($key) ?>" value="<?= $e((string) ($settings['values'][$key] ?? '')) ?>">
                <?php endif; ?>
            </label>
            <?php if (isset($errors[$key])): ?><p class="field-error"><?= $e($errors[$key]) ?></p><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($hasSecretField): ?>
            <label class="field"><span>Confirm your password</span><input type="password" name="current_password" autocomplete="current-password"></label>
            <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
        <?php endif; ?>
        <button type="submit">Save settings</button>
    </form>
    <?php endif; ?>

    <h3>Package-owned credentials</h3>
    <?php if ($reveal !== null): ?>
        <div class="card reveal">
            <p><strong>Copy these now — they are shown only once.</strong></p>
            <?php if (!empty($reveal['api_token'])): ?><p>API token: <code><?= $e($reveal['api_token']) ?></code></p><?php endif; ?>
            <?php if (!empty($reveal['webhook_secret'])): ?><p>Webhook signing secret: <code><?= $e($reveal['webhook_secret']) ?></code></p><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php foreach (['settings', 'provision', 'rotate', 'revoke'] as $slot): ?>
        <?php if (isset($errors[$slot])): ?><p class="field-error"><?= $e($errors[$slot]) ?></p><?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($integration['credentials'])): ?>
        <p class="muted">No credentials provisioned.</p>
    <?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Package credentials">
    <table class="audit">
        <thead><tr><th>Label</th><th>Kind</th><th>Status</th><th>Scopes / events</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($integration['credentials'] as $cred): ?>
            <tr>
                <td><?= $e($cred['label']) ?></td>
                <td><?= $e($cred['kind']) ?></td>
                <td><?= $e($cred['status']) ?></td>
                <td><?= $e(implode(', ', $cred['scopes'] ?: $cred['events'])) ?></td>
                <td>
                    <?php if ($cred['status'] !== 'revoked'): ?>
                        <form method="post" action="<?= $e($base) ?>/integration/credentials/<?= (int) $cred['id'] ?>/rotate" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="password" name="current_password" placeholder="password" aria-label="Your current password" autocomplete="current-password">
                            <button type="submit" aria-label="Rotate credential #<?= (int) $cred['id'] ?>">Rotate</button>
                        </form>
                        <form method="post" action="<?= $e($base) ?>/integration/credentials/<?= (int) $cred['id'] ?>/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <button type="submit" aria-label="Revoke credential #<?= (int) $cred['id'] ?>">Revoke</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <div class="integration-actions">
        <?php if (!empty($integration['integrable']) && ($integration['refusal'] ?? null) === null && empty($integration['execution_disabled'])): ?>
        <form method="post" action="<?= $e($base) ?>/integration/provision" class="inline">
            <?= $this->csrfField() ?>
            <label class="field"><span>Confirm password</span><input type="password" name="current_password" autocomplete="current-password"></label>
            <button type="submit">Provision credentials</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= $e($base) ?>/integration/disable" class="inline">
            <?= $this->csrfField() ?>
            <button type="submit">Pause delivery</button>
        </form>
        <form method="post" action="<?= $e($base) ?>/integration/export" class="inline">
            <?= $this->csrfField() ?>
            <button type="submit">Export settings</button>
        </form>
    </div>
</section>
