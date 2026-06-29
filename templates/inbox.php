<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Inbox'); $this->section('route', 'inbox'); ?>
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
<div class="inbox-shell" data-inbox>
    <section class="inbox-list" data-inbox-list aria-label="Topics">
        <header class="board-header inbox-list-head">
            <p class="eyebrow">For you</p>
            <h1>Community Inbox
                <?php if ((int) $unread_count > 0): ?><span class="badge"><?= (int) $unread_count ?> unread</span><?php endif; ?>
            </h1>
            <p class="muted">Your triage view — topics that want your attention.</p>
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
    </section>

    <section class="inbox-reading" data-inbox-reading tabindex="-1" aria-label="Reading pane">
        <div class="inbox-empty">
            <svg class="inbox-empty-star" viewBox="0 0 100 100" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/></g></svg>
            <p class="inbox-empty-title">Choose a topic</p>
            <p class="muted">Select a topic on the left to read it here — your place in the list is kept. Without JavaScript, topics open as their own page.</p>
        </div>
    </section>
</div>
