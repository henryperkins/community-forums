<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Change email address'); ?>
<div class="settings-screen">
    <header class="settings-head"><?= $this->partial('partials/page_heading', ['heading' => 'Change email address']) ?><?= $this->partial('partials/settings_intro') ?></header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'profile']) ?>
        <div class="settings-pane">
            <section class="stacked scribe-panel">
                <h2 class="scribe-panel-head">Current email</h2>
                <p><?= $e($email) ?></p>
                <p class="muted">This address stays active until you confirm the new one. Your connected sign-in providers will stay connected.</p>
            </section>
            <?php if ($pending !== null): ?>
                <section class="stacked scribe-panel" aria-labelledby="pending-email-heading">
                    <h2 id="pending-email-heading" class="scribe-panel-head">Awaiting confirmation</h2>
                    <p>A confirmation was requested for <strong><?= $e($pending['new_email']) ?></strong>. The link expires at <?= $e($pending['expires_at']) ?> UTC.</p>
                    <p class="muted">Requesting another link replaces the previous one.</p>
                    <form method="post" action="/settings/account/email/cancel"><?= $this->csrfField() ?><button type="submit" class="btn btn-secondary">Cancel email change</button></form>
                </section>
            <?php endif; ?>
            <form method="post" action="/settings/account/email" class="stacked scribe-panel" data-passkey-stepup-form data-stepup-url="/settings/security/passkeys/step-up-challenge">
                <?= $this->csrfField() ?>
                <h2 class="scribe-panel-head">New email address</h2>
                <?php if (!empty($errors['authentication'])): ?><p class="field-error" role="alert"><?= $e($errors['authentication']) ?></p><?php endif; ?>
                <label class="field"><span>New email</span><input type="email" name="new_email" class="input input-engraved" maxlength="255" required autocomplete="email" autocapitalize="off" spellcheck="false" value="<?= $e($new_email) ?>"<?= field_attrs($errors, 'new_email') ?>></label>
                <?= field_error($errors, 'new_email') ?>
                <?php if ($has_password): ?>
                    <label class="field"><span>Current password</span><input type="password" name="current_password" class="input input-engraved" autocomplete="current-password"<?= field_attrs($errors, 'current_password') ?>></label>
                    <?= field_error($errors, 'current_password') ?>
                    <button type="submit" class="btn">Send confirmation email</button>
                <?php elseif (!$has_passkey && $providers === []): ?>
                    <p class="muted">Add a password in <a href="/settings/security#set-password">Security</a> before changing your email address.</p>
                <?php endif; ?>
                <?php if ($has_passkey): ?>
                    <input type="hidden" name="passkey_assertion" value="">
                    <button type="button" class="btn btn-secondary" data-passkey-stepup-btn hidden>Confirm with a passkey</button>
                    <p class="field-error" data-passkey-revoke-error role="alert" hidden></p>
                    <?= field_error($errors, 'passkey') ?>
                    <noscript><p>Passkey confirmation needs JavaScript. Use a connected provider below, or <a href="/settings/security#set-password">add a password</a>.</p></noscript>
                <?php endif; ?>
                <?php foreach ($providers as $provider): ?>
                    <button type="submit" class="btn btn-secondary" formaction="/settings/account/email/oauth/<?= $e($provider['name']) ?>">Confirm with <?= $e($provider['label']) ?></button>
                <?php endforeach; ?>
                <?php if (!empty($errors['current_password']) && !$has_password): ?><p class="field-error" role="alert"><?= $e($errors['current_password']) ?></p><?php endif; ?>
                <p class="field-hint">The confirmation link is sent to your new address. After confirmation, other sessions are signed out and a security notice is queued to your old address.</p>
            </form>
            <p><a href="/settings/account">Back to account settings</a></p>
        </div>
    </div>
</div>
