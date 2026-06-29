<?php /** @var \App\Core\View $this */ ?>
<?php
// Mask the starter's identity when the OP post was made anonymously.
$a = mask_author($t['author_display_name'] ?? null, $t['author_username'] ?? null, 'user', !empty($t['op_is_anonymous']));
$unread = !empty($t['is_unread']);
$starred = !empty($t['is_starred']);
$showBoard = ($show_board ?? false) && !empty($t['board_slug']);
$status = (string) ($t['status'] ?? 'open');
$pinned = (int) ($t['is_pinned'] ?? 0) === 1;
$locked = (int) ($t['is_locked'] ?? 0) === 1;
$statusSlug = preg_replace('/[^a-z_]/', '', $status);
$rowClasses = 'thread-row';
if ($unread) { $rowClasses .= ' thread-unread'; }
if ($pinned) { $rowClasses .= ' thread-pinned'; }
if ($locked) { $rowClasses .= ' thread-locked'; }
if ($status !== 'open') { $rowClasses .= ' thread-status-' . $statusSlug; }
?>
<li class="<?= $rowClasses ?>">
    <?php if ($unread): ?><span class="unread-dot" title="Unread" aria-label="Unread"></span><?php endif; ?>
    <?php if ($show_avatars ?? true): ?><?= $this->partial('partials/monogram', ['name' => $a['mono_name'], 'username' => $a['mono_seed']]) ?><?php endif; ?>
    <div class="thread-row-main">
        <div class="thread-row-chips">
            <?php if ($pinned): ?><span class="chip chip-pinned">Pinned</span><?php endif; ?>
            <?php if ($status === 'solved'): ?><span class="chip chip-solved">Solved</span>
            <?php elseif ($status === 'needs_answer'): ?><span class="chip chip-needs">Needs answer</span>
            <?php elseif ($status !== 'open'): ?><span class="chip chip-<?= $e($statusSlug) ?>"><?= $e(ucwords(str_replace('_', ' ', $status))) ?></span><?php endif; ?>
            <?php if ($locked): ?><span class="chip chip-locked">Locked</span><?php endif; ?>
        </div>
        <a class="thread-title" href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>">
            <?= $e($t['title']) ?>
        </a>
        <span class="thread-meta">
            <?php if ($showBoard): ?><a class="thread-board" href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($t['board_name'] ?? $t['board_slug']) ?></a> · <?php endif; ?>
            by <?= $e($a['label']) ?>
            · <?= (int) $t['reply_count'] ?> <?= (int) $t['reply_count'] === 1 ? 'reply' : 'replies' ?>
            · <?= $e(human_datetime(($t['last_post_at'] ?? null) ?: $t['created_at'])) ?>
            <?php if (!empty($t['assigned_username'])): ?>
                · assigned to @<?= $e($t['assigned_username']) ?>
            <?php endif; ?>
            <?php if (!empty($t['for_you_reason'])): ?>
                · <?= $e($t['for_you_reason']) ?>
            <?php endif; ?>
            <?php if (!empty($t['snoozed_until'])): ?>
                · snoozed until <?= $e(human_datetime($t['snoozed_until'])) ?>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($starred): ?><span class="thread-star" title="Starred" aria-label="Starred">★</span><?php endif; ?>
</li>
