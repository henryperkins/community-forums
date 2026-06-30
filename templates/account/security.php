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
        </div>
    </div>
</div>
