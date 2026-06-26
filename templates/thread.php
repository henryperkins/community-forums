<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $thread['title']); ?>
<article class="thread">
    <header class="thread-head">
        <p class="breadcrumb"><a href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></p>
        <h1>
            <?php if ((int) $thread['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ((int) $thread['is_locked'] === 1): ?><span class="badge badge-muted">Locked</span><?php endif; ?>
            <?= $e($thread['title']) ?>
        </h1>
        <?php if ($is_admin): ?>
            <div class="mod-bar">
                <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit"><?= (int) $thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                </form>
                <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit"><?= (int) $thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($posts)): ?>
        <p class="muted empty">This thread has no visible posts.</p>
    <?php else: ?>
        <div class="post-stream">
            <?php foreach ($posts as $p): ?>
                <?= $this->partial('partials/post', ['p' => $p, 'thread' => $thread]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => $pages,
        'base_url' => '/t/' . (int) $thread['id'] . '-' . $thread['slug'] . '?',
    ]) ?>

    <?php if ($locked): ?>
        <div class="joinbar">This thread is locked and is not accepting replies.</div>
    <?php elseif ($can_reply): ?>
        <?= $this->partial('partials/composer', ['thread' => $thread, 'reply_errors' => $reply_errors, 'reply_old' => $reply_old]) ?>
    <?php elseif ($current_user === null): ?>
        <div class="joinbar">You're browsing as a guest — <a href="/login?next=/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">log in</a> to reply.</div>
    <?php elseif ($current_user !== null && !$current_user->isActive()): ?>
        <div class="joinbar">Your account cannot post right now.</div>
    <?php endif; ?>
</article>
