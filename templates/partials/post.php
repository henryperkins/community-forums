<?php /** @var \App\Core\View $this */ ?>
<?php
$owner = $current_user !== null && $current_user->id() === (int) $p['user_id'];
$admin = $current_user?->isAdmin() ?? false;
$canModerate = $admin && !$owner;
$author = ($p['author_display_name'] ?? '') !== '' ? $p['author_display_name'] : $p['author_username'];
?>
<div class="post" id="p<?= (int) $p['id'] ?>">
    <?= $this->partial('partials/monogram', ['name' => $author, 'username' => $p['author_username']]) ?>
    <div class="post-main">
        <div class="post-head">
            <a class="post-author" href="/u/<?= $e($p['author_username']) ?>"><?= $e($author) ?></a>
            <?php if ((int) $p['is_op'] === 1): ?><span class="badge">OP</span><?php endif; ?>
            <?php if (($p['author_role'] ?? 'user') === 'admin'): ?><span class="badge badge-staff">Staff</span><?php endif; ?>
            <span class="post-time"><?= $e(human_datetime($p['created_at'])) ?></span>
            <?php if (!empty($p['edited_at'])): ?><span class="muted post-edited">(edited)</span><?php endif; ?>
        </div>
        <div class="post-body">
            <?php if (($p['body_html'] ?? '') !== ''): ?>
                <?= $p['body_html'] /* pre-sanitised at write time */ ?>
            <?php else: ?>
                <p><?= $e($p['body']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($owner || $canModerate): ?>
            <div class="post-actions">
                <?php if ($owner): ?>
                    <details class="post-edit">
                        <summary class="linkbtn">Edit</summary>
                        <form method="post" action="/posts/<?= (int) $p['id'] ?>/edit" class="composer">
                            <?= $this->csrfField() ?>
                            <textarea name="body" rows="4" class="composer-input" maxlength="20000" required><?= $e($p['body']) ?></textarea>
                            <button class="btn btn-small" type="submit">Save changes</button>
                        </form>
                    </details>
                    <form method="post" action="/posts/<?= (int) $p['id'] ?>/delete" class="inline">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn danger" type="submit">Delete</button>
                    </form>
                <?php elseif ($canModerate): ?>
                    <details class="post-edit">
                        <summary class="linkbtn danger">Remove (mod)</summary>
                        <form method="post" action="/posts/<?= (int) $p['id'] ?>/delete" class="composer">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" class="input" placeholder="Reason (required)" maxlength="255" required>
                            <button class="btn btn-small danger" type="submit">Remove post</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
