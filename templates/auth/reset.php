<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Choose a new password'); $this->section('variant', 'auth'); ?>
<?php
$errors = $errors ?? [];
// See auth/register.php: the static autofocus yields to field_attrs()' on a 422.
$resetFirstFocus = $errors === [] ? ' autofocus' : '';
?>
<div class="auth-card">
    <h1>Choose a new password</h1>
    <?php if (empty($valid)): ?>
        <?php // No field to blame and no form to focus, so the message takes focus
              // itself — a role="alert" already in the parsed document announces
              // nothing, and there is no JS to inject it after load. ?>
        <p class="field-error" role="alert" tabindex="-1" autofocus>This password reset link is invalid or has expired.</p>
        <div class="auth-links"><p><a href="/forgot">Request a new reset link</a></p></div>
    <?php else: ?>
        <p class="auth-lede">Pick something only you would know. You'll use it next time you log in.</p>
        <form method="post" action="/reset" class="auth-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="token" value="<?= $e($token ?? '') ?>">
            <label class="field">
                <span>New password</span>
                <input type="password" name="password" class="input input-engraved" autocomplete="new-password"<?= field_attrs($errors, 'password') ?> required<?= $resetFirstFocus ?>>
            </label>
            <?= field_error($errors, 'password') ?>
            <label class="field">
                <span>Confirm new password</span>
                <input type="password" name="password_confirm" class="input input-engraved" autocomplete="new-password"<?= field_attrs($errors, 'password_confirm') ?> required>
            </label>
            <?= field_error($errors, 'password_confirm') ?>
            <button class="btn" type="submit">Update password</button>
        </form>
    <?php endif; ?>
</div>
