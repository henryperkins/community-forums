<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Account settings'); ?>
<?php $errors = $errors ?? []; ?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'profile']) ?>
        <div class="settings-pane">

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
        <?php // The panel head is a real heading, as it already is on Security and
              // Notifications. It was the only scribe-panel head still rendering as a
              // <span>, which left this page's panels outside the heading outline. ?>
        <section class="scribe-panel profile-media-panel">
            <h2 class="scribe-panel-head">Avatar</h2>
            <?php if (!empty($old['avatar_path'])): ?>
                <div class="avatar-row">
                    <img class="monogram avatar-img monogram-gilt" src="<?= $e($old['avatar_path']) ?>" alt="" width="64" height="64">
                    <div class="avatar-actions">
                        <form method="post" action="/settings/avatar" enctype="multipart/form-data" class="stacked">
                            <?= $this->csrfField() ?>
                            <label class="field">
                                <span>Upload avatar</span>
                                <input type="file" name="avatar" class="input input-engraved" accept="image/png,image/jpeg,image/gif,image/webp" required>
                            </label>
                            <button class="btn" type="submit">Upload avatar</button>
                        </form>
                        <form method="post" action="/settings/avatar/remove" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn muted" type="submit">Remove avatar</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="/settings/avatar" enctype="multipart/form-data" class="stacked">
                    <?= $this->csrfField() ?>
                    <label class="field">
                        <span>Upload avatar</span>
                        <input type="file" name="avatar" class="input input-engraved" accept="image/png,image/jpeg,image/gif,image/webp" required>
                    </label>
                    <button class="btn" type="submit">Upload avatar</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <form method="post" action="/settings/account" class="stacked scribe-panel">
        <?= $this->csrfField() ?>
        <h2 class="scribe-panel-head">Identity</h2>
        <label class="field">
            <span>Email <span class="muted">(not editable in this version)</span></span>
            <input type="email" class="input input-engraved" value="<?= $e($email ?? '') ?>" disabled>
        </label>
        <?php // field_error() emits a <p>, which cannot legally nest inside <label>.
              // Inside .field-grid a bare sibling would claim its own grid cell and
              // break the two-column pairing, so each cell wraps label + error. ?>
        <div class="field-grid">
            <div class="field-cell">
                <label class="field">
                    <span>Display name</span>
                    <input type="text" name="display_name" class="input input-engraved" maxlength="64" value="<?= $e($old['display_name'] ?? '') ?>"<?= field_attrs($errors, 'display_name') ?>>
                </label>
                <?= field_error($errors, 'display_name') ?>
            </div>
            <div class="field-cell">
                <label class="field">
                    <span>Pronouns</span>
                    <input type="text" name="pronouns" class="input input-engraved" maxlength="32" placeholder="they/them" value="<?= $e($old['pronouns'] ?? '') ?>"<?= field_attrs($errors, 'pronouns') ?>>
                </label>
                <?= field_error($errors, 'pronouns') ?>
            </div>
            <div class="field-cell">
                <label class="field">
                    <span>Location</span>
                    <input type="text" name="location" class="input input-engraved" maxlength="64" value="<?= $e($old['location'] ?? '') ?>"<?= field_attrs($errors, 'location') ?>>
                </label>
                <?= field_error($errors, 'location') ?>
            </div>
            <div class="field-cell">
                <label class="field">
                    <span>Website</span>
                    <input type="url" name="website" class="input input-engraved" maxlength="255" placeholder="https://example.com" value="<?= $e($old['website'] ?? '') ?>"<?= field_attrs($errors, 'website') ?>>
                </label>
                <?= field_error($errors, 'website') ?>
            </div>
        </div>
        <label class="field">
            <span>Bio <span class="muted">(Markdown supported)</span></span>
            <textarea name="bio" rows="5" class="composer-input textarea-engraved" maxlength="1000"<?= field_attrs($errors, 'bio') ?>><?= $e($old['bio'] ?? '') ?></textarea>
        </label>
        <?= field_error($errors, 'bio') ?>
        <label class="field">
            <span>Signature <span class="muted">(shown under your posts, max 3 lines)</span></span>
            <textarea name="signature" rows="3" class="composer-input textarea-engraved" maxlength="500"<?= field_attrs($errors, 'signature') ?>><?= $e($old['signature'] ?? '') ?></textarea>
        </label>
        <?= field_error($errors, 'signature') ?>
        <?php if (!empty($custom_profile_fields)): ?>
            <?php // A group error with no single input to blame: the fieldset carries
                  // the wiring, matching admin/role_edit.php's capability grid. ?>
            <fieldset class="field custom-profile-fields"<?= !empty($errors['custom_profile_fields']) ? ' aria-invalid="true" aria-describedby="err-custom_profile_fields"' : '' ?>>
                <legend class="scribe-panel-head">Custom profile fields</legend>
                <p class="muted">Add up to three public profile facts. Labels are limited to 40 characters; values to 160.</p>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="field-row">
                        <span class="row-bullet" aria-hidden="true"></span>
                        <input type="text" name="custom_label_<?= $i ?>" class="row-input" maxlength="40" placeholder="Label" value="<?= $e($old['custom_label_' . $i] ?? '') ?>">
                        <span class="row-mark" aria-hidden="true">·</span>
                        <input type="text" name="custom_value_<?= $i ?>" class="row-input" maxlength="160" placeholder="Value" value="<?= $e($old['custom_value_' . $i] ?? '') ?>">
                    </div>
                <?php endfor; ?>
                <?= field_error($errors, 'custom_profile_fields') ?>
            </fieldset>
        <?php endif; ?>
        <button class="btn" type="submit">Save changes</button>
    </form>
        </div>
    </div>
</div>
