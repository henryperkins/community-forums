<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Email verification'); $this->section('variant', 'auth'); ?>
<div class="auth-card">
    <?php if (!empty($ok)): ?>
        <div class="auth-emblem"><?= $this->partial('partials/icon', ['name' => 'verified']) ?></div>
        <h1>Email verified</h1>
        <p class="auth-lede">Thanks — your email address is confirmed. Your account is ready.</p>
        <div class="auth-links"><p><a href="/">Go to the community</a></p></div>
    <?php else: ?>
        <div class="auth-emblem warn"><?= $this->partial('partials/icon', ['name' => 'verify-failed']) ?></div>
        <h1>Verification link invalid</h1>
        <p class="field-error" role="alert">This verification link is invalid or has expired. If you've already verified, you're all set.</p>
        <div class="auth-links"><p>Signed in? You can request a fresh link from <a href="/settings/account">account settings</a>.</p></div>
    <?php endif; ?>
</div>
