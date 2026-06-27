<?php /** @var \App\Core\View $this */ ?>
<?php
// Mask the starter's identity when the OP post was made anonymously.
$a = mask_author($t['author_display_name'] ?? null, $t['author_username'] ?? null, 'user', !empty($t['op_is_anonymous']));
$unread = !empty($t['is_unread']);
$starred = !empty($t['is_starred']);
$showBoard = ($show_board ?? false) && !empty($t['board_slug']);
?>
<li class="thread-row<?= $unread ? ' thread-unread' : '' ?>">
    <?php if ($unread): ?><span class="unread-dot" title="Unread" aria-label="Unread"></span><?php endif; ?>
    <?= $this->partial('partials/monogram', ['name' => $a['mono_name'], 'username' => $a['mono_seed']]) ?>
    <div class="thread-row-main">
        <a class="thread-title" href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>">
            <?php if ($starred): ?><span class="star-marker" title="Starred">★</span><?php endif; ?>
            <?php if ((int) $t['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ((int) $t['is_locked'] === 1): ?><span class="badge badge-muted">Locked</span><?php endif; ?>
            <?= $e($t['title']) ?>
        </a>
        <span class="thread-meta">
            <?php if ($showBoard): ?><a class="thread-board" href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($t['board_name'] ?? $t['board_slug']) ?></a> · <?php endif; ?>
            by <?= $e($a['label']) ?>
            · <?= (int) $t['reply_count'] ?> <?= (int) $t['reply_count'] === 1 ? 'reply' : 'replies' ?>
            · <?= $e(human_datetime(($t['last_post_at'] ?? null) ?: $t['created_at'])) ?>
        </span>
    </div>
</li>
