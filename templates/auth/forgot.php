<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Reset your password'); $this->section('variant', 'plain'); ?>
<div class="auth-card">
    <h1>Reset your password</h1>
    <?php if (!empty($sent)): ?>
        <p>If an account exists for that email address, we've sent a link to choose a new password. The link is valid for a limited time.</p>
        <p class="muted">Didn't get it? Check your spam folder, or <a href="/forgot">try again</a>.</p>
        <p class="muted"><a href="/login">Back to log in</a></p>
    <?php else: ?>
        <p class="muted">Enter your account's email address and we'll send you a link to choose a new password.</p>
        <?php if (!empty($errors['email'])): ?><p class="field-error" role="alert"><?= $e($errors['email']) ?></p><?php endif; ?>
        <form method="post" action="/forgot" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" class="input" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" required autofocus>
            </label>
            <button class="btn" type="submit">Send reset link</button>
        </form>
        <p class="muted"><a href="/login">Back to log in</a></p>
    <?php endif; ?>
</div>
