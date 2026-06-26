<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Following'); ?>
<div class="feed">
    <header class="board-header">
        <h1>Following</h1>
        <p class="muted">Recent activity from people you follow.</p>
    </header>

    <?php if (empty($items)): ?>
        <p class="muted empty">Nothing here yet. <a href="/leaderboard">Find people to follow</a> on the leaderboard, or visit a member's profile.</p>
    <?php else: ?>
        <ul class="feed-list">
            <?php foreach ($items as $it): ?>
                <?php $author = ($it['author_display_name'] ?? '') !== '' ? $it['author_display_name'] : $it['author_username']; ?>
                <li class="feed-item">
                    <div class="feed-meta">
                        <a class="post-author" href="/u/<?= $e($it['author_username']) ?>"><?= $e($author) ?></a>
                        <span class="muted"><?= (int) $it['is_op'] === 1 ? 'started a topic' : 'replied' ?></span>
                        <span class="post-time"><?= $e(human_datetime($it['created_at'])) ?></span>
                    </div>
                    <a class="feed-thread" href="/t/<?= (int) $it['thread_id'] ?>-<?= $e($it['thread_slug']) ?>#p<?= (int) $it['id'] ?>"><?= $e($it['thread_title']) ?></a>
                    <span class="muted">in #<?= $e($it['board_slug']) ?></span>
                    <p class="feed-excerpt"><?= $e(mb_strimwidth((string) $it['body'], 0, 200, '…')) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

        <nav class="pager">
            <?php if ($page > 1): ?><a class="btn btn-small" href="/feed?page=<?= $page - 1 ?>">← Newer</a><?php endif; ?>
            <?php if (!empty($has_more)): ?><a class="btn btn-small" href="/feed?page=<?= $page + 1 ?>">Older →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
