<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$display = ($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['username'];
$heading = $mode === 'followers' ? 'Followers' : 'Following';
$this->section('title', $heading . ' · @' . $profile['username']);
?>
<div class="read-main read-pad connections">
    <header class="board-header">
        <h1><?= $e($heading) ?> <span class="muted">· <a href="/u/<?= $e($profile['username']) ?>">@<?= $e($profile['username']) ?></a></span></h1>
    </header>
    <?php if (empty($people)): ?>
        <p class="muted empty"><?= $mode === 'followers' ? 'No followers yet.' : 'Not following anyone yet.' ?></p>
    <?php else: ?>
        <ul class="people-list">
            <?php foreach ($people as $person): ?>
                <?php $pd = ($person['display_name'] ?? '') !== '' ? $person['display_name'] : $person['username']; ?>
                <li class="person-row">
                    <?= $this->partial('partials/monogram', ['name' => $pd, 'username' => $person['username']]) ?>
                    <a class="person-name" href="/u/<?= $e($person['username']) ?>"><?= $e($pd) ?></a>
                    <span class="handle">@<?= $e($person['username']) ?></span>
                    <span class="muted person-rep"><?= (int) ($person['reputation'] ?? 0) ?> rep</span>
                    <?php if (!empty($can_remove_followers)): ?>
                        <form class="inline" method="post" action="/u/<?= $e($profile['username']) ?>/followers/<?= (int) $person['id'] ?>/remove">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn danger" type="submit">Remove</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
