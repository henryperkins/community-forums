<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Publisher trust');
$this->section('variant', 'admin');
$pubErrors = $errors ?? [];
$pubOld = $old ?? [];
// Which form actually failed. Without it a refused key revoke repopulates the
// suspend form's reason, because both post a field called "reason".
$failedForm = (string) ($pubOld['form'] ?? '');
$keyFormOpen = !empty($pubErrors['key_id']) || !empty($pubErrors['public_key']);
$rotateFormOpen = !empty($pubErrors['envelope']) || !empty($pubErrors['rotation']);
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'packages', 'pane_class' => 'admin-packages packages-publisher']) ?>
    <a class="admin-back" href="/admin/packages/security">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Security response
    </a>
    <?php // copy: the chips move out of the <h2> so they stop polluting the heading name. ?>
    <div class="packages-card-head">
        <h2 class="admin-record-title"><?= $e($publisher['display_name']) ?></h2>
        <span class="packages-pill is-off"><?= $e($publisher['status']) ?></span>
        <?= $publisher['verified_at'] !== null ? '<span class="packages-pill is-done">verified</span>' : '' ?>
    </div>
    <p class="packages-lead"><code class="packages-uid"><?= $e($publisher['publisher_uid']) ?></code> Trust changes require your password. Suspension force-disables every install of this publisher's packages; reinstatement never silently re-enables them.</p>

    <section class="card packages-panel-card">
        <h3 class="packages-eyebrow">Status</h3>
        <div class="packages-status-forms">
            <?php if ($publisher['status'] !== 'suspended'): ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/suspend" class="inline-form packages-revoke-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="form" value="suspend">
                <input class="packages-secret-input" type="text" name="reason" placeholder="Suspension reason" aria-label="Suspension reason" maxlength="255" value="<?= $failedForm === 'suspend' ? $e($pubOld['reason'] ?? '') : '' ?>" required>
                <input class="packages-secret-input" type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn danger" type="submit">Suspend publisher</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/reinstate" class="inline-form packages-revoke-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="form" value="reinstate">
                <input class="packages-secret-input" type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Reinstate publisher</button>
            </form>
            <?php endif; ?>
            <?php if ($publisher['verified_at'] === null): ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/verify" class="inline-form packages-revoke-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="form" value="verify">
                <input class="packages-secret-input" type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                <button class="btn" type="submit">Verify publisher</button>
            </form>
            <?php endif; ?>
        </div>
        <?= field_error($pubErrors, 'reason', 'err-pub-reason') ?>
        <?= field_error($pubErrors, 'current_password', 'err-pub-current_password') ?>
        <?= field_error($pubErrors, 'publisher', 'err-pub-publisher') ?>
    </section>

    <section class="card packages-table-card">
        <h3 class="packages-eyebrow">Signing keys</h3>
        <div class="table-scroll table-scroll-wide" tabindex="0" role="region" aria-label="Publisher signing keys">
        <table class="audit packages-table registry-keys-table">
            <thead><tr><th scope="col">Key id</th><th scope="col">Status</th><th scope="col">Window</th><th scope="col">Fingerprint</th><th scope="col" class="packages-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($keys as $key): ?>
                <tr>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e($key['key_id']) ?></code></td>
                    <td><?= $e($key['status']) ?><?= $key['status'] === 'revoked' ? ' &mdash; ' . ($key['revoked_reason'] !== null ? $e($key['revoked_reason']) : 'no reason recorded') : '' ?></td>
                    <td class="packages-col-nowrap"><span class="packages-mono"><?= $e($key['valid_from'] ?? 'inf') ?> &rarr; <?= $e($key['valid_until'] ?? 'inf') ?></span></td>
                    <td class="packages-col-nowrap"><code class="packages-mono"><?= $e(substr(hash('sha256', (string) $key['public_key']), 0, 16)) ?></code></td>
                    <td class="packages-col-actions">
                        <?php if ($key['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/publisher-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form packages-revoke-form">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="form" value="revoke_key">
                            <input type="hidden" name="revoke_key_id" value="<?= (int) $key['id'] ?>">
                            <input class="packages-secret-input" type="text" name="reason" placeholder="Revocation reason" aria-label="Revocation reason for key <?= $e($key['key_id']) ?>" maxlength="255" value="<?= ($failedForm === 'revoke_key' && (int) ($pubOld['revoke_key_id'] ?? 0) === (int) $key['id']) ? $e($pubOld['reason'] ?? '') : '' ?>" required>
                            <input class="packages-secret-input" type="password" name="current_password" placeholder="Your password" aria-label="Your current password" autocomplete="current-password" required>
                            <button class="btn btn-small danger" type="submit">Revoke</button>
                        </form>
                        <?php else: ?>
                            <span class="packages-none">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($keys === []): ?><tr><td colspan="5" class="packages-empty">No signing keys pinned.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php // a11y: a disclosure that owns an error must re-open on 422, or the operator
              // is told something failed and cannot see what. ?>
        <details class="packages-disclosure"<?= $keyFormOpen ? ' open' : '' ?>>
            <summary>Pin a new public key</summary>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/keys" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Key id</span><input class="input" type="text" name="key_id" maxlength="190" value="<?= $e($pubOld['key_id'] ?? '') ?>"<?= field_attrs($pubErrors, 'key_id') ?> required></label>
                <?= field_error($pubErrors, 'key_id') ?>
                <label class="field"><span>Public key (base64, 32 bytes)</span><input class="input" type="text" name="public_key" value="<?= $e($pubOld['public_key'] ?? '') ?>"<?= field_attrs($pubErrors, 'public_key') ?> required></label>
                <?= field_error($pubErrors, 'public_key') ?>
                <label class="field"><span>Valid from (UTC, optional)</span><input class="input" type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <label class="field"><span>Valid until (UTC, optional)</span><input class="input" type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <label class="reauth-field"><span>Your password</span><input class="packages-secret-input" type="password" name="current_password" aria-label="Your current password" autocomplete="current-password" required></label>
                <button class="btn" type="submit">Pin key</button>
            </form>
        </details>

        <details class="packages-disclosure"<?= $rotateFormOpen ? ' open' : '' ?>>
            <summary>Apply a signed key rotation</summary>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Rotation envelope (JSON)</span><textarea class="input" name="envelope" rows="4" placeholder='{"document":"...","signature":"&lt;base64&gt;","key_id":"..."}'><?= $rotateFormOpen ? $e($pubOld['envelope'] ?? '') : '' ?></textarea></label>
                <?= field_error($pubErrors, 'envelope') ?>
                <?= field_error($pubErrors, 'rotation') ?>
                <label class="reauth-field"><span>Your password</span><input class="packages-secret-input" type="password" name="current_password" aria-label="Your current password" autocomplete="current-password" required></label>
                <button class="btn" type="submit">Apply rotation</button>
            </form>
        </details>
    </section>

    <section class="card packages-panel-card">
        <h3 class="packages-eyebrow">Packages &amp; review decisions</h3>
        <?php foreach ($packages as $package): ?>
            <div class="packages-card-head">
                <code class="packages-rule-id"><?= $e($package['package_uid']) ?></code>
                <span class="packages-pill is-off"><?= $e($package['advisory_status']) ?></span>
            </div>
            <ul class="packages-rule-list">
            <?php foreach ($package['decisions'] as $decision): ?>
                <li class="packages-rule-row">
                    <span class="packages-rule-main">
                        <span class="packages-rule-label"><?= $e($decision['decision']) ?></span>
                        <code class="packages-rule-id"><?= $e(substr((string) $decision['digest'], 0, 12)) ?></code>
                    </span>
                    <span class="packages-rule-meta"><?= $e($decision['source']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if ($package['decisions'] === []): ?><li class="packages-rule-row"><span class="packages-empty">No review decisions recorded.</span></li><?php endif; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ($packages === []): ?><p class="packages-empty">This publisher owns no packages.</p><?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
