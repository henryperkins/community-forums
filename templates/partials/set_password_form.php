<?php /** @var \App\Core\View $this */ ?>
<section class="scribe-panel" id="set-password">
    <h2 class="scribe-panel-head">Set a password</h2>
    <p class="muted">Set a password to add email sign-in and manage password-protected account settings.</p>
    <form method="post" action="<?= $e($action) ?>" class="stacked">
        <?= $this->csrfField() ?>
        <div class="field-grid">
            <div class="field-cell">
                <label class="field">
                    <span>New password</span>
                    <input type="password" name="new_password" class="input" autocomplete="new-password" required<?= field_attrs($errors, 'new_password') ?>>
                </label>
                <?= field_error($errors, 'new_password') ?>
            </div>
            <div class="field-cell">
                <label class="field">
                    <span>Confirm new password</span>
                    <input type="password" name="new_password_confirm" class="input" autocomplete="new-password" required<?= field_attrs($errors, 'new_password_confirm') ?>>
                </label>
                <?= field_error($errors, 'new_password_confirm') ?>
            </div>
        </div>
        <button class="btn" type="submit">Set password</button>
    </form>
</section>
