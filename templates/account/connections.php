<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Connections'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <section class="card">
        <h2>Connected accounts</h2>
        <p class="muted">Link Google, GitHub, or Apple to sign in faster. Email/password always stays available.</p>
        <ul class="connections-list">
            <?php foreach ($providers as $p): ?>
                <li class="connection-row">
                    <span class="connection-name"><?= $e(ucfirst($p['name'])) ?></span>
                    <?php if ($p['linked']): ?>
                        <span class="pill">Connected</span>
                        <?php $row = $linked[$p['name']] ?? null; ?>
                        <?php if ($row !== null && ($row['email'] ?? '') !== ''): ?>
                            <span class="muted"><?= $e($row['email']) ?></span>
                        <?php endif; ?>
                        <form class="inline" method="post" action="/settings/connections/unlink">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="provider" value="<?= $e($p['name']) ?>">
                            <button class="linkbtn danger" type="submit">Disconnect</button>
                        </form>
                    <?php elseif ($p['configured']): ?>
                        <a class="btn btn-small" href="/auth/<?= $e($p['name']) ?>/redirect">Connect</a>
                    <?php else: ?>
                        <span class="muted">Not available</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if (empty($has_password)): ?>
        <section class="card">
            <h2>Set a password</h2>
            <p class="muted">Your account currently signs in only through a connected provider. Set a password to add email sign-in (required before you can disconnect your last provider).</p>
            <form method="post" action="/settings/connections/set-password" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>New password</span>
                    <input type="password" name="new_password" class="input" autocomplete="new-password" required>
                    <?php if (!empty($errors['new_password'])): ?><span class="field-error"><?= $e($errors['new_password']) ?></span><?php endif; ?>
                </label>
                <label class="field">
                    <span>Confirm new password</span>
                    <input type="password" name="new_password_confirm" class="input" autocomplete="new-password" required>
                </label>
                <button class="btn" type="submit">Set password</button>
            </form>
        </section>
    <?php endif; ?>
</div>
