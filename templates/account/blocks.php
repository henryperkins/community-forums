<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Blocked users'); ?>
<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'blocks']) ?>

        <div class="settings-pane">
    <section class="scribe-panel is-list">
        <h2 class="scribe-panel-head">Blocked members</h2>
        <p class="muted">A blocked member cannot open a conversation with you or @mention you, and their notifications to you are suppressed. Their posts stay readable — blocking is not moderation.</p>
        <?php if (empty($blocked)): ?>
            <p class="account-note">You have not blocked anyone.</p>
        <?php else: ?>
            <ul class="account-ruled-list">
                <?php foreach ($blocked as $b): ?>
                    <?php $name = ($b['display_name'] ?? '') !== '' ? $b['display_name'] : $b['username']; ?>
                    <li class="account-ruled-row">
                        <?= $this->partial('partials/monogram', ['name' => $name, 'username' => $b['username']]) ?>
                        <span class="account-row-main">
                            <a class="account-row-name" href="/u/<?= $e($b['username']) ?>"><?= $e($name) ?></a>
                            <span class="account-row-meta">@<?= $e($b['username']) ?><?php if (!empty($b['created_at'])): ?> · blocked <?= $e(human_datetime($b['created_at'])) ?><?php endif; ?></span>
                        </span>
                        <form class="inline" method="post" action="/u/<?= $e($b['username']) ?>/block">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="return" value="/settings/blocks">
                            <button class="linkbtn" type="submit">Unblock</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
        </div>
    </div>
</div>
