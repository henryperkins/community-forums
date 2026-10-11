<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $saved_feed['name'] ?? 'Following'); ?>
<?php
$feedHeading = isset($saved_feed) ? (string) $saved_feed['name'] : (($feed_view ?? 'following') === 'latest' ? 'Latest' : 'Following');
$this->start('feed_view_controls');
?>
    <?php if (isset($saved_feed)): ?>
        <a href="/settings/boards">Manage saved feeds</a>
    <?php else: ?>
    <nav class="inbox-tabs feed-tabs" aria-label="Feed views">
        <a class="inbox-tab<?= ($feed_view ?? 'following') === 'following' ? ' is-active' : '' ?>" href="/feed?view=following"<?= ($feed_view ?? 'following') === 'following' ? ' aria-current="page"' : '' ?>>Following</a>
        <?php if (!empty($expanded_feeds)): ?>
            <a class="inbox-tab<?= ($feed_view ?? 'following') === 'latest' ? ' is-active' : '' ?>" href="/feed?view=latest"<?= ($feed_view ?? 'following') === 'latest' ? ' aria-current="page"' : '' ?>>Latest</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php $this->stop(); ?>
<?php if ($current_user !== null): $this->start('subheader_leading'); ?>
    <div class="page-toolbar feed-toolbar">
        <h1 class="forum-page-title"><?= $e($feedHeading) ?></h1>
        <div class="page-toolbar-controls">
            <?php if (isset($saved_feed)): ?>
                <?= $this->block('feed_view_controls') ?>
            <?php else: ?>
                <details class="inbox-menu feed-view-menu" name="inbox-controls" data-inbox-menu data-inbox-menu-align="end">
                    <summary aria-label="Feed view: <?= $e($feedHeading) ?>"><span class="toolbar-control-prefix">View:</span><span><?= $e($feedHeading) ?></span><?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?></summary>
                    <div class="inbox-menu-panel"><?= $this->block('feed_view_controls') ?></div>
                </details>
            <?php endif; ?>
        </div>
    </div>
<?php $this->stop(); endif; ?>
<div class="read-main read-pad feed">
    <header class="board-header">
        <?php if ($current_user === null): ?><h1><?= $e($feedHeading) ?></h1><?php endif; ?>
        <p class="muted"><?= isset($saved_feed) ? 'Recent visible activity from your saved board selection.' : (($feed_view ?? 'following') === 'latest' ? 'Recent visible community activity.' : (!empty($expanded_feeds) ? 'Recent activity from people, boards, and tags you follow.' : 'Recent activity from people you follow.')) ?></p>
    </header>
    <?php if ($current_user === null): ?>
        <?php if (isset($saved_feed)): ?><p><?= $this->block('feed_view_controls') ?></p><?php else: ?><?= $this->block('feed_view_controls') ?><?php endif; ?>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p class="muted empty"><?= isset($saved_feed) ? (!empty($unavailable) ? 'This saved feed is unavailable. Update its filter in board settings.' : 'No visible activity in this saved feed.') : (!empty($expanded_feeds) ? 'Nothing here yet. Follow people, boards, or tags to shape this feed.' : 'Nothing here yet. Follow people to shape this feed.') ?></p>
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

        <nav class="pager" aria-label="Pagination">
            <?php $pageBase = isset($saved_feed) ? '/feeds/saved/' . (int) $saved_feed['id'] . '?' : '/feed?view=' . rawurlencode($feed_view ?? 'following') . '&'; ?>
            <?php if ($page > 1): ?><a class="btn btn-small" href="<?= $e($pageBase) ?>page=<?= $page - 1 ?>">← Newer</a><?php endif; ?>
            <?php if (!empty($has_more)): ?><a class="btn btn-small" href="<?= $e($pageBase) ?>page=<?= $page + 1 ?>">Older →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
