<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Following'); ?>
<div class="read-main read-pad feed">
    <header class="board-header">
        <h1><?= ($feed_view ?? 'following') === 'latest' ? 'Latest' : 'Following' ?></h1>
        <p class="muted"><?= ($feed_view ?? 'following') === 'latest' ? 'Recent visible community activity.' : (!empty($expanded_feeds) ? 'Recent activity from people, boards, and tags you follow.' : 'Recent activity from people you follow.') ?></p>
    </header>
    <nav class="inbox-tabs feed-tabs" aria-label="Feed views">
        <a class="inbox-tab<?= ($feed_view ?? 'following') === 'following' ? ' is-active' : '' ?>" href="/feed?view=following">Following</a>
        <?php if (!empty($expanded_feeds)): ?>
            <a class="inbox-tab<?= ($feed_view ?? 'following') === 'latest' ? ' is-active' : '' ?>" href="/feed?view=latest">Latest</a>
        <?php endif; ?>
    </nav>

    <?php if (empty($items)): ?>
        <p class="muted empty"><?= !empty($expanded_feeds) ? 'Nothing here yet. Follow people, boards, or tags to shape this feed.' : 'Nothing here yet. Follow people to shape this feed.' ?></p>
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
            <?php $viewParam = 'view=' . rawurlencode($feed_view ?? 'following') . '&'; ?>
            <?php if ($page > 1): ?><a class="btn btn-small" href="/feed?<?= $viewParam ?>page=<?= $page - 1 ?>">← Newer</a><?php endif; ?>
            <?php if (!empty($has_more)): ?><a class="btn btn-small" href="/feed?<?= $viewParam ?>page=<?= $page + 1 ?>">Older →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
