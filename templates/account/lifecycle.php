<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Account lifecycle');
$status = (string) ($row['status'] ?? 'active');
$pending = is_array($pending_deletion ?? null) ? $pending_deletion : null;
?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <?php if (!empty($errors)): ?>
        <div class="card error-list" role="alert">
            <?php foreach ($errors as $message): ?>
                <p><?= $e($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="card stacked">
        <h2>Export account data</h2>
        <p class="muted">Download a JSON archive of your profile, preferences, sessions metadata, subscriptions, notifications, reports, posts, direct messages, and related audit rows.</p>
        <form method="post" action="/settings/account/export" class="inline-form">
            <?= $this->csrfField() ?>
            <button class="btn btn-secondary" type="submit">Download account export</button>
        </form>
    </section>

    <section class="card stacked">
        <h2>Deactivate account</h2>
        <?php if ($status === 'deactivated'): ?>
            <p class="muted">Your account is deactivated. You can reactivate it to restore write access.</p>
            <form method="post" action="/settings/account/reactivate" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit">Reactivate account</button>
            </form>
        <?php else: ?>
            <p class="muted">Deactivation is reversible. Your account stays sign-in capable, but write actions are blocked until you reactivate.</p>
            <form method="post" action="/settings/account/deactivate" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required>
                    <?php if (!empty($errors['current_password'])): ?><span class="field-error"><?= $e($errors['current_password']) ?></span><?php endif; ?>
                </label>
                <button class="btn btn-secondary" type="submit">Deactivate account</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card stacked danger-zone">
        <h2>Delete account</h2>
        <?php if ($pending !== null): ?>
            <p class="muted">Deletion is scheduled after the grace period on <?= $e((string) $pending['purge_after']) ?> UTC. Public content will be preserved under a deleted-user identity while account PII is purged.</p>
            <form method="post" action="/settings/account/delete/cancel" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn btn-secondary" type="submit">Cancel deletion request</button>
            </form>
        <?php else: ?>
            <p class="muted">Deletion starts a 30-day grace period. During the grace period your account is write-blocked and you can cancel here.</p>
            <form method="post" action="/settings/account/delete/request" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required>
                </label>
                <button class="btn danger" type="submit">Request account deletion</button>
            </form>
        <?php endif; ?>
    </section>
</div>
