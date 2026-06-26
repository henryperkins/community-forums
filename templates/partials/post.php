<?php /** @var \App\Core\View $this */ ?>
<?php
$owner = $current_user !== null && $current_user->id() === (int) $p['user_id'];
$admin = $current_user?->isAdmin() ?? false;
$canModerate = $admin && !$owner;
$author = ($p['author_display_name'] ?? '') !== '' ? $p['author_display_name'] : $p['author_username'];
?>
<?php $accepted = $accepted ?? false; ?>
<div class="post<?= $accepted ? ' post-accepted' : '' ?>" id="p<?= (int) $p['id'] ?>">
    <?= $this->partial('partials/monogram', ['name' => $author, 'username' => $p['author_username']]) ?>
    <div class="post-main">
        <div class="post-head">
            <a class="post-author" href="/u/<?= $e($p['author_username']) ?>"><?= $e($author) ?></a>
            <?php if ((int) $p['is_op'] === 1): ?><span class="badge">OP</span><?php endif; ?>
            <?php if (($p['author_role'] ?? 'user') === 'admin'): ?><span class="badge badge-staff">Staff</span><?php endif; ?>
            <?php if ($accepted): ?><span class="badge badge-solved" title="Accepted answer">✓ Accepted answer</span><?php endif; ?>
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
        <?php
        // Reactions (P2-02). $counts: emoji=>n; $mine: emoji the viewer added.
        $engagement = $engagement ?? false;
        $counts = $counts ?? [];
        $mine = $mine ?? [];
        $allowed = $allowed_emoji ?? [];
        if ($engagement):
            $threadPath = '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
        ?>
        <div class="reactions" data-post="<?= (int) $p['id'] ?>">
            <?php foreach ($counts as $emoji => $n): ?>
                <?php $on = in_array($emoji, $mine, true); ?>
                <?php if ($current_user !== null): ?>
                    <form class="reaction-form inline" method="post" action="/posts/<?= (int) $p['id'] ?>/react">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="emoji" value="<?= $e($emoji) ?>">
                        <button type="submit" class="reaction<?= $on ? ' reaction-on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"
                                title="<?= $on ? 'Remove your reaction' : 'React' ?>"><?= $e($emoji) ?> <span class="reaction-n"><?= (int) $n ?></span></button>
                    </form>
                <?php else: ?>
                    <span class="reaction reaction-static"><?= $e($emoji) ?> <span class="reaction-n"><?= (int) $n ?></span></span>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($current_user !== null && $allowed !== []): ?>
                <details class="reaction-add">
                    <summary class="reaction reaction-pick" title="Add a reaction">＋</summary>
                    <div class="reaction-menu">
                        <?php foreach ($allowed as $emoji): ?>
                            <form class="reaction-form inline" method="post" action="/posts/<?= (int) $p['id'] ?>/react">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="emoji" value="<?= $e($emoji) ?>">
                                <button type="submit" class="reaction"><?= $e($emoji) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($can_mark_solved) && empty($accepted) && (int) $p['is_op'] === 0): ?>
            <form class="inline solved-action" method="post" action="/posts/<?= (int) $p['id'] ?>/accept">
                <?= $this->csrfField() ?>
                <button class="linkbtn" type="submit">✓ Accept as answer</button>
            </form>
        <?php endif; ?>
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
        <?php if (!empty($features['moderation_queue']) && $current_user !== null && !$owner): ?>
            <div class="post-report">
                <details>
                    <summary class="linkbtn muted">Report</summary>
                    <form method="post" action="/posts/<?= (int) $p['id'] ?>/report" class="composer">
                        <?= $this->csrfField() ?>
                        <select name="reason_code" class="input input-small">
                            <?php foreach (['spam', 'harassment', 'off_topic', 'nsfw', 'illegal', 'other'] as $rc): ?>
                                <option value="<?= $e($rc) ?>"><?= $e(ucfirst(str_replace('_', ' ', $rc))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="reason" class="input" placeholder="Details (optional)" maxlength="255">
                        <label class="checkline"><input type="checkbox" name="notify_reporter" value="1"> Notify me of the outcome</label>
                        <button class="btn btn-small" type="submit">Submit report</button>
                    </form>
                </details>
            </div>
        <?php endif; ?>
    </div>
</div>
