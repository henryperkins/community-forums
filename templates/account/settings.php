<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Account settings'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <?php if (isset($email_verified) && !$email_verified): ?>
        <div class="card notice" role="status">
            <p><strong>Verify your email address.</strong> We've sent a confirmation link to your inbox. Verifying keeps your account recoverable and unlocks your welcome badge.</p>
            <form method="post" action="/verify/resend" class="inline">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit">Resend verification email</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (!empty($profile_media)): ?>
        <section class="card">
            <h2>Avatar</h2>
            <?php if (!empty($old['avatar_path'])): ?>
                <p><img class="monogram avatar-img" src="<?= $e($old['avatar_path']) ?>" alt="" width="64" height="64"></p>
            <?php endif; ?>
            <form method="post" action="/settings/avatar" enctype="multipart/form-data" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Upload avatar</span>
                    <input type="file" name="avatar" class="input" accept="image/png,image/jpeg,image/gif,image/webp" required>
                </label>
                <button class="btn" type="submit">Upload avatar</button>
            </form>
            <?php if (!empty($old['avatar_path'])): ?>
                <form method="post" action="/settings/avatar/remove" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn muted" type="submit">Remove avatar</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

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
            <span>Website</span>
            <input type="url" name="website" class="input" maxlength="255" placeholder="https://example.com" value="<?= $e($old['website'] ?? '') ?>">
            <?php if (!empty($errors['website'])): ?><span class="field-error"><?= $e($errors['website']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Pronouns</span>
            <input type="text" name="pronouns" class="input" maxlength="32" placeholder="they/them" value="<?= $e($old['pronouns'] ?? '') ?>">
            <?php if (!empty($errors['pronouns'])): ?><span class="field-error"><?= $e($errors['pronouns']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Bio <span class="muted">(Markdown supported)</span></span>
            <textarea name="bio" rows="5" class="composer-input" maxlength="1000"><?= $e($old['bio'] ?? '') ?></textarea>
            <?php if (!empty($errors['bio'])): ?><span class="field-error"><?= $e($errors['bio']) ?></span><?php endif; ?>
        </label>
        <label class="field">
            <span>Signature <span class="muted">(shown under your posts, max 3 lines)</span></span>
            <textarea name="signature" rows="3" class="composer-input" maxlength="500"><?= $e($old['signature'] ?? '') ?></textarea>
            <?php if (!empty($errors['signature'])): ?><span class="field-error"><?= $e($errors['signature']) ?></span><?php endif; ?>
        </label>
        <?php if (!empty($custom_profile_fields)): ?>
            <fieldset class="field">
                <legend>Custom profile fields</legend>
                <p class="muted">Add up to three public profile facts. Labels are limited to 40 characters; values to 160.</p>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="inline-form">
                        <input type="text" name="custom_label_<?= $i ?>" class="input" maxlength="40" placeholder="Label" value="<?= $e($old['custom_label_' . $i] ?? '') ?>">
                        <input type="text" name="custom_value_<?= $i ?>" class="input" maxlength="160" placeholder="Value" value="<?= $e($old['custom_value_' . $i] ?? '') ?>">
                    </div>
                <?php endfor; ?>
                <?php if (!empty($errors['custom_profile_fields'])): ?><span class="field-error"><?= $e($errors['custom_profile_fields']) ?></span><?php endif; ?>
            </fieldset>
        <?php endif; ?>
        <button class="btn" type="submit">Save changes</button>
    </form>
</div>
