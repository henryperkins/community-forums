<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Blocked users'); ?>
<div class="settings">
    <h1>Account settings</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <section class="card">
        <h2>Blocked users</h2>
        <p class="muted">Blocked members can't message or @mention you, and their notifications to you are suppressed.</p>
        <?php if (empty($blocked)): ?>
            <p class="muted">You haven't blocked anyone.</p>
        <?php else: ?>
            <ul class="people-list">
                <?php foreach ($blocked as $b): ?>
                    <?php $name = ($b['display_name'] ?? '') !== '' ? $b['display_name'] : $b['username']; ?>
                    <li class="person-row">
                        <a class="person-name" href="/u/<?= $e($b['username']) ?>"><?= $e($name) ?></a>
                        <span class="muted">@<?= $e($b['username']) ?></span>
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
