<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Active sessions'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <section class="card">
        <div class="sessions-head">
            <h2>Active sessions &amp; devices</h2>
            <form class="inline" method="post" action="/settings/sessions/revoke-others">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit">Log out of all other devices</button>
            </form>
        </div>
        <ul class="session-list">
            <?php foreach ($sessions as $s): ?>
                <?php $isCurrent = ($current_id ?? null) === $s['id']; ?>
                <li class="session-row">
                    <div class="session-meta">
                        <span class="session-ua"><?= $e($s['user_agent'] ?: 'Unknown device') ?></span>
                        <?php if ($isCurrent): ?><span class="pill">This device</span><?php endif; ?>
                        <span class="muted">IP <?= $e($s['ip'] ?: '—') ?> · last active <?= $e(human_datetime($s['last_seen_at'])) ?></span>
                    </div>
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
    </section>
</div>
