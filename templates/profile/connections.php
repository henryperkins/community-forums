<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$display = ($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['username'];
$heading = $mode === 'followers' ? 'Followers' : 'Following';
$this->section('title', $heading . ' · @' . $profile['username']);
$this->section('canonical', '/u/' . $profile['username']);
$this->section('description', $heading . ' of ' . $display . ' (@' . $profile['username'] . ').');
$this->section('composer', '0');
$page = (int) ($page ?? 1);
$pageCount = (int) ($page_count ?? 1);
$listUrl = '/u/' . $profile['username'] . '/' . ($mode === 'followers' ? 'followers' : 'following');
?>
<div class="read-main read-pad connections">
    <header class="board-header">
        <h1><?= $e($heading) ?> <span class="muted">· <a href="/u/<?= $e($profile['username']) ?>">@<?= $e($profile['username']) ?></a></span></h1>
    </header>
    <?php if (empty($people)): ?>
        <div class="profile-panel-empty">
            <h2><?= $mode === 'followers' ? 'No followers yet.' : 'Not following anyone yet.' ?></h2>
            <p><?= $mode === 'followers' ? 'When members follow ' . $e($display) . ', they will be listed here.' : $e($display) . ' is not following anyone yet.' ?></p>
        </div>
    <?php else: ?>
        <ul class="people-list">
            <?php foreach ($people as $person): ?>
                <?php $pd = ($person['display_name'] ?? '') !== '' ? $person['display_name'] : $person['username']; ?>
                <li class="person-row">
                    <?= $this->partial('partials/monogram', ['name' => $pd, 'username' => $person['username']]) ?>
                    <a class="person-name" href="/u/<?= $e($person['username']) ?>"><?= $e($pd) ?></a>
                    <span class="handle">@<?= $e($person['username']) ?></span>
                    <span class="muted person-rep"><?= (int) ($person['reputation'] ?? 0) ?> regard</span>
                    <?php if (!empty($can_remove_followers)): ?>
                        <form class="inline" method="post" action="/u/<?= $e($profile['username']) ?>/followers/<?= (int) $person['id'] ?>/remove">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="return" value="<?= $e($page > 1 ? $listUrl . '?page=' . $page : $listUrl) ?>">
                            <button class="linkbtn danger" type="submit">Remove</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pageCount > 1): ?>
            <nav class="profile-pager" aria-label="Pagination">
                <?php if ($page > 1): ?><a class="btn btn-small" href="<?= $e($page === 2 ? $listUrl : $listUrl . '?page=' . ($page - 1)) ?>">Previous</a><?php else: ?><span class="btn btn-small is-disabled" aria-disabled="true">Previous</span><?php endif; ?>
                <span class="profile-pager-label">Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?><a class="btn btn-small" href="<?= $e($listUrl . '?page=' . ($page + 1)) ?>">Next</a><?php else: ?><span class="btn btn-small is-disabled" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
