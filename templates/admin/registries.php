<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Registry trust');
$this->section('variant', 'admin');
$formState = static function (string $action, int $target = 0) use ($form_errors, $form_old): array {
    $context = $action . '-' . $target;
    return [$form_errors[$context] ?? [], $form_old[$context] ?? [], 'registry-' . $context];
};
$formAlerts = static function (array $errors, array $fields, string $id): string {
    $html = '';
    foreach (array_diff_key($errors, array_fill_keys($fields, true)) as $field => $_) {
        $html .= field_error($errors, $field, 'err-' . $id . '-' . $field, true);
    }
    return $html;
};
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'registries', 'pane_class' => 'admin-packages packages-registries']) ?>
    <?php foreach ($page_errors as $field => $_): ?>
        <?= field_error($page_errors, $field, 'err-registry-page-' . $field, true) ?>
    <?php endforeach; ?>
    <p class="pane-intro packages-lead">The private signing root lives offline with the operator; this console pins, rotates, and revokes public keys only. Trust changes require your password. The local blocklist works regardless of registry state.</p>

    <?php if (empty($registries)): ?>
        <p class="packages-empty">No registry sources are configured yet. Add one below &mdash; it starts disabled until you enable it with your password.</p>
    <?php endif; ?>

    <?php foreach ($registries as $reg): ?>
    <section class="card packages-registry-card">
        <?php // copy: the chip moves out of the <h2> and the enable/disable toggle moves
              // up into the header row, right-aligned, as the design has it. ?>
        <div class="packages-card-head">
            <h2 class="packages-section-title is-md"><?= $e($reg['display_name']) ?></h2>
            <code class="packages-uid"><?= $e($reg['source_id']) ?></code>
            <?= ((int) $reg['is_enabled']) === 1 ? '<span class="state state-done">enabled</span>' : '<span class="state state-muted">disabled</span>' ?>
            <?php // feature-added: disabling takes no password, enabling requires one. The
                  // asymmetry is deliberate (the safe direction is free) and is now evident
                  // from the absent field rather than a parenthetical in the button. ?>
            <?php [$regErrors, $regOld, $formId] = $formState('enable', (int) $reg['id']); ?>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/enabled" class="inline-form registry-toggle-form">
                <?= $this->csrfField() ?>
                <?php if (((int) $reg['is_enabled']) === 1): ?>
                    <input type="hidden" name="enabled" value="0">
                    <button class="btn btn-small btn-secondary" type="submit">Disable registry</button>
                <?php else: ?>
                    <input type="hidden" name="enabled" value="1">
                    <label class="sr-only" for="reg-enable-password-<?= (int) $reg['id'] ?>">Confirm your password to enable <?= $e($reg['display_name']) ?></label>
                    <input class="packages-secret-input" type="password" id="reg-enable-password-<?= (int) $reg['id'] ?>" name="current_password" placeholder="Your password" autocomplete="current-password"<?= field_attrs($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?> required>
                    <button class="btn btn-small" type="submit">Enable registry</button>
                <?php endif; ?>
                <?= field_error($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?>
                <?= $formAlerts($regErrors, ['current_password'], $formId) ?>
            </form>
        </div>
        <p class="registry-snapshot"><?= $e($reg['base_url']) ?>.
            <?php if ($reg['latest_snapshot'] !== null): ?>
                Last verified snapshot <?= $e($reg['latest_snapshot']['generated_at']) ?> UTC; expires <?= $e($reg['latest_snapshot']['expires_at']) ?> UTC.
            <?php else: ?>No verified snapshot yet.<?php endif; ?></p>

        <div class="table-scroll table-scroll-wide" tabindex="0" role="region" aria-label="<?= $e('Signing keys for ' . $reg['display_name']) ?>">
        <table class="audit packages-table registry-keys-table">
            <thead><tr><th scope="col">Key id</th><th scope="col">Status</th><th scope="col">Window</th><th scope="col">Fingerprint</th><th scope="col" class="packages-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($reg['keys'] as $key): ?>
                <tr>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e($key['key_id']) ?></code></td>
                    <td><?= $e($key['status']) ?><?= $key['status'] === 'revoked' ? ' &mdash; ' . ($key['revoked_reason'] !== null ? $e($key['revoked_reason']) : 'no reason recorded') : '' ?></td>
                    <td class="packages-col-nowrap"><span class="packages-mono"><?= $e($key['valid_from'] ?? 'inf') ?> &rarr; <?= $e($key['valid_until'] ?? 'inf') ?></span></td>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e(substr(hash('sha256', (string) $key['public_key']), 0, 16)) ?></code></td>
                    <td class="packages-col-actions">
                        <?php if ($key['status'] !== 'revoked'): ?>
                        <?php // feature-changed: the design offers a bare one-click Revoke.
                              // Revocation needs a reason and a password; both stay. ?>
                        <?php [$keyErrors, $keyOld, $keyFormId] = $formState('revoke', (int) $key['id']); ?>
                        <form method="post" action="/admin/registry-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form packages-revoke-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="revoke-reason-<?= (int) $key['id'] ?>">Revocation reason for key <?= $e($key['key_id']) ?></label>
                            <input class="packages-secret-input" type="text" id="revoke-reason-<?= (int) $key['id'] ?>" name="reason" placeholder="Revocation reason" value="<?= $e($keyOld['reason'] ?? '') ?>"<?= field_attrs($keyErrors, 'reason', 'err-' . $keyFormId . '-reason') ?> required>
                            <label class="sr-only" for="revoke-password-<?= (int) $key['id'] ?>">Your password to revoke key <?= $e($key['key_id']) ?></label>
                            <input class="packages-secret-input" type="password" id="revoke-password-<?= (int) $key['id'] ?>" name="current_password" placeholder="Your password" autocomplete="current-password"<?= field_attrs($keyErrors, 'current_password', 'err-' . $keyFormId . '-current_password') ?> required>
                            <button class="btn btn-small danger" type="submit">Revoke</button>
                            <?= field_error($keyErrors, 'reason', 'err-' . $keyFormId . '-reason') ?>
                            <?= field_error($keyErrors, 'current_password', 'err-' . $keyFormId . '-current_password') ?>
                            <?= $formAlerts($keyErrors, ['reason', 'current_password'], $keyFormId) ?>
                        </form>
                        <?php else: ?>
                            <span class="packages-none">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($reg['keys'] ?? []) === []): ?>
                <tr><td colspan="5" class="packages-empty">No signing keys pinned for this registry.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php [$regErrors, $regOld, $formId] = $formState('pin', (int) $reg['id']); ?>
        <details class="packages-disclosure"<?= $regErrors !== [] ? ' open' : '' ?>>
            <summary>Pin a new public key</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/keys" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Key id</span><input class="input" type="text" name="key_id" maxlength="190" value="<?= $e($regOld['key_id'] ?? '') ?>"<?= field_attrs($regErrors, 'key_id', 'err-' . $formId . '-key_id') ?> required></label>
                <?= field_error($regErrors, 'key_id', 'err-' . $formId . '-key_id') ?>
                <label class="field"><span>Public key (base64, 32 bytes)</span><input class="input" type="text" name="public_key" value="<?= $e($regOld['public_key'] ?? '') ?>"<?= field_attrs($regErrors, 'public_key', 'err-' . $formId . '-public_key') ?> required></label>
                <?= field_error($regErrors, 'public_key', 'err-' . $formId . '-public_key') ?>
                <label class="field"><span>Valid from (UTC, optional)</span><input class="input" type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($regOld['valid_from'] ?? '') ?>"<?= field_attrs($regErrors, 'valid_from', 'err-' . $formId . '-valid_from') ?>></label>
                <label class="field"><span>Valid until (UTC, optional)</span><input class="input" type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($regOld['valid_until'] ?? '') ?>"<?= field_attrs($regErrors, 'valid_until', 'err-' . $formId . '-valid_until') ?>></label>
                <?= field_error($regErrors, 'valid_from', 'err-' . $formId . '-valid_from') ?>
                <?= field_error($regErrors, 'valid_until', 'err-' . $formId . '-valid_until') ?>
                <label class="reauth-field"><span>Confirm your password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?> required></label>
                <?= field_error($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?>
                <?= $formAlerts($regErrors, ['key_id', 'public_key', 'valid_from', 'valid_until', 'current_password'], $formId) ?>
                <div class="form-actions"><button class="btn" type="submit">Pin key</button></div>
            </form>
        </details>

        <?php [$regErrors, $regOld, $formId] = $formState('rotate', (int) $reg['id']); ?>
        <details class="packages-disclosure"<?= $regErrors !== [] ? ' open' : '' ?>>
            <summary>Apply a signed key rotation</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <?php // HTML consumes the first literal newline in a textarea; preserve the member's leading newline after it. ?>
                <label class="field"><span>Rotation envelope JSON</span><textarea class="input" name="envelope" rows="4"<?= field_attrs($regErrors, 'envelope', 'err-' . $formId . '-envelope') ?> required>
<?= $e($regOld['envelope'] ?? '') ?></textarea></label>
                <?= field_error($regErrors, 'envelope', 'err-' . $formId . '-envelope') ?>
                <label class="reauth-field"><span>Confirm your password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?> required></label>
                <?= field_error($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?>
                <?= $formAlerts($regErrors, ['envelope', 'current_password'], $formId) ?>
                <div class="form-actions"><button class="btn" type="submit">Apply rotation</button></div>
            </form>
        </details>

        <?php [$regErrors, $regOld, $formId] = $formState('advisory', (int) $reg['id']); ?>
        <details class="packages-disclosure"<?= $regErrors !== [] ? ' open' : '' ?>>
            <summary>Ingest a signed advisory manually</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/advisories" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Advisory envelope JSON</span><textarea class="input" name="envelope" rows="4"<?= field_attrs($regErrors, 'envelope', 'err-' . $formId . '-envelope') ?> required>
<?= $e($regOld['envelope'] ?? '') ?></textarea></label>
                <?= field_error($regErrors, 'envelope', 'err-' . $formId . '-envelope') ?>
                <label class="reauth-field"><span>Confirm your password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?> required></label>
                <?= field_error($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?>
                <?= $formAlerts($regErrors, ['envelope', 'current_password'], $formId) ?>
                <div class="form-actions"><button class="btn" type="submit">Ingest advisory</button></div>
            </form>
        </details>
    </section>
    <?php endforeach; ?>

    <section class="card packages-record-card">
        <h3 class="packages-eyebrow">Add a registry source</h3>
        <?php [$regErrors, $regOld, $formId] = $formState('create'); ?>
        <form method="post" action="/admin/registries" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field"><span>Source id</span><input class="input" type="text" name="source_id" maxlength="190" value="<?= $e($regOld['source_id'] ?? '') ?>"<?= field_attrs($regErrors, 'source_id', 'err-' . $formId . '-source_id') ?> required></label>
            <?= field_error($regErrors, 'source_id', 'err-' . $formId . '-source_id') ?>
            <label class="field"><span>Display name</span><input class="input" type="text" name="display_name" maxlength="190" value="<?= $e($regOld['display_name'] ?? '') ?>"<?= field_attrs($regErrors, 'display_name', 'err-' . $formId . '-display_name') ?> required></label>
            <?= field_error($regErrors, 'display_name', 'err-' . $formId . '-display_name') ?>
            <label class="field"><span>Base URL</span><input class="input" type="url" name="base_url" maxlength="512" value="<?= $e($regOld['base_url'] ?? '') ?>"<?= field_attrs($regErrors, 'base_url', 'err-' . $formId . '-base_url') ?> required></label>
            <?= field_error($regErrors, 'base_url', 'err-' . $formId . '-base_url') ?>
            <label class="reauth-field"><span>Confirm your password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?> required></label>
            <?= field_error($regErrors, 'current_password', 'err-' . $formId . '-current_password') ?>
            <?= $formAlerts($regErrors, ['source_id', 'display_name', 'base_url', 'current_password'], $formId) ?>
            <div class="form-actions"><button class="btn" type="submit">Add registry (starts disabled)</button></div>
        </form>
    </section>

    <section class="card packages-panel-card">
        <h2 class="packages-section-title is-md">Local blocklist (registry-independent)</h2>
        <?php if (empty($blocks)): ?>
            <p class="packages-empty">Nothing is locally blocked.</p>
        <?php else: ?>
        <ul class="packages-rule-list">
            <?php foreach ($blocks as $block): ?>
                <li class="packages-rule-row packages-block-row">
                    <span class="packages-rule-main">
                        <code class="packages-rule-id"><?= $block['package_uid'] !== null ? $e($block['package_uid']) : $e(substr((string) $block['digest'], 0, 16)) . '…' ?></code>
                        <?php if (($block['reason'] ?? '') !== ''): ?><span class="packages-rule-detail"><?= $e((string) $block['reason']) ?></span><?php endif; ?>
                    </span>
                    <?php // Removing a block re-enables a package, so it re-auths. ?>
                    <?php [$blockErrors, $blockOld, $blockFormId] = $formState('unblock', (int) $block['id']); ?>
                    <form class="inline-form packages-revoke-form" method="post" action="/admin/blocklist/<?= (int) $block['id'] ?>/remove">
                        <?= $this->csrfField() ?>
                        <label class="sr-only" for="block-remove-password-<?= (int) $block['id'] ?>">Your password to remove this block</label>
                        <input class="packages-secret-input" type="password" id="block-remove-password-<?= (int) $block['id'] ?>" name="current_password" placeholder="Your password" autocomplete="current-password"<?= field_attrs($blockErrors, 'current_password', 'err-' . $blockFormId . '-current_password') ?> required>
                        <button class="btn btn-small" type="submit">Remove</button>
                        <?= field_error($blockErrors, 'current_password', 'err-' . $blockFormId . '-current_password') ?>
                        <?= $formAlerts($blockErrors, ['current_password'], $blockFormId) ?>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php [$regErrors, $regOld, $formId] = $formState('block'); ?>
        <form method="post" action="/admin/blocklist" class="stacked">
            <p class="packages-section-caption">Blocking takes effect immediately and needs no password; removing a block does.</p>
            <?= $this->csrfField() ?>
            <label class="field"><span>Release digest (sha256 hex, optional)</span><input class="input" type="text" name="digest" value="<?= $e($regOld['digest'] ?? '') ?>"<?= field_attrs($regErrors, 'digest', 'err-' . $formId . '-digest') ?>></label>
            <?= field_error($regErrors, 'digest', 'err-' . $formId . '-digest') ?>
            <label class="field"><span>Package uid (optional)</span><input class="input" type="text" name="package_uid" value="<?= $e($regOld['package_uid'] ?? '') ?>"<?= field_attrs($regErrors, 'package_uid', 'err-' . $formId . '-package_uid') ?>></label>
            <?= field_error($regErrors, 'package_uid', 'err-' . $formId . '-package_uid') ?>
            <label class="field"><span>Reason (optional)</span><input class="input" type="text" name="reason" maxlength="255" value="<?= $e($regOld['reason'] ?? '') ?>"<?= field_attrs($regErrors, 'reason', 'err-' . $formId . '-reason') ?>></label>
            <?= field_error($regErrors, 'reason', 'err-' . $formId . '-reason') ?>
            <?= $formAlerts($regErrors, ['digest', 'package_uid', 'reason'], $formId) ?>
            <div class="form-actions"><button class="btn" type="submit">Block now</button></div>
        </form>
    </section>

    <section class="card packages-panel-card">
        <h3 class="packages-eyebrow">Advisories</h3>
        <?php if ($advisories === []): ?>
            <p class="packages-empty">None ingested.</p>
        <?php else: ?>
        <ul class="packages-rule-list">
            <?php foreach ($advisories as $a): ?>
                <li class="packages-rule-row">
                    <span class="packages-rule-main">
                        <code class="packages-rule-id"><?= $e($a['advisory_uid']) ?></code>
                        <span class="packages-rule-detail"><?= $a['package_uid'] !== null ? $e($a['package_uid']) : 'unresolved' ?> · <?= $e($a['severity']) ?> · action: <?= $e($a['action']) ?></span>
                    </span>
                    <?php if ($a['acknowledged_at'] === null): ?>
                        <?php [$advisoryErrors, $advisoryOld, $advisoryFormId] = $formState('ack', (int) $a['id']); ?>
                        <form method="post" action="/admin/advisories/<?= (int) $a['id'] ?>/ack" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-small packages-textbtn" type="submit">Acknowledge</button>
                            <?= $formAlerts($advisoryErrors, [], $advisoryFormId) ?>
                        </form>
                    <?php else: ?>
                        <span class="packages-rule-meta">acknowledged <?= $e($a['acknowledged_at']) ?> UTC</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
