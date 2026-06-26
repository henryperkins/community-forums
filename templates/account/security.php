<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Change password'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <form method="post" action="/settings/security" class="stacked card">
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Current password</span>
            <input type="password" name="current_password" class="input" autocomplete="current-password" required>
            <?php if (!empty($errors['current_password'])): ?><span class="field-error"><?= $e($errors['current_password']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>New password</span>
            <input type="password" name="new_password" class="input" autocomplete="new-password" required>
            <?php if (!empty($errors['new_password'])): ?><span class="field-error"><?= $e($errors['new_password']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Confirm new password</span>
            <input type="password" name="new_password_confirm" class="input" autocomplete="new-password" required>
            <?php if (!empty($errors['new_password_confirm'])): ?><span class="field-error"><?= $e($errors['new_password_confirm']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit">Change password</button>
    </form>
</div>
