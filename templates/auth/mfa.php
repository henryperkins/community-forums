<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Two-factor verification'); ?>
<div class="auth-card">
    <h1>Two-factor verification</h1>
    <form method="post" action="/login/mfa" class="stacked">
        <?= $this->csrfField() ?>
        <input type="hidden" name="mfa_token" value="<?= $e((string) $token) ?>">
        <input type="hidden" name="next" value="<?= $e((string) ($next ?? '/')) ?>">
        <label class="field">
            <span>Authenticator or recovery code</span>
            <input name="code" class="input" autocomplete="one-time-code" required autofocus>
            <?php if (!empty($errors['code'])): ?><span class="field-error"><?= $e($errors['code']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit">Verify</button>
    </form>
</div>
