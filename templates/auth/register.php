<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Sign up'); $this->section('variant', 'auth'); ?>
<?php
$errors = $errors ?? [];
// field_attrs() puts autofocus on the FIRST errored field, so the static
// first-field autofocus only applies when the form is clean. Two autofocus
// attributes would leave focus on Username while the error sat on Email.
$registerFirstFocus = $errors === [] ? ' autofocus' : '';
?>
<div class="auth-card wide">
    <span class="auth-eyebrow">Take a seat at the table</span>
    <h1>Create your account</h1>
    <?php if (($registration_mode ?? 'open') === 'closed'): ?>
        <p class="notice" role="status">New sign-ups are currently closed. Please check back later or contact an administrator.</p>
    <?php elseif (!empty($errors['invite'])): ?>
        <p class="notice" role="alert"><?= $e($errors['invite']) ?></p>
    <?php elseif (($registration_mode ?? 'open') === 'invite' && empty($invite_valid)): ?>
        <p class="notice" role="status">Registration is by invitation only. Use your invitation link to sign up.</p>
    <?php elseif (!empty($invite_valid)): ?>
        <p class="notice" role="status">You’ve been invited to join this community. Complete the form to accept your invitation.</p>
    <?php endif; ?>
    <?php if (empty($registration_blocked)): ?>
    <form method="post" action="/register" class="auth-form">
        <?= $this->csrfField() ?>
        <?php $inviteFieldValue = (string) (($invite_token ?? '') !== '' ? $invite_token : ($old['invite'] ?? '')); ?>
        <?php if ($inviteFieldValue !== ''): ?><input type="hidden" name="invite" value="<?= $e($inviteFieldValue) ?>"><?php endif; ?>
        <?php // field_error() emits a <p>, which cannot legally nest inside <label>,
              // so the error line sits after its label. .field:has(+ .field-error)
              // closes the gap the label's own margin would otherwise leave. ?>
        <label class="field">
            <span>Username</span>
            <input type="text" name="username" class="input input-engraved" maxlength="32" value="<?= $e($old['username'] ?? '') ?>"<?= field_attrs($errors, 'username') ?> required<?= $registerFirstFocus ?>>
        </label>
        <?= field_error($errors, 'username') ?>
        <label class="field">
            <span>Display name <span class="muted">(optional)</span></span>
            <input type="text" name="display_name" class="input input-engraved" maxlength="64" value="<?= $e($old['display_name'] ?? '') ?>"<?= field_attrs($errors, 'display_name') ?>>
        </label>
        <?= field_error($errors, 'display_name') ?>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" class="input input-engraved" maxlength="255" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>"<?= field_attrs($errors, 'email') ?> required>
        </label>
        <?= field_error($errors, 'email') ?>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" class="input input-engraved" autocomplete="new-password"<?= field_attrs($errors, 'password') ?> required>
        </label>
        <?= field_error($errors, 'password') ?>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirm" class="input input-engraved" autocomplete="new-password"<?= field_attrs($errors, 'password_confirm') ?> required>
        </label>
        <?= field_error($errors, 'password_confirm') ?>
        <button class="btn" type="submit"><?= !empty($invite_valid) ? 'Accept invitation' : 'Sign up' ?></button>
    </form>
    <?php endif; ?>
    <div class="auth-links"><p>Already have an account? <a href="/login">Log in</a>.</p></div>
</div>
