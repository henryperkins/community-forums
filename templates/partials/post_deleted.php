<?php /** @var \App\Core\View $this */ ?>
<?php
// Restorable stub for a soft-deleted reply (ADMIN §3.3): rendered only from the
// staff with-deleted list variant. The byline stays masked for anonymous posts —
// deletion never unmasks; reveal remains its own audited action.
$isAnon = (int) ($p['is_anonymous'] ?? 0) === 1;
$a = mask_author($p['author_display_name'] ?? null, $p['author_username'] ?? null, $p['author_role'] ?? 'user', $isAnon);
?>
<article class="post post-deleted" id="p<?= (int) $p['id'] ?>">
    <div class="post-main">
        <div class="post-head">
            <span class="post-author"><?= $e($a['label']) ?></span>
            <span class="badge">Removed by a warden</span>
            <?php if (!empty($p['deleted_at'])): ?>
                <span class="post-time"><?= $e(human_datetime((string) $p['deleted_at'])) ?></span>
            <?php endif; ?>
        </div>
        <details class="post-native-disclosure post-deleted-disclosure">
            <summary class="linkbtn">Show removed content</summary>
            <div class="post-body formatted-content post-deleted-body"><?= $p['body_html'] ?></div>
        </details>
        <?php if (!empty($can_restore_posts)): ?>
            <form method="post" action="/mod/p/<?= (int) $p['id'] ?>/restore" class="post-deleted-restore">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit" aria-label="Restore removed reply #p<?= (int) $p['id'] ?>">Restore</button>
            </form>
        <?php endif; ?>
    </div>
</article>
