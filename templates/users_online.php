<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Members online');
$members = is_array($online ?? null) ? $online : [];
?>
<div class="read-main read-pad users-online-surface">
    <header class="users-online-hero">
        <p class="eyebrow">Presence</p>
        <h1>Members online</h1>
        <p>People who chose to show their presence and were active recently.</p>
        <span class="users-online-total"><?= count($members) ?> showing now</span>
    </header>

    <?php if ($members === []): ?>
        <p class="muted empty">No one is showing as online right now.</p>
    <?php else: ?>
        <ul class="users-online-list">
            <?php foreach ($members as $member): ?>
                <li>
                    <a href="/u/<?= $e($member['username']) ?>">
                        <?= $this->partial('partials/monogram', ['name' => $member['display_name'], 'username' => $member['username']]) ?>
                        <span><strong><?= $e($member['display_name']) ?></strong><small>@<?= $e($member['username']) ?></small></span>
                        <span class="presence-dot" aria-label="Online"></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="users-online-privacy">Presence is optional. Members who turn it off in privacy settings are never listed here.</p>
</div>
