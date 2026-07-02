<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Registry trust');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Registry trust &amp; security response</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/packages">Packages</a>
        <a class="active" href="/admin/registries">Registry trust</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">The private signing root lives offline with the operator; this console pins, rotates, and revokes public keys only. Trust changes require your password. The local blocklist works regardless of registry state.</p>

    <?php foreach ($registries as $reg): ?>
    <section class="card">
        <h2><?= $e($reg['display_name']) ?> <code><?= $e($reg['source_id']) ?></code>
            <?= ((int) $reg['is_enabled']) === 1 ? '<span class="pill">enabled</span>' : '<span class="pill">disabled</span>' ?></h2>
        <p class="muted"><?= $e($reg['base_url']) ?>.
            <?php if ($reg['latest_snapshot'] !== null): ?>
                Last verified snapshot <?= $e($reg['latest_snapshot']['generated_at']) ?> UTC; expires <?= $e($reg['latest_snapshot']['expires_at']) ?> UTC.
            <?php else: ?>No verified snapshot yet.<?php endif; ?></p>

        <div class="table-scroll table-scroll-wide">
        <table class="audit">
            <thead><tr><th>Key id</th><th>Status</th><th>Window</th><th>Fingerprint</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reg['keys'] as $key): ?>
                <tr>
                    <td class="nowrap"><code><?= $e($key['key_id']) ?></code></td>
                    <td><?= $e($key['status']) ?><?= $key['revoked_reason'] !== null ? ' - ' . $e($key['revoked_reason']) : '' ?></td>
                    <td><?= $e($key['valid_from'] ?? 'inf') ?> to <?= $e($key['valid_until'] ?? 'inf') ?></td>
                    <td class="nowrap"><code><?= $e(substr(hash('sha256', (string) $key['public_key']), 0, 16)) ?></code></td>
                    <td class="form-cell">
                        <?php if ($key['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/registry-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" placeholder="Revocation reason" required>
                            <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                            <button class="btn" type="submit">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <details>
            <summary>Pin a new public key</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/keys" class="stacked">
                <?= $this->csrfField() ?>
                <label>Key id <input type="text" name="key_id" maxlength="190" value="<?= $e($old['key_id'] ?? '') ?>" required></label>
                <?php if (!empty($errors['key_id'])): ?><p class="field-error"><?= $e($errors['key_id']) ?></p><?php endif; ?>
                <label>Public key (base64, 32 bytes) <input type="text" name="public_key" value="<?= $e($old['public_key'] ?? '') ?>" required></label>
                <?php if (!empty($errors['public_key'])): ?><p class="field-error"><?= $e($errors['public_key']) ?></p><?php endif; ?>
                <label>Valid from (UTC, optional) <input type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($old['valid_from'] ?? '') ?>"></label>
                <label>Valid until (UTC, optional) <input type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($old['valid_until'] ?? '') ?>"></label>
                <?php if (!empty($errors['valid_from'])): ?><p class="field-error"><?= $e($errors['valid_from']) ?></p><?php endif; ?>
                <?php if (!empty($errors['valid_until'])): ?><p class="field-error"><?= $e($errors['valid_until']) ?></p><?php endif; ?>
                <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
                <div class="form-actions"><button class="btn" type="submit">Pin key</button></div>
            </form>
        </details>

        <details>
            <summary>Apply a signed key rotation</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <label>Rotation envelope JSON
                    <textarea name="envelope" rows="4" required><?= $e($old['envelope'] ?? '') ?></textarea>
                </label>
                <?php if (!empty($errors['envelope'])): ?><p class="field-error"><?= $e($errors['envelope']) ?></p><?php endif; ?>
                <?php if (!empty($errors['rotation'])): ?><p class="field-error"><?= $e($errors['rotation']) ?></p><?php endif; ?>
                <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions"><button class="btn" type="submit">Apply rotation</button></div>
            </form>
        </details>

        <details>
            <summary>Ingest a signed advisory manually</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/advisories" class="stacked">
                <?= $this->csrfField() ?>
                <label>Advisory envelope JSON
                    <textarea name="envelope" rows="4" required><?= $e($old['envelope'] ?? '') ?></textarea>
                </label>
                <?php if (!empty($errors['advisory_envelope'])): ?><p class="field-error"><?= $e($errors['advisory_envelope']) ?></p><?php endif; ?>
                <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions"><button class="btn" type="submit">Ingest advisory</button></div>
            </form>
        </details>

        <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/enabled" class="stacked">
            <?= $this->csrfField() ?>
            <?php if (((int) $reg['is_enabled']) === 1): ?>
                <input type="hidden" name="enabled" value="0">
                <div class="form-actions"><button class="btn" type="submit">Disable registry (no password)</button></div>
            <?php else: ?>
                <input type="hidden" name="enabled" value="1">
                <label>Confirm your password to enable
                    <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions"><button class="btn" type="submit">Enable registry</button></div>
            <?php endif; ?>
        </form>
    </section>
    <?php endforeach; ?>

    <section class="card">
        <h2>Add a registry source</h2>
        <form method="post" action="/admin/registries" class="stacked">
            <?= $this->csrfField() ?>
            <label>Source id <input type="text" name="source_id" maxlength="190" value="<?= $e($old['source_id'] ?? '') ?>" required></label>
            <?php if (!empty($errors['source_id'])): ?><p class="field-error"><?= $e($errors['source_id']) ?></p><?php endif; ?>
            <label>Display name <input type="text" name="display_name" maxlength="190" value="<?= $e($old['display_name'] ?? '') ?>" required></label>
            <?php if (!empty($errors['display_name'])): ?><p class="field-error"><?= $e($errors['display_name']) ?></p><?php endif; ?>
            <label>Base URL <input type="url" name="base_url" maxlength="512" value="<?= $e($old['base_url'] ?? '') ?>" required></label>
            <?php if (!empty($errors['base_url'])): ?><p class="field-error"><?= $e($errors['base_url']) ?></p><?php endif; ?>
            <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Add registry (starts disabled)</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Local blocklist (registry-independent)</h2>
        <div class="table-scroll table-scroll-wide">
        <table class="audit">
            <thead><tr><th>Digest</th><th>Package uid</th><th>Reason</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blocks as $block): ?>
                <tr>
                    <td><?= $block['digest'] !== null ? '<code>' . $e(substr((string) $block['digest'], 0, 16)) . '...</code>' : '-' ?></td>
                    <td><?= $block['package_uid'] !== null ? '<code>' . $e($block['package_uid']) . '</code>' : '-' ?></td>
                    <td><?= $e($block['reason'] ?? '') ?></td>
                    <td class="form-cell">
                        <form method="post" action="/admin/blocklist/<?= (int) $block['id'] ?>/remove" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                            <button class="btn" type="submit">Remove (re-enables)</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <form method="post" action="/admin/blocklist" class="stacked">
            <?= $this->csrfField() ?>
            <label>Release digest (sha256 hex, optional) <input type="text" name="digest" value="<?= $e($old['digest'] ?? '') ?>"></label>
            <?php if (!empty($errors['digest'])): ?><p class="field-error"><?= $e($errors['digest']) ?></p><?php endif; ?>
            <label>Package uid (optional) <input type="text" name="package_uid" value="<?= $e($old['package_uid'] ?? '') ?>"></label>
            <?php if (!empty($errors['target'])): ?><p class="field-error"><?= $e($errors['target']) ?></p><?php endif; ?>
            <label>Reason (optional) <input type="text" name="reason" maxlength="255" value="<?= $e($old['reason'] ?? '') ?>"></label>
            <div class="form-actions"><button class="btn" type="submit">Block now (no password)</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Advisories</h2>
        <?php if ($advisories === []): ?><p class="muted">None ingested.</p><?php else: ?>
        <div class="table-scroll">
        <table class="audit">
            <thead><tr><th>Advisory</th><th>Package</th><th>Severity</th><th>Action</th><th>Acknowledged</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($advisories as $a): ?>
                <tr>
                    <td><code><?= $e($a['advisory_uid']) ?></code></td>
                    <td><?= $a['package_uid'] !== null ? '<code>' . $e($a['package_uid']) . '</code>' : '<span class="muted">unresolved</span>' ?></td>
                    <td><?= $e($a['severity']) ?></td>
                    <td><code><?= $e($a['action']) ?></code></td>
                    <td><?= $a['acknowledged_at'] !== null ? $e($a['acknowledged_at']) . ' UTC' : 'not yet' ?></td>
                    <td>
                        <?php if ($a['acknowledged_at'] === null): ?>
                        <form method="post" action="/admin/advisories/<?= (int) $a['id'] ?>/ack" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="btn" type="submit">Acknowledge</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
    </div>
</div>
