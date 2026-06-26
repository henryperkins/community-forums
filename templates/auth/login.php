<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Log in'); $this->section('variant', 'plain'); ?>
<div class="auth-card">
    <h1>Log in</h1>
    <?php if (!empty($errors['email'])): ?><p class="field-error" role="alert"><?= $e($errors['email']) ?></p><?php endif; ?>
    <form method="post" action="/login" class="stacked">
        <?= $this->csrfField() ?>
        <input type="hidden" name="next" value="<?= $e($next ?? '/') ?>">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" class="input" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" required autofocus>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" class="input" autocomplete="current-password" required>
        </label>
        <button class="btn" type="submit">Log in</button>
    </form>
    <?php if (!empty($oauth_providers)): ?>
        <div class="oauth-buttons">
            <p class="muted oauth-sep">or sign in with</p>
            <?php foreach ($oauth_providers as $p): ?>
                <a class="btn btn-oauth btn-oauth-<?= $e($p) ?>" href="/auth/<?= $e($p) ?>/redirect"><?= $e(ucfirst($p)) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="muted">New here? <a href="/register">Create an account</a>.</p>
</div>
