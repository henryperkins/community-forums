<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Account lifecycle');
$status = (string) ($row['status'] ?? 'active');
$pending = is_array($pending_deletion ?? null) ? $pending_deletion : null;
$errors = $errors ?? [];
// Both /deactivate and /delete/request post `current_password`, so an unscoped
// bag lit the deactivate form's inline error for a refused *deletion*. The
// controller knows which action failed and says so.
$errorForm = (string) ($error_form ?? '');
$deactivateErrors = $errorForm === 'deactivate' ? $errors : [];
$deleteErrors = $errorForm === 'delete' ? $errors : [];
$unscoped = $errorForm === '' ? $errors : [];
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'account']) ?>

        <div class="settings-pane">
    <?php if (!empty($unscoped)): ?>
        <div class="scribe-panel error-list" role="alert">
            <?php foreach ($unscoped as $message): ?>
                <p><?= $e($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="scribe-panel">
        <h2 class="scribe-panel-head">Export account data</h2>
        <p class="lifecycle-note">A JSON archive of your profile, preferences, sessions metadata, subscriptions, notifications, reports, posts, direct messages, and the audit rows attached to them.</p>
        <form method="post" action="/settings/account/export" class="inline-form">
            <?= $this->csrfField() ?>
            <button class="btn btn-secondary" type="submit">Download account export</button>
        </form>
    </section>

    <section class="scribe-panel">
        <h2 class="scribe-panel-head">Deactivate account</h2>
        <?php if ($status === 'deactivated'): ?>
            <p class="lifecycle-note">Your account is deactivated. You can reactivate it to restore write access.</p>
            <form method="post" action="/settings/account/reactivate" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit">Reactivate account</button>
            </form>
        <?php else: ?>
            <p class="lifecycle-note">Reversible. Your account stays sign-in capable, but posting and replying are blocked until you reactivate.</p>
            <form method="post" action="/settings/account/deactivate" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field field-narrow-320">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required<?= field_attrs($deactivateErrors, 'current_password', 'err-deactivate-current_password') ?>>
                    <?= field_error($deactivateErrors, 'current_password', 'err-deactivate-current_password', true) ?>
                </label>
                <button class="btn btn-secondary" type="submit">Deactivate account</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="scribe-panel danger-zone">
        <h2 class="scribe-panel-head">Delete account</h2>
        <?php if ($pending !== null): ?>
            <p class="lifecycle-note">Deletion is scheduled after the grace period on <?= $e(human_datetime((string) $pending['purge_after'])) ?>. Your posts stay readable under a deleted-member identity; everything that identifies you is purged.</p>
            <form method="post" action="/settings/account/delete/cancel" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn btn-secondary" type="submit">Cancel deletion request</button>
            </form>
        <?php else: ?>
            <p class="lifecycle-note">Deletion opens a 30-day grace period. Your account is write-blocked and you can cancel at any point. Your posts stay readable under a deleted-member identity; everything that identifies you is purged.</p>
            <form method="post" action="/settings/account/delete/request" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field field-narrow-320">
                    <span>Current password</span>
                    <input type="password" name="current_password" class="input" autocomplete="current-password" required<?= field_attrs($deleteErrors, 'current_password', 'err-delete-current_password') ?>>
                    <?= field_error($deleteErrors, 'current_password', 'err-delete-current_password', true) ?>
                </label>
                <button class="btn danger" type="submit">Request account deletion</button>
            </form>
        <?php endif; ?>
    </section>
        </div>
    </div>
</div>
