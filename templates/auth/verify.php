<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Email verification'); $this->section('variant', 'plain'); ?>
<div class="auth-card">
    <?php if (!empty($ok)): ?>
        <h1>Email verified</h1>
        <p>Thanks — your email address is confirmed.</p>
        <p class="muted"><a href="/">Go to the community</a></p>
    <?php else: ?>
        <h1>Verification link invalid</h1>
        <p class="field-error" role="alert">This verification link is invalid or has expired. If you've already verified, you're all set.</p>
        <p class="muted">Signed in? You can request a fresh link from <a href="/settings/account">account settings</a>.</p>
    <?php endif; ?>
</div>
