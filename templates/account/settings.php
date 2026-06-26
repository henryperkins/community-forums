<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Account settings'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <nav class="subnav">
        <a class="active" href="/settings/account">Profile</a>
        <a href="/settings/security">Password</a>
    </nav>

    <form method="post" action="/settings/account" class="stacked card">
        <?= $this->csrfField() ?>
        <label class="field">
            <span>Email <span class="muted">(not editable in this version)</span></span>
            <input type="email" class="input" value="<?= $e($email ?? '') ?>" disabled>
        </label>
        <label class="field">
            <span>Display name</span>
            <input type="text" name="display_name" class="input" maxlength="64" value="<?= $e($old['display_name'] ?? '') ?>">
            <?php if (!empty($errors['display_name'])): ?><span class="field-error"><?= $e($errors['display_name']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Location</span>
            <input type="text" name="location" class="input" maxlength="64" value="<?= $e($old['location'] ?? '') ?>">
            <?php if (!empty($errors['location'])): ?><span class="field-error"><?= $e($errors['location']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Bio <span class="muted">(Markdown supported)</span></span>
            <textarea name="bio" rows="5" class="composer-input" maxlength="1000"><?= $e($old['bio'] ?? '') ?></textarea>
            <?php if (!empty($errors['bio'])): ?><span class="field-error"><?= $e($errors['bio']) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit">Save changes</button>
    </form>
</div>
