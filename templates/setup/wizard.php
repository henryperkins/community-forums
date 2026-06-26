<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Set up your community'); $this->section('variant', 'plain'); ?>
<div class="auth-card setup">
    <h1>Welcome — let's set up your community</h1>
    <p class="muted">Create the first administrator account and name your community. You can change everything later.</p>

    <form method="post" action="/setup" class="stacked">
        <?= $this->csrfField() ?>

        <fieldset class="field-group">
            <legend>Community</legend>
            <label class="field">
                <span>Community name</span>
                <input type="text" name="site_name" class="input" maxlength="80" value="<?= $e($old['site_name'] ?? '') ?>" required autofocus>
                <?php if (!empty($errors['site_name'])): ?><span class="field-error"><?= $e($errors['site_name']) ?></span><?php endif; ?>
            </label>
        </fieldset>

        <fieldset class="field-group">
            <legend>Administrator account</legend>
            <label class="field">
                <span>Username</span>
                <input type="text" name="username" class="input" maxlength="32" value="<?= $e($old['username'] ?? '') ?>" required>
                <?php if (!empty($errors['username'])): ?><span class="field-error"><?= $e($errors['username']) ?></span><?php endif; ?>
            </label>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" class="input" maxlength="255" value="<?= $e($old['email'] ?? '') ?>" required>
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
        </fieldset>

        <p class="muted">A starter set of categories and boards will be created automatically.</p>
        <button class="btn" type="submit">Create my community</button>
    </form>
</div>
