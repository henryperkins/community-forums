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
<?php // This surface has no design representation anywhere in the mirror — restyle
      // only, no restructuring. #integration, .integration-actions and .reveal are
      // Playwright-pinned names and stay byte-identical. ?>
<section class="card packages-panel-card packages-integration-card" id="integration">
    <h2 class="packages-section-title is-md">Integration</h2>
    <p class="packages-lead">
        This package <?= ($integration['type'] ?? '') === 'remote_app' ? 'runs remotely' : 'runs declaratively' ?>.
        RetroBoards never executes package code in-process &mdash; it only exchanges the data these grants allow,
        through the read-only API and package-owned webhooks below.
    </p>

    <?php if (!empty($integration['execution_disabled'])): ?>
        <p class="callout callout-danger packages-refusal" role="alert">Package execution is emergency-disabled site-wide. Credentials cannot authenticate and delivery is paused until an operator re-enables execution.</p>
    <?php endif; ?>
    <?php if (($integration['refusal'] ?? null) !== null): ?>
        <p class="callout callout-danger packages-refusal" role="alert"><?= $e($integration['refusal']['code'] . ': ' . $integration['refusal']['message']) ?></p>
    <?php endif; ?>

    <h3 class="packages-eyebrow">Granted permissions</h3>
    <dl class="packages-facts">
        <div><dt class="packages-fact-term">API scopes</dt><dd class="packages-fact-value"><?= ($integration['granted_scopes'] ?? []) ? $e(implode(', ', $integration['granted_scopes'])) : 'none' ?></dd></div>
        <div><dt class="packages-fact-term">Webhook events</dt><dd class="packages-fact-value"><?= ($integration['granted_events'] ?? []) ? $e(implode(', ', $integration['granted_events'])) : 'none' ?></dd></div>
        <div><dt class="packages-fact-term">Outbound hosts</dt><dd class="packages-fact-value"><?= ($integration['outbound_hosts'] ?? []) ? $e(implode(', ', $integration['outbound_hosts'])) : 'none' ?></dd></div>
        <div><dt class="packages-fact-term">Data classes</dt><dd class="packages-fact-value"><?= $classList ? $e(implode(', ', array_filter($classList))) : 'none' ?></dd></div>
        <div><dt class="packages-fact-term">Jobs (consent metadata only)</dt><dd class="packages-fact-value"><?= $jobList ? $e(implode(', ', array_filter($jobList))) : 'none' ?></dd></div>
    </dl>

    <h3 class="packages-eyebrow">Settings</h3>
    <?php if (empty($settings['fields'])): ?>
        <p class="packages-empty">This package declares no configurable settings.</p>
    <?php else: ?>
    <form method="post" action="<?= $e($base) ?>/integration/settings" class="stacked">
        <?= $this->csrfField() ?>
        <?php foreach ($settings['fields'] as $field): $key = (string) $field['key']; ?>
            <label class="field">
                <span><?= $e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></span>
                <?php if (($field['type'] ?? '') === 'select'): ?>
                    <select class="input" name="<?= $e($key) ?>">
                        <?php foreach (($field['options'] ?? []) as $opt): ?>
                            <option value="<?= $e($opt) ?>"<?= (string) ($settings['values'][$key] ?? '') === (string) $opt ? ' selected' : '' ?>><?= $e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (!empty($field['secret'])): ?>
                    <input class="input" type="password" name="<?= $e($key) ?>" autocomplete="new-password"
                           placeholder="<?= !empty($settings['has_secret'][$key]) ? 'stored — leave blank to keep' : 'not set' ?>">
                <?php else: ?>
                    <input class="input" type="text" name="<?= $e($key) ?>" value="<?= $e((string) ($settings['values'][$key] ?? '')) ?>">
                <?php endif; ?>
            </label>
            <?php if (isset($errors[$key])): ?><p class="field-error"><?= $e($errors[$key]) ?></p><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($hasSecretField): ?>
            <label class="reauth-field"><span>Confirm your password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"></label>
            <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
        <?php endif; ?>
        <button class="btn btn-small" type="submit">Save settings</button>
    </form>
    <?php endif; ?>

    <h3 class="packages-eyebrow">Package-owned credentials</h3>
    <?php if ($reveal !== null): ?>
        <div class="card reveal">
            <p><strong>Copy these now &mdash; they are shown only once.</strong></p>
            <?php if (!empty($reveal['api_token'])): ?><p>API token: <code class="packages-mono"><?= $e($reveal['api_token']) ?></code></p><?php endif; ?>
            <?php if (!empty($reveal['webhook_secret'])): ?><p>Webhook signing secret: <code class="packages-mono"><?= $e($reveal['webhook_secret']) ?></code></p><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php foreach (['settings', 'provision', 'rotate', 'revoke'] as $slot): ?>
        <?php if (isset($errors[$slot])): ?><p class="callout callout-danger packages-refusal" role="alert"><?= $e($errors[$slot]) ?></p><?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($integration['credentials'])): ?>
        <p class="packages-empty">No credentials provisioned.</p>
    <?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Package credentials">
    <table class="audit packages-table packages-credentials-table">
        <thead><tr><th scope="col">Label</th><th scope="col">Kind</th><th scope="col">Status</th><th scope="col">Scopes / events</th><th scope="col" class="packages-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($integration['credentials'] as $cred): ?>
            <tr>
                <td><?= $e($cred['label']) ?></td>
                <td class="packages-col-nowrap"><?= $e($cred['kind']) ?></td>
                <td class="packages-col-nowrap"><?= $e($cred['status']) ?></td>
                <td><?= $e(implode(', ', $cred['scopes'] ?: $cred['events'])) ?></td>
                <td class="packages-col-actions">
                    <?php if ($cred['status'] !== 'revoked'): ?>
                        <form method="post" action="<?= $e($base) ?>/integration/credentials/<?= (int) $cred['id'] ?>/rotate" class="inline-form packages-revoke-form">
                            <?= $this->csrfField() ?>
                            <input class="packages-secret-input" type="password" name="current_password" placeholder="password" aria-label="Your current password" autocomplete="current-password">
                            <button class="btn btn-small" type="submit" aria-label="Rotate credential #<?= (int) $cred['id'] ?>">Rotate</button>
                        </form>
                        <form method="post" action="<?= $e($base) ?>/integration/credentials/<?= (int) $cred['id'] ?>/revoke" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-small danger" type="submit" aria-label="Revoke credential #<?= (int) $cred['id'] ?>">Revoke</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php // constraint: package-integrations.spec.ts selects
          // #integration .integration-actions input[name="current_password"], so the
          // Provision form's password must stay inside this container. ?>
    <div class="integration-actions">
        <?php if (!empty($integration['integrable']) && ($integration['refusal'] ?? null) === null && empty($integration['execution_disabled'])): ?>
        <form method="post" action="<?= $e($base) ?>/integration/provision" class="inline-form packages-action-form">
            <?= $this->csrfField() ?>
            <label class="reauth-field"><span>Confirm password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"></label>
            <button class="btn btn-small" type="submit">Provision credentials</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= $e($base) ?>/integration/disable" class="inline-form">
            <?= $this->csrfField() ?>
            <button class="btn btn-small btn-secondary" type="submit">Pause delivery</button>
        </form>
        <form method="post" action="<?= $e($base) ?>/integration/export" class="inline-form">
            <?= $this->csrfField() ?>
            <button class="btn btn-small btn-ghost" type="submit">Export settings</button>
        </form>
    </div>
</section>
