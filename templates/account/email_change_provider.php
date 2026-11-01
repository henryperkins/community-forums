<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Confirm your account'); ?>
<div class="settings-screen">
    <header class="settings-head"><?= $this->partial('partials/page_heading', ['heading' => 'Confirm your account']) ?><?= $this->partial('partials/settings_intro') ?></header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'profile']) ?>
        <div class="settings-pane">
            <section class="stacked scribe-panel">
                <h2 class="scribe-panel-head">Continue with <?= $e($provider_label) ?></h2>
                <p>Confirm the same provider account already connected to this forum account. Then we will send a verification link to <strong><?= $e($new_email) ?></strong>.</p>
                <p>Your current email stays active until you open that link and confirm the change.</p>
                <p><a class="btn" href="<?= $e($authorize_url) ?>" rel="noreferrer">Continue to <?= $e($provider_label) ?></a></p>
                <p><a href="/settings/account/email">Cancel and return to email settings</a></p>
            </section>
        </div>
    </div>
</div>
