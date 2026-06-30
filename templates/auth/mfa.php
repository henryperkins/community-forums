<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Two-factor verification'); $this->section('variant', 'auth'); ?>
<div class="auth-card">
    <span class="auth-eyebrow">One more ward</span>
    <h1>Two-factor verification</h1>
    <p class="auth-lede">Enter the code from your authenticator, or a one-time recovery code.</p>
    <form method="post" action="/login/mfa" class="auth-form">
        <?= $this->csrfField() ?>
        <input type="hidden" name="mfa_token" value="<?= $e((string) $token) ?>">
        <input type="hidden" name="next" value="<?= $e((string) ($next ?? '/')) ?>">
        <label class="field">
            <span>Authenticator or recovery code</span>
            <input name="code" class="input input-engraved" autocomplete="one-time-code" required autofocus>
            <?php if (!empty($errors['code'])): ?><span class="field-error"><?= $e($errors['code']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit">Verify</button>
    </form>
    <div class="auth-links"><p><a href="/login">Back to log in</a></p></div>
</div>
