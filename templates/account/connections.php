<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Connections');
$errors = $errors ?? [];
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'connections']) ?>

        <div class="settings-pane">
    <section class="scribe-panel is-list">
        <h2 class="scribe-panel-head">Connected accounts</h2>
        <p class="muted">Link a provider to sign in faster. Email and password always stay available.</p>
        <ul class="account-ruled-list">
            <?php foreach ($providers as $p): ?>
                <?php
                $label = (string) ($p['label'] ?? ucfirst($p['name']));
                $mark = mb_strtoupper(mb_substr($label, 0, 1));
                $identity = $p['linked'] ? ($linked[$p['name']] ?? null) : null;
                $sub = (string) ($identity['email'] ?? '');
                ?>
                <li class="account-ruled-row">
                    <span class="account-row-mark" aria-hidden="true"><?= $e($mark) ?></span>
                    <span class="account-row-main">
                        <span class="account-row-name"><?= $e($label) ?></span>
                        <?php if ($sub !== ''): ?><span class="account-row-meta"><?= $e($sub) ?></span><?php endif; ?>
                    </span>
                    <?php if ($p['linked']): ?>
                        <span class="account-state-chip">Connected</span>
                        <form class="inline" method="post" action="/settings/connections/unlink">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="provider" value="<?= $e($p['name']) ?>">
                            <button class="linkbtn danger" type="submit">Disconnect</button>
                        </form>
                    <?php elseif ($p['configured']): ?>
                        <a class="btn btn-secondary btn-small" href="/auth/<?= $e($p['name']) ?>/redirect">Connect</a>
                    <?php else: ?>
                        <span class="muted">Not available</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="account-note">Disconnecting a provider does not delete anything — it only removes that sign-in route.</p>
    </section>

    <?php if (empty($has_password)): ?>
        <section class="scribe-panel">
            <h2 class="scribe-panel-head">Set a password</h2>
            <p class="muted">Your account currently signs in only through a connected provider. Set a password to add email sign-in (required before you can disconnect your last provider).</p>
            <form method="post" action="/settings/connections/set-password" class="stacked">
                <?= $this->csrfField() ?>
                <div class="field-grid">
                    <label class="field">
                        <span>New password</span>
                        <input type="password" name="new_password" class="input" autocomplete="new-password" required<?= field_attrs($errors, 'new_password') ?>>
                        <?= field_error($errors, 'new_password') ?>
                    </label>
                    <label class="field">
                        <span>Confirm new password</span>
                        <input type="password" name="new_password_confirm" class="input" autocomplete="new-password" required<?= field_attrs($errors, 'new_password_confirm') ?>>
                        <?= field_error($errors, 'new_password_confirm') ?>
                    </label>
                </div>
                <button class="btn" type="submit">Set password</button>
            </form>
        </section>
    <?php endif; ?>
        </div>
    </div>
</div>
