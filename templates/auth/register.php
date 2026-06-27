<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Sign up'); $this->section('variant', 'plain'); ?>
<div class="auth-card">
    <h1>Create your account</h1>
    <?php if (!empty($registration_closed)): ?>
        <p class="notice" role="status">New sign-ups are currently closed. Please check back later or contact an administrator.</p>
    <?php endif; ?>
    <form method="post" action="/register" class="stacked">
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Username</span>
            <input type="text" name="username" class="input" maxlength="32" value="<?= $e($old['username'] ?? '') ?>" required autofocus>
            <?php if (!empty($errors['username'])): ?><span class="field-error"><?= $e($errors['username']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Display name <span class="muted">(optional)</span></span>
            <input type="text" name="display_name" class="input" maxlength="64" value="<?= $e($old['display_name'] ?? '') ?>">
            <?php if (!empty($errors['display_name'])): ?><span class="field-error"><?= $e($errors['display_name']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" class="input" maxlength="255" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" required>
            <?php if (!empty($errors['email'])): ?><span class="field-error"><?= $e($errors['email']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" class="input" autocomplete="new-password" required>
            <?php if (!empty($errors['password'])): ?><span class="field-error"><?= $e($errors['password']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirm" class="input" autocomplete="new-password" required>
            <?php if (!empty($errors['password_confirm'])): ?><span class="field-error"><?= $e($errors['password_confirm']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit">Sign up</button>
    </form>
    <p class="muted">Already have an account? <a href="/login">Log in</a>.</p>
</div>
