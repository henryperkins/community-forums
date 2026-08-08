<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Active sessions');
$lifetimeDays = (int) ($session_lifetime_days ?? 30);
?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'sessions']) ?>

        <div class="settings-pane">
    <section class="scribe-panel is-list">
        <div class="sessions-head">
            <h2 class="scribe-panel-head">Active sessions &amp; devices</h2>
            <form class="inline" method="post" action="/settings/sessions/revoke-others">
                <?= $this->csrfField() ?>
                <button class="btn btn-secondary btn-small" type="submit">Log out of all other devices</button>
            </form>
        </div>
        <ul class="account-ruled-list">
            <?php foreach ($sessions as $s): ?>
                <?php $isCurrent = ($current_id ?? null) === $s['id']; ?>
                <li class="account-ruled-row">
                    <span class="account-row-main">
                        <span class="account-row-name"><?= $e($s['user_agent'] ?: 'Unknown device') ?><?php if ($isCurrent): ?><span class="account-state-chip">This device</span><?php endif; ?></span>
                        <span class="account-row-meta">IP <?= $e($s['ip'] ?: '—') ?> · last active <?= $e(human_datetime($s['last_seen_at'])) ?></span>
                    </span>
                    <?php if (!$isCurrent): ?>
                        <form class="inline" method="post" action="/settings/sessions/revoke">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="sid" value="<?= $e($s['id']) ?>">
                            <button class="linkbtn danger" type="submit">Sign out</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="account-note">Sessions expire <?= $lifetimeDays ?> day<?= $lifetimeDays === 1 ? '' : 's' ?> after you sign in. Signing out a device revokes its token immediately.</p>
    </section>
        </div>
    </div>
</div>
