<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Sign up'); $this->section('variant', 'auth'); ?>
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
        <label class="field">
            <span>Username</span>
            <input type="text" name="username" class="input input-engraved" maxlength="32" value="<?= $e($old['username'] ?? '') ?>" required autofocus>
            <?php if (!empty($errors['username'])): ?><span class="field-error"><?= $e($errors['username']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Display name <span class="muted">(optional)</span></span>
            <input type="text" name="display_name" class="input input-engraved" maxlength="64" value="<?= $e($old['display_name'] ?? '') ?>">
            <?php if (!empty($errors['display_name'])): ?><span class="field-error"><?= $e($errors['display_name']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" class="input input-engraved" maxlength="255" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" required>
            <?php if (!empty($errors['email'])): ?><span class="field-error"><?= $e($errors['email']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" class="input input-engraved" autocomplete="new-password" required>
            <?php if (!empty($errors['password'])): ?><span class="field-error"><?= $e($errors['password']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirm" class="input input-engraved" autocomplete="new-password" required>
            <?php if (!empty($errors['password_confirm'])): ?><span class="field-error"><?= $e($errors['password_confirm']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit"><?= !empty($invite_valid) ? 'Accept invitation' : 'Sign up' ?></button>
    </form>
    <?php endif; ?>
    <div class="auth-links"><p>Already have an account? <a href="/login">Log in</a>.</p></div>
</div>
