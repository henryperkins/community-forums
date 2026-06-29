<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', '#' . $board['name']);
$this->section('canonical', '/c/' . $board['slug']);
if (!empty($board['description'])) {
    $this->section('description', mb_strimwidth((string) $board['description'], 0, 160, '…'));
}
if (($board['visibility'] ?? 'public') !== 'public') {
    $this->section('robots', 'noindex, nofollow');
}
?>
<div class="board-view">
    <header class="board-header">
        <h1><span class="hash">#</span><?= $e($board['name']) ?>
            <?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
        </h1>
        <?php if (!empty($board['description'])): ?><p class="muted"><?= $e($board['description']) ?></p><?php endif; ?>
        <?php if ($current_user !== null && !empty($expanded_feeds)): ?>
            <form class="inline" method="post" action="/b/<?= (int) $board['id'] ?>/follow">
                <?= $this->csrfField() ?>
                <button class="linkbtn" type="submit"><?= !empty($is_following_board) ? 'Unfollow board' : 'Follow board' ?></button>
                <span class="muted">Discovery feed only</span>
            </form>
        <?php endif; ?>
    </header>

    <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?>
        <div class="joinbar joinbar-archived" data-archived-banner>This board is retired and read-only. You can still read and search its topics, but new topics and replies are closed.</div>
    <?php elseif ($can_post): ?>
        <details class="composer-details" id="new-topic">
            <summary class="btn">New Topic</summary>
            <?= $this->partial('partials/new_thread_form', ['board' => $board, 'errors' => [], 'old' => []]) ?>
        </details>
    <?php elseif ($current_user === null): ?>
        <div class="joinbar">You're browsing as a guest — <a href="/login?next=/c/<?= $e($board['slug']) ?>">log in</a> to start a topic.</div>
    <?php endif; ?>

    <?php if (empty($threads)): ?>
        <p class="muted empty">No threads here yet.</p>
    <?php else: ?>
        <ul class="thread-list">
            <?php foreach ($threads as $t): ?>
                <?= $this->partial('partials/thread_row', ['t' => $t, 'board' => $board, 'show_avatars' => $show_avatars ?? true]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => max(1, (int) ceil($total / $per_page)),
        'base_url' => '/c/' . $board['slug'] . '?',
    ]) ?>

    <?php if (!empty($can_post) && (int) ($board['is_archived'] ?? 0) !== 1): ?>
        <a class="fab" href="#new-topic" aria-label="Start a new topic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></a>
    <?php endif; ?>
</div>
