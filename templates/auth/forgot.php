<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Reset your password'); $this->section('variant', 'auth'); ?>
<?php $forgotErrors = $errors ?? []; ?>
<div class="auth-card">
    <h1>Reset your password</h1>
    <?php if (!empty($sent)): ?>
        <p class="auth-lede">If an account exists for that email address, we've sent a link to choose a new password. The link is valid for a limited time.</p>
        <div class="auth-links">
            <p>Didn't get it? Check your spam folder, or <a href="/forgot">try again</a>.</p>
            <p><a href="/login">Back to log in</a></p>
        </div>
    <?php else: ?>
        <p class="auth-lede">Enter your account's email address and we'll send you a link to choose a new password.</p>
        <form method="post" action="/forgot" class="auth-form">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" class="input input-engraved" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>"<?= field_attrs($forgotErrors, 'email') ?> required<?= $forgotErrors === [] ? ' autofocus' : '' ?>>
            </label>
            <?= field_error($forgotErrors, 'email') ?>
            <button class="btn" type="submit">Send reset link</button>
        </form>
        <div class="auth-links"><p><a href="/login">Back to log in</a></p></div>
    <?php endif; ?>
</div>
