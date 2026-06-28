<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Inbox'); ?>
<?php
$labels = [
    'for_you' => 'For You',
    'unread' => 'Unread',
    'mentions' => 'Mentions',
    'replies' => 'Replies to You',
    'watching' => 'Watching',
    'needs_answer' => 'Needs Answer',
    'assigned' => 'Assigned',
    'decisions' => 'Decisions',
    'solved' => 'Solved',
    'snoozed' => 'Snoozed',
    'starred' => 'Starred',
    'mine' => 'Mine',
    'active' => 'Active',
    'newest' => 'Newest',
    'unanswered' => 'Unanswered',
];
?>
<div class="inbox-view">
    <header class="board-header">
        <h1>Inbox
            <?php if ((int) $unread_count > 0): ?><span class="badge"><?= (int) $unread_count ?> unread</span><?php endif; ?>
        </h1>
        <p class="muted">Your personal triage view for topics that need attention.</p>
    </header>

    <nav class="inbox-tabs" aria-label="Inbox filters">
        <?php foreach ($filters as $f): ?>
            <a class="inbox-tab<?= $f === $filter ? ' is-active' : '' ?>" href="/inbox?filter=<?= $e($f) ?>"
               <?= $f === $filter ? 'aria-current="page"' : '' ?>><?= $e($labels[$f] ?? ucfirst($f)) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($threads)): ?>
        <p class="muted empty">
            <?php if ($filter === 'for_you'): ?>Nothing needs your attention right now.
            <?php elseif ($filter === 'unread'): ?>You're all caught up — nothing unread.
            <?php elseif ($filter === 'starred'): ?>No starred threads yet. Star a thread to keep it here.
            <?php elseif ($filter === 'mine'): ?>You haven't started any threads yet.
            <?php elseif ($filter === 'snoozed'): ?>No snoozed topics waiting.
            <?php else: ?>Nothing to show here.<?php endif; ?>
        </p>
    <?php else: ?>
        <ul class="thread-list">
            <?php foreach ($threads as $t): ?>
                <?= $this->partial('partials/thread_row', ['t' => $t, 'show_board' => true]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => $pages,
        'base_url' => '/inbox?filter=' . rawurlencode($filter) . '&',
    ]) ?>
</div>
