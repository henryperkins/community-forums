<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('variant', 'plain'); $this->section('title', 'Email preferences'); ?>
<div class="auth-card">
    <h1>Email preferences</h1>
    <?php if ($done): ?>
        <p>Done — <strong><?= $e($email) ?></strong> will no longer receive notification emails.</p>
        <p class="muted">Changed your mind?</p>
        <form method="post" action="/resubscribe">
            <?= $this->csrfField() ?>
            <input type="hidden" name="email" value="<?= $e($email) ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <button class="btn" type="submit">Re-subscribe</button>
        </form>
    <?php elseif ($resubscribed): ?>
        <p><strong><?= $e($email) ?></strong> has been re-subscribed to notification emails.</p>
    <?php else: ?>
        <p>Unsubscribe <strong><?= $e($email) ?></strong> from <?= $e($site_name) ?> notification emails?</p>
        <form method="post" action="/unsubscribe">
            <?= $this->csrfField() ?>
            <input type="hidden" name="email" value="<?= $e($email) ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <button class="btn" type="submit">Unsubscribe</button>
        </form>
    <?php endif; ?>
</div>
