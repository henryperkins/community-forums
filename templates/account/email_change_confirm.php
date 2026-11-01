<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Confirm email change'); ?>
<div class="settings-screen">
    <header class="settings-head"><?= $this->partial('partials/page_heading', ['heading' => 'Confirm email change']) ?><?= $this->partial('partials/settings_intro') ?></header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'profile']) ?>
        <div class="settings-pane">
            <section class="stacked scribe-panel">
                <?php foreach ($errors as $error): ?><p class="field-error" role="alert"><?= $e($error) ?></p><?php endforeach; ?>
                <?php if ($verification !== null): ?>
                    <h2 class="scribe-panel-head">Use your new address</h2>
                    <p>Change your email from <strong><?= $e($email) ?></strong> to <strong><?= $e($verification['new_email']) ?></strong>?</p>
                    <p>Other sessions will be signed out. A security notice will be queued to your old address.</p>
                    <form method="post" action="/settings/account/email/confirm">
                        <?= $this->csrfField() ?><input type="hidden" name="token" value="<?= $e($token) ?>">
                        <button type="submit" class="btn">Confirm email change</button>
                    </form>
                <?php else: ?>
                    <h2 class="scribe-panel-head">This link cannot be used</h2>
                    <p>It may have expired, been replaced, or belong to another account. Your email has not been changed by opening this page.</p>
                    <p><a href="/settings/account/email">Request a new confirmation link</a></p>
                <?php endif; ?>
                <p><a href="/settings/account">Back to account settings</a></p>
            </section>
        </div>
    </div>
</div>
