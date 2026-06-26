<?php /** @var \App\Core\View $this */ ?>
<?php $author = ($t['author_display_name'] ?? '') !== '' ? $t['author_display_name'] : $t['author_username']; ?>
<li class="thread-row">
    <?= $this->partial('partials/monogram', ['name' => $author, 'username' => $t['author_username']]) ?>
    <div class="thread-row-main">
        <a class="thread-title" href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>">
            <?php if ((int) $t['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ((int) $t['is_locked'] === 1): ?><span class="badge badge-muted">Locked</span><?php endif; ?>
            <?= $e($t['title']) ?>
        </a>
        <span class="thread-meta">
            by <?= $e($author) ?>
            · <?= (int) $t['reply_count'] ?> <?= (int) $t['reply_count'] === 1 ? 'reply' : 'replies' ?>
            · <?= $e(human_datetime(($t['last_post_at'] ?? null) ?: $t['created_at'])) ?>
        </span>
    </div>
</li>
