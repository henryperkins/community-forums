<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Log in'); $this->section('variant', 'auth'); ?>
<?php
// Every refusal (wrong credentials, throttled, banned) is one message that names
// no field on purpose: naming the wrong one would tell a stranger whether the
// address has an account. So neither input is marked invalid; both are described
// by the message, and focus lands on the password (the email is kept) so a screen
// reader hears why the sign-in failed as the page arrives.
$loginError = (string) ($errors['email'] ?? '');
$describedBy = $loginError !== '' ? ' aria-describedby="login-error"' : '';
?>
<div class="auth-card">
    <span class="auth-eyebrow">Welcome back</span>
    <h1>Log in</h1>
    <?php if ($loginError !== ''): ?><p class="field-error auth-error" id="login-error" role="alert"><?= $e($loginError) ?></p><?php endif; ?>
    <form method="post" action="/login" class="auth-form">
        <?= $this->csrfField() ?>
        <input type="hidden" name="next" value="<?= $e($next ?? '/') ?>">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" class="input input-engraved" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" required<?= $describedBy ?><?= $loginError === '' ? ' autofocus' : '' ?>>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" class="input input-engraved" autocomplete="current-password" required<?= $describedBy ?><?= $loginError !== '' ? ' autofocus' : '' ?>>
        </label>
        <button class="btn" type="submit">Log in</button>
    </form>
    <?php if (!empty($passkeys_usable)): ?>
        <div class="passkey-signin"
             data-passkey-signin
             data-challenge-url="/login/passkey/challenge"
             data-login-url="/login/passkey"
             hidden>
            <button type="button" class="btn btn-secondary" data-passkey-signin-btn>Sign in with a passkey</button>
            <?php /* Always rendered, empty until a ceremony fails: a live region has to exist before its text arrives or it is not announced. */ ?>
            <p class="field-error" data-passkey-signin-error role="alert"></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($oauth_providers)): ?>
        <div class="oauth-buttons">
            <p class="oauth-sep">or sign in with</p>
            <div class="oauth-row">
            <?php foreach ($oauth_providers as $p): ?>
                <a class="btn btn-oauth btn-oauth-<?= $e($p['name']) ?>" href="/auth/<?= $e($p['name']) ?>/redirect"><?= $e($p['label']) ?></a>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="auth-links">
        <p><a href="/forgot">Forgot your password?</a></p>
        <p>New here? <a href="/register">Create an account</a>.</p>
    </div>
</div>
