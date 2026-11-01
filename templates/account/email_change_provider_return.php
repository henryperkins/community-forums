<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Finish account confirmation'); ?>
<section class="stacked scribe-panel">
    <h1 class="scribe-panel-head">Finish confirmation with <?= $e($provider_label) ?></h1>
    <p>Continue to confirm your connected provider account and send the verification email to your new address. Your current email stays active until you confirm that email link.</p>
    <form method="post" action="<?= $e($callback_path) ?>">
        <input type="hidden" name="state" value="<?= $e($state) ?>">
        <button type="submit" class="btn">Send confirmation email</button>
    </form>
    <p><a href="/settings/account/email">Cancel and return to email settings</a></p>
</section>
