<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Security');
$totp = $totp ?? ['enabled' => false, 'pending' => false, 'unused_recovery_codes' => 0];
$setup = $totp_setup ?? null;
$recoveryCodes = $new_recovery_codes ?? [];
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav') ?>

        <div class="settings-pane">
    <form method="post" action="/settings/security" class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Password</h2>
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Current password</span>
            <input type="password" name="current_password" class="input" autocomplete="current-password" required>
            <?php if (!empty($errors['current_password'])): ?><span class="field-error"><?= $e($errors['current_password']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>New password</span>
            <input type="password" name="new_password" class="input" autocomplete="new-password" required>
            <?php if (!empty($errors['new_password'])): ?><span class="field-error"><?= $e($errors['new_password']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Confirm new password</span>
            <input type="password" name="new_password_confirm" class="input" autocomplete="new-password" required>
            <?php if (!empty($errors['new_password_confirm'])): ?><span class="field-error"><?= $e($errors['new_password_confirm']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit">Change password</button>
    </form>

    <section class="stacked scribe-panel">
        <h2 class="scribe-panel-head">Two-factor authentication</h2>
        <?php if (!empty($totp['enabled'])): ?>
            <p class="muted">Enabled. <?= (int) $totp['unused_recovery_codes'] ?> recovery code<?= (int) $totp['unused_recovery_codes'] === 1 ? '' : 's' ?> remaining.</p>
        <?php elseif (!empty($totp['pending'])): ?>
            <p class="muted">Enrollment started. Verify a code to finish enabling two-factor authentication.</p>
        <?php else: ?>
            <p class="muted">Not enabled.</p>
        <?php endif; ?>
        <?php if (!empty($errors['totp'])): ?><p class="field-error"><?= $e($errors['totp']) ?></p><?php endif; ?>
        <?php if (!empty($errors['recovery'])): ?><p class="field-error"><?= $e($errors['recovery']) ?></p><?php endif; ?>

        <?php if (!$totp['enabled']): ?>
            <form method="post" action="/settings/security/totp/enroll" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required>
                </label>
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
                        <input type="password" name="current_password" class="input" autocomplete="current-password" required>
                    </label>
                    <label class="field">
                        <span>6-digit code</span>
                        <input name="totp_code" class="input" inputmode="numeric" autocomplete="one-time-code" required>
                        <?php if (!empty($errors['totp_code'])): ?><span class="field-error"><?= $e($errors['totp_code']) ?></span><?php endif; ?>
                    </label>
                    <button class="btn" type="submit">Verify and enable</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($recoveryCodes)): ?>
            <div class="stacked">
                <h3>Recovery codes</h3>
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
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required>
                </label>
                <button class="btn btn-secondary" type="submit">Rotate recovery codes</button>
            </form>

            <form method="post" action="/settings/security/totp/disable" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required>
                </label>
                <label class="field">
                    <span>Authenticator or recovery code</span>
                    <input name="disable_code" class="input" autocomplete="one-time-code" required>
                    <?php if (!empty($errors['disable_code'])): ?><span class="field-error"><?= $e($errors['disable_code']) ?></span><?php endif; ?>
                </label>
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
