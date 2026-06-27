<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Approval queue');
$this->section('robots', 'noindex, nofollow');
?>
<div class="card">
    <h1>Approval queue</h1>
    <p class="muted">Content held by anti-abuse rules or board approval. Approving publishes it and runs the normal counters and notifications; rejecting removes it.</p>

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
                        <form method="post" action="/mod/approvals/thread/<?= (int) $t['id'] ?>/approve"><?= $this->csrfField() ?><button class="btn" type="submit">Approve</button></form>
                        <form method="post" action="/mod/approvals/thread/<?= (int) $t['id'] ?>/reject"><?= $this->csrfField() ?><button class="btn btn-secondary" type="submit">Reject</button></form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

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
                        <form method="post" action="/mod/approvals/post/<?= (int) $p['id'] ?>/approve"><?= $this->csrfField() ?><button class="btn" type="submit">Approve</button></form>
                        <form method="post" action="/mod/approvals/post/<?= (int) $p['id'] ?>/reject"><?= $this->csrfField() ?><button class="btn btn-secondary" type="submit">Reject</button></form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
