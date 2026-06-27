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
    </header>

    <?php if ($can_post): ?>
        <details class="composer-details">
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
</div>
