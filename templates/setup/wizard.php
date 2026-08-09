<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Set up your community'); $this->section('variant', 'plain'); ?>
<?php
$errors = $errors ?? [];
// See auth/register.php: the static autofocus yields to field_attrs()' on a 422.
$wizardFirstFocus = $errors === [] ? ' autofocus' : '';
?>
<div class="auth-card setup">
    <h1>Welcome — let's set up your community</h1>
    <p class="muted">Create the first administrator account and name your community. You can change everything later.</p>

    <form method="post" action="/setup" class="stacked">
        <?= $this->csrfField() ?>

        <fieldset class="field-group">
            <legend>Community</legend>
            <?php // field_error() emits a <p>, which cannot legally nest inside <label>,
                  // so the error line follows its label; .field:has(+ .field-error)
                  // closes the gap the label's own margin would otherwise leave. ?>
            <label class="field">
                <span>Community name</span>
                <input type="text" name="site_name" class="input" maxlength="80" value="<?= $e($old['site_name'] ?? '') ?>"<?= field_attrs($errors, 'site_name') ?> required<?= $wizardFirstFocus ?>>
            </label>
            <?= field_error($errors, 'site_name') ?>
        </fieldset>

        <fieldset class="field-group">
            <legend>Administrator account</legend>
            <label class="field">
                <span>Username</span>
                <input type="text" name="username" class="input" maxlength="32" value="<?= $e($old['username'] ?? '') ?>"<?= field_attrs($errors, 'username') ?> required>
            </label>
            <?= field_error($errors, 'username') ?>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" class="input" maxlength="255" value="<?= $e($old['email'] ?? '') ?>"<?= field_attrs($errors, 'email') ?> required>
            </label>
            <?= field_error($errors, 'email') ?>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" class="input" autocomplete="new-password"<?= field_attrs($errors, 'password') ?> required>
            </label>
            <?= field_error($errors, 'password') ?>
            <label class="field">
                <span>Confirm password</span>
                <input type="password" name="password_confirm" class="input" autocomplete="new-password"<?= field_attrs($errors, 'password_confirm') ?> required>
            </label>
            <?= field_error($errors, 'password_confirm') ?>
        </fieldset>

        <p class="muted">A starter set of categories and boards will be created automatically.</p>
        <button class="btn" type="submit">Create my community</button>
    </form>
</div>
