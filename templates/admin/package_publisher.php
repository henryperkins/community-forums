<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Publisher trust');
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($publisher['display_name']) ?>
            <span class="pill"><?= $e($publisher['status']) ?></span>
            <?= $publisher['verified_at'] !== null ? '<span class="pill">verified</span>' : '' ?>
        </h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'registries', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted"><code><?= $e($publisher['publisher_uid']) ?></code>. Trust changes require your password. Suspension force-disables every install of this publisher's packages; reinstatement never silently re-enables them.</p>

    <section class="card">
        <h2>Status</h2>
        <div class="form-cell">
            <?php if ($publisher['status'] !== 'suspended'): ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/suspend" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="text" name="reason" placeholder="Suspension reason" aria-label="Suspension reason" maxlength="255" value="<?= $e($old['reason'] ?? '') ?>" required>
                <input type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Suspend publisher</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/reinstate" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Reinstate publisher</button>
            </form>
            <?php endif; ?>
            <?php if ($publisher['verified_at'] === null): ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/verify" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Verify publisher</button>
            </form>
            <?php endif; ?>
        </div>
        <?= field_error($errors ?? [], 'reason', 'err-pub-reason') ?>
        <?= field_error($errors ?? [], 'current_password', 'err-pub-current_password') ?>
        <?= field_error($errors ?? [], 'publisher', 'err-pub-publisher') ?>
    </section>

    <section class="card">
        <h2>Signing keys</h2>
        <div class="table-scroll table-scroll-wide" tabindex="0" role="region" aria-label="Publisher signing keys">
        <table class="audit">
            <thead><tr><th scope="col">Key id</th><th scope="col">Status</th><th scope="col">Window</th><th scope="col">Fingerprint</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($keys as $key): ?>
                <tr>
                    <td class="nowrap"><code><?= $e($key['key_id']) ?></code></td>
                    <td><?= $e($key['status']) ?><?= $key['revoked_reason'] !== null ? ' - ' . $e($key['revoked_reason']) : '' ?></td>
                    <td><?= $e($key['valid_from'] ?? 'inf') ?> to <?= $e($key['valid_until'] ?? 'inf') ?></td>
                    <td class="nowrap"><code><?= $e(substr(hash('sha256', (string) $key['public_key']), 0, 16)) ?></code></td>
                    <td class="form-cell">
                        <?php if ($key['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/publisher-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" placeholder="Revocation reason" aria-label="Revocation reason for key <?= $e($key['key_id']) ?>" maxlength="255" required>
                            <input type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                            <button class="btn" type="submit">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($keys === []): ?><tr><td colspan="5" class="muted">No signing keys pinned.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <details>
            <summary>Pin a new public key</summary>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/keys" class="stacked">
                <?= $this->csrfField() ?>
                <label>Key id <input type="text" name="key_id" maxlength="190" value="<?= $e($old['key_id'] ?? '') ?>"<?= field_attrs($errors ?? [], 'key_id') ?> required></label>
                <?= field_error($errors ?? [], 'key_id') ?>
                <label>Public key (base64, 32 bytes) <input type="text" name="public_key" value="<?= $e($old['public_key'] ?? '') ?>"<?= field_attrs($errors ?? [], 'public_key') ?> required></label>
                <?= field_error($errors ?? [], 'public_key') ?>
                <label>Valid from (UTC, optional) <input type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <label>Valid until (UTC, optional) <input type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <input type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Pin key</button>
            </form>
        </details>

        <details>
            <summary>Apply a signed key rotation</summary>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <label>Rotation envelope (JSON) <textarea name="envelope" rows="4" placeholder='{"document":"...","signature":"&lt;base64&gt;","key_id":"..."}'><?= $e($old['envelope'] ?? '') ?></textarea></label>
                <?= field_error($errors ?? [], 'envelope') ?>
                <?= field_error($errors ?? [], 'rotation') ?>
                <input type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Apply rotation</button>
            </form>
        </details>
    </section>

    <section class="card">
        <h2>Packages &amp; review decisions</h2>
        <?php foreach ($packages as $package): ?>
            <h3><code><?= $e($package['package_uid']) ?></code> <span class="pill"><?= $e($package['advisory_status']) ?></span></h3>
            <ul>
            <?php foreach ($package['decisions'] as $decision): ?>
                <li><?= $e($decision['decision']) ?> — <code><?= $e(substr((string) $decision['digest'], 0, 12)) ?></code> (<?= $e($decision['source']) ?>)</li>
            <?php endforeach; ?>
            <?php if ($package['decisions'] === []): ?><li class="muted">No review decisions recorded.</li><?php endif; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ($packages === []): ?><p class="muted">This publisher owns no packages.</p><?php endif; ?>
    </section>
    </div>
</div>
