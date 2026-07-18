<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Approval queue');
$this->section('robots', 'noindex, nofollow');
$topicCount = count($pending_threads ?? []);
$replyCount = count($pending_posts ?? []);
?>
<div class="mod reports-view">
    <header class="mod-head">
        <span>
            <span class="eyebrow">Warden's table</span>
            <h1>Approval queue</h1>
        </span>
        <span class="pill mod-pill">Moderation</span>
    </header>

    <nav class="mod-subnav" aria-label="Moderation queues">
        <a href="/mod/reports">Reports</a>
        <a class="active" href="/mod/approvals">Approval hold <span class="mod-count"><?= $topicCount + $replyCount ?></span></a>
        <a href="/mod/appeals">Appeals</a>
    </nav>

    <div class="mod-pane">
        <header class="board-header">
            <h2>Approval hold</h2>
            <p class="muted">Content held by anti-abuse rules or board approval. Approving publishes it and runs the normal counters and notifications; rejecting removes it.</p>
        </header>

        <section class="card">
            <h2>Topics awaiting approval</h2>
            <?php if (empty($pending_threads)): ?>
                <p class="muted">No topics are awaiting approval.</p>
            <?php else: ?>
                <ul class="approval-list">
                    <?php foreach ($pending_threads as $t): ?>
                        <li class="approval-item">
                            <div class="approval-meta">
                                <strong><?= $e($t['title']) ?></strong>
                                <span class="muted">by @<?= $e($t['author_username']) ?> in #<?= $e($t['board_slug']) ?> · <?= $e($t['created_at']) ?> UTC</span>
                            </div>
                            <div class="approval-actions">
                                <form method="post" action="/mod/approvals/thread/<?= (int) $t['id'] ?>/approve"><?= $this->csrfField() ?><button class="btn" type="submit" aria-label="Approve topic '<?= $e($t['title']) ?>'">Approve</button></form>
                                <form method="post" action="/mod/approvals/thread/<?= (int) $t['id'] ?>/reject"><?= $this->csrfField() ?><button class="btn btn-secondary" type="submit" aria-label="Reject topic '<?= $e($t['title']) ?>'">Reject</button></form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Replies awaiting approval</h2>
            <?php if (empty($pending_posts)): ?>
                <p class="muted">No replies are awaiting approval.</p>
            <?php else: ?>
                <ul class="approval-list">
                    <?php foreach ($pending_posts as $p): ?>
                        <li class="approval-item">
                            <div class="approval-meta">
                                <a href="/t/<?= (int) $p['thread_id'] ?>-<?= $e($p['thread_slug']) ?>"><?= $e($p['thread_title']) ?></a>
                                <span class="muted">reply by @<?= $e($p['author_username']) ?> in #<?= $e($p['board_slug']) ?> · <?= $e($p['created_at']) ?> UTC</span>
                                <p><?= $e(mb_strimwidth((string) $p['body'], 0, 280, '…')) ?></p>
                            </div>
                            <div class="approval-actions">
                                <form method="post" action="/mod/approvals/post/<?= (int) $p['id'] ?>/approve"><?= $this->csrfField() ?><button class="btn" type="submit" aria-label="Approve reply in '<?= $e($p['thread_title']) ?>'">Approve</button></form>
                                <form method="post" action="/mod/approvals/post/<?= (int) $p['id'] ?>/reject"><?= $this->csrfField() ?><button class="btn btn-secondary" type="submit" aria-label="Reject reply in '<?= $e($p['thread_title']) ?>'">Reject</button></form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
