<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Security');
$totp = $totp ?? ['enabled' => false, 'pending' => false, 'unused_recovery_codes' => 0];
$setup = $totp_setup ?? null;
$recoveryCodes = $new_recovery_codes ?? [];
$secErrs = $errors ?? [];
$secCtx = (string) ($error_context ?? '');
/**
 * Five forms on this page carry a current_password field and share one $errors
 * array, so that one key is scoped to the form it came from — otherwise every
 * form would light up and the page would repeat one error id five times. Keys
 * that can only come from a single form (new_password, totp_code, disable_code)
 * need no scoping; totp/recovery are panel-level and have no input to attach to.
 */
$sfattr = function (string $context, string $field) use ($secCtx, $secErrs): string {
    return $secCtx === $context ? field_attrs($secErrs, $field, 'err-' . $context . '-' . $field) : '';
};
$sferr = function (string $context, string $field) use ($secCtx, $secErrs): string {
    return $secCtx === $context ? field_error($secErrs, $field, 'err-' . $context . '-' . $field) : '';
};
/*
 * Not every form that can 422 is still on the page when it does: a wrong
 * password sent to "disable two-factor" is rejected before the not-enabled
 * check, and the panel only renders the disable form WHILE 2FA is enabled — so
 * the scoped error would have nowhere to land. Surface those centrally instead
 * of dropping them, the same orphaned-action problem mod/user.php solves.
 * (Before the errors were scoped this leaked into the password-change form,
 * which showed the message under a field that had nothing to do with it.)
 */
$secFormRendered = match ($secCtx) {
    'password' => true,
    'totp_enroll' => empty($totp['enabled']),
    'totp_confirm' => is_array($setup),
    'totp_rotate', 'totp_disable' => !empty($totp['enabled']),
    default => false,
};
// totp/recovery are excluded: the panel below already owns those two.
$secOrphaned = $secCtx !== '' && !$secFormRendered
    ? array_diff_key($secErrs, array_flip(['totp', 'recovery']))
    : [];
// One autofocus per document — the orphan summary outranks the panel message.
$secPanelFocus = $secOrphaned === [];
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'security']) ?>

        <div class="settings-pane">
    <?php if ($secOrphaned !== []): ?>
        <div class="card notice" role="alert" tabindex="-1" autofocus>
            <?php foreach ($secOrphaned as $secMessage): ?>
                <p class="field-error"><?= $e($secMessage) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="/settings/security" class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Password</h2>
        <?= $this->csrfField() ?>
        <?php // field_error() emits a <p>, which cannot legally nest inside <label>,
              // so each error follows its label — and inside .field-grid a bare
              // sibling would claim its own grid cell, hence the .field-cell wrap. ?>
        <label class="field">
            <span>Current password</span>
            <input type="password" name="current_password" class="input" autocomplete="current-password"<?= $sfattr('password', 'current_password') ?> required>
        </label>
        <?= $sferr('password', 'current_password') ?>
        <?php // copy: the design pairs the new and confirm fields on one row. The
              // strength meter beside them is FR-02 — five tiers ending in a fiction
              // string, over a scorer production does not have. Build nothing. ?>
        <div class="field-grid">
            <div class="field-cell">
                <label class="field">
                    <span>New password</span>
                    <input type="password" name="new_password" class="input" autocomplete="new-password"<?= field_attrs($secErrs, 'new_password') ?> required>
                </label>
                <?= field_error($secErrs, 'new_password') ?>
            </div>
            <div class="field-cell">
                <label class="field">
                    <span>Confirm new password</span>
                    <input type="password" name="new_password_confirm" class="input" autocomplete="new-password"<?= field_attrs($secErrs, 'new_password_confirm') ?> required>
                </label>
                <?= field_error($secErrs, 'new_password_confirm') ?>
            </div>
        </div>
        <button class="btn" type="submit">Change password</button>
    </form>

    <section class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Two-factor authentication</h2>
        <?php if (!empty($totp['enabled'])): ?>
            <p class="totp-state"><span class="totp-state-pill">Enabled</span> <span class="muted"><?= (int) $totp['unused_recovery_codes'] ?> recovery code<?= (int) $totp['unused_recovery_codes'] === 1 ? '' : 's' ?> remaining &mdash; each works once.</span></p>
        <?php elseif (!empty($totp['pending'])): ?>
            <p class="muted">Enrollment started. Verify a code to finish enabling two-factor authentication.</p>
        <?php else: ?>
            <p class="muted">Not enabled.</p>
        <?php endif; ?>
        <?php // Panel-level state ("already enabled", "not enabled"), not a field:
              // there is no input to attach, so the message takes focus itself —
              // a role="alert" already in the parsed document announces nothing. ?>
        <?php foreach (['totp', 'recovery'] as $secPanelKey): ?>
            <?php if (!empty($secErrs[$secPanelKey])): ?>
                <p class="field-error" id="err-<?= $e($secPanelKey) ?>" tabindex="-1"<?= $secPanelFocus && array_key_first($secErrs) === $secPanelKey ? ' autofocus' : '' ?>><?= $e($secErrs[$secPanelKey]) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$totp['enabled']): ?>
            <form method="post" action="/settings/security/totp/enroll" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password"<?= $sfattr('totp_enroll', 'current_password') ?> required>
                </label>
                <?= $sferr('totp_enroll', 'current_password') ?>
                <button class="btn" type="submit">Start setup</button>
            </form>
        <?php endif; ?>

        <?php if (is_array($setup)): ?>
            <div class="stacked">
                <label class="field">
                    <span>Authenticator secret</span>
                    <input class="input" value="<?= $e((string) $setup['secret']) ?>" readonly>
                </label>
                <label class="field">
                    <span>Authenticator URI</span>
                    <input class="input" value="<?= $e((string) $setup['uri']) ?>" readonly>
                </label>
                <form method="post" action="/settings/security/totp/confirm" class="stacked">
                    <?= $this->csrfField() ?>
                    <label class="field">
                        <span>Current password</span>
                        <input type="password" name="current_password" class="input" autocomplete="current-password"<?= $sfattr('totp_confirm', 'current_password') ?> required>
                    </label>
                    <?= $sferr('totp_confirm', 'current_password') ?>
                    <label class="field">
                        <span>6-digit code</span>
                        <input name="totp_code" class="input" inputmode="numeric" autocomplete="one-time-code"<?= field_attrs($secErrs, 'totp_code') ?> required>
                    </label>
                    <?= field_error($secErrs, 'totp_code') ?>
                    <button class="btn" type="submit">Verify and enable</button>
                </form>
            </div>
        <?php endif; ?>

        <?php // feature-removed FR-04: the design keeps a recovery-code grid permanently
              // displayable. Production HMAC-hashes the codes and can never re-show them,
              // so they appear exactly once — here, immediately after generation. ?>
        <?php if (!empty($recoveryCodes)): ?>
            <div class="stacked">
                <h3 class="totp-codes-head">Recovery codes</h3>
                <p class="muted">Copy these now &mdash; they are shown only once, and each works once.</p>
                <ul class="code-list">
                    <?php foreach ($recoveryCodes as $code): ?>
                        <li><code><?= $e($code) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($totp['enabled'])): ?>
            <form method="post" action="/settings/security/totp/recovery/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password"<?= $sfattr('totp_rotate', 'current_password') ?> required>
                </label>
                <?= $sferr('totp_rotate', 'current_password') ?>
                <button class="btn btn-secondary" type="submit">Rotate recovery codes</button>
            </form>

            <form method="post" action="/settings/security/totp/disable" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password"<?= $sfattr('totp_disable', 'current_password') ?> required>
                </label>
                <?= $sferr('totp_disable', 'current_password') ?>
                <label class="field">
                    <span>Authenticator or recovery code</span>
                    <input name="disable_code" class="input" autocomplete="one-time-code"<?= field_attrs($secErrs, 'disable_code') ?> required>
                </label>
                <?= field_error($secErrs, 'disable_code') ?>
                <button class="btn danger" type="submit">Disable two-factor authentication</button>
            </form>
        <?php endif; ?>
    </section>

    <?php if (is_array($passkeys ?? null)): ?>
    <section class="stacked scribe-panel" data-passkey-panel>
        <h2 class="scribe-panel-head">Passkeys</h2>
        <?php if (!empty($passkey_errors)): ?>
            <p class="field-error"><?= $e(implode(' ', $passkey_errors)) ?></p>
        <?php endif; ?>
        <?php if ($passkeys['credentials'] === []): ?>
            <p class="muted">No passkeys yet. A passkey signs you in with your device's screen lock instead of your password.</p>
        <?php else: ?>
            <ul class="stacked">
                <?php foreach ($passkeys['credentials'] as $pk): ?>
                    <li>
                        <div>
                            <strong><?= $e($pk['nickname'] !== '' ? $pk['nickname'] : 'Unnamed passkey') ?></strong>
                            <p class="muted">Added <?= $e(human_datetime($pk['created_at'])) ?><?= $pk['last_used_at'] !== null ? ' · last used ' . $e(human_datetime((string) $pk['last_used_at'])) : '' ?><?= $pk['backed_up'] ? ' · synced' : '' ?></p>
                        </div>
                        <form method="post" action="/settings/security/passkeys/<?= (int) $pk['id'] ?>/rename" class="stacked">
                            <?= $this->csrfField() ?>
                            <label class="field">
                                <span>Passkey name</span>
                                <input type="text" name="nickname" class="input" value="<?= $e($pk['nickname']) ?>" maxlength="120">
                            </label>
                            <button type="submit" class="btn btn-secondary">Rename</button>
                        </form>
                        <form method="post" action="/settings/security/passkeys/<?= (int) $pk['id'] ?>/revoke" class="stacked" data-passkey-revoke-form>
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="passkey_assertion" value="">
                            <?php if ($passkeys['has_password']): ?>
                                <label class="field">
                                    <span>Current password</span>
                                    <input type="password" name="current_password" class="input" autocomplete="current-password">
                                </label>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" data-passkey-stepup-btn hidden>Confirm with a passkey</button>
                            <?php endif; ?>
                            <button type="submit" class="btn danger"<?= !$passkeys['has_password'] ? ' data-passkey-needs-stepup' : '' ?>>Remove</button>
                            <p class="field-error" data-passkey-revoke-error hidden></p>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form class="stacked"
              data-passkey-add-form
              data-challenge-url="/settings/security/passkeys/challenge"
              data-store-url="/settings/security/passkeys"
              data-stepup-url="/settings/security/passkeys/step-up-challenge"
              hidden>
            <?= $this->csrfField() ?>
            <input type="hidden" name="passkey_assertion" value="">
            <?php if ($passkeys['has_password']): ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password">
                </label>
            <?php endif; ?>
            <label class="field">
                <span>Name this passkey (optional)</span>
                <input type="text" name="nickname" class="input" maxlength="120">
            </label>
            <button type="button" class="btn" data-passkey-add-btn>Add a passkey</button>
            <p class="field-error" data-passkey-add-error hidden></p>
        </form>
        <noscript>
            <p class="muted">Adding a passkey needs JavaScript and a supported browser. Password, authenticator code, and recovery sign-in keep working without it.</p>
        </noscript>
    </section>
    <?php endif; ?>
        </div>
    </div>
</div>
