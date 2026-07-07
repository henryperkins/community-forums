<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Log in'); $this->section('variant', 'auth'); ?>
<div class="auth-card">
    <span class="auth-eyebrow">Welcome back to the council</span>
    <h1>Log in</h1>
    <?php if (!empty($errors['email'])): ?><p class="field-error auth-error" role="alert"><?= $e($errors['email']) ?></p><?php endif; ?>
    <form method="post" action="/login" class="auth-form">
        <?= $this->csrfField() ?>
        <input type="hidden" name="next" value="<?= $e($next ?? '/') ?>">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" class="input input-engraved" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" required autofocus>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" class="input input-engraved" autocomplete="current-password" required>
        </label>
        <button class="btn" type="submit">Log in</button>
    </form>
    <?php if (!empty($features['passkeys'])): ?>
        <div class="passkey-signin"
             data-passkey-signin
             data-challenge-url="/login/passkey/challenge"
             data-login-url="/login/passkey"
             hidden>
            <button type="button" class="btn btn-secondary" data-passkey-signin-btn>Sign in with a passkey</button>
            <p class="form-error" data-passkey-signin-error hidden></p>
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
