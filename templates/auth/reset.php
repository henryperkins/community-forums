<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Choose a new password'); $this->section('variant', 'plain'); ?>
<div class="auth-card">
    <h1>Choose a new password</h1>
    <?php if (empty($valid)): ?>
        <p class="field-error" role="alert">This password reset link is invalid or has expired.</p>
        <p class="muted"><a href="/forgot">Request a new reset link</a></p>
    <?php else: ?>
        <?php if (!empty($errors['password'])): ?><p class="field-error" role="alert"><?= $e($errors['password']) ?></p><?php endif; ?>
        <?php if (!empty($errors['password_confirm'])): ?><p class="field-error" role="alert"><?= $e($errors['password_confirm']) ?></p><?php endif; ?>
        <form method="post" action="/reset" class="stacked">
            <?= $this->csrfField() ?>
            <input type="hidden" name="token" value="<?= $e($token ?? '') ?>">
            <label class="field">
                <span>New password</span>
                <input type="password" name="password" class="input" autocomplete="new-password" required autofocus>
            </label>
            <label class="field">
                <span>Confirm new password</span>
                <input type="password" name="password_confirm" class="input" autocomplete="new-password" required>
            </label>
            <button class="btn" type="submit">Update password</button>
        </form>
    <?php endif; ?>
</div>
