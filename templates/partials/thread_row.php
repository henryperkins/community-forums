<?php /** @var \App\Core\View $this */ ?>
<?php
// Mask the starter's identity when the OP post was made anonymously.
$a = mask_author($t['author_display_name'] ?? null, $t['author_username'] ?? null, 'user', !empty($t['op_is_anonymous']));
$unread = !empty($t['is_unread']);
$inboxUnread = !empty($t['is_inbox_unread']);
$starred = !empty($t['is_starred']);
$showBoard = ($show_board ?? false) && !empty($t['board_slug']);
$status = (string) ($t['status'] ?? 'open');
$pinned = (int) ($t['is_pinned'] ?? 0) === 1;
$locked = (int) ($t['is_locked'] ?? 0) === 1;
$statusSlug = preg_replace('/[^a-z_]/', '', $status);
$presentation = (string) ($presentation ?? 'default');
$boardPresentation = $presentation === 'board';
$activityAt = (string) (($t['last_post_at'] ?? null) ?: $t['created_at']);
$activityLabel = human_datetime($activityAt);
$replyCount = (int) $t['reply_count'];
$replyNoun = $replyCount === 1 ? 'reply' : 'replies';
$rowClasses = 'thread-row' . ($boardPresentation ? ' thread-row-board' : '');
if ($unread) { $rowClasses .= ' thread-unread'; }
if ($pinned) { $rowClasses .= ' thread-pinned'; }
if ($locked) { $rowClasses .= ' thread-locked'; }
if ($status !== 'open') { $rowClasses .= ' thread-status-' . $statusSlug; }

// Board presentation states status as a coloured word on the meta line rather
// than a chip stacked above the title, so the title stays the first thing read.
$flags = [];
if ($boardPresentation) {
    if ($status === 'solved') {
        $flags[] = ['is-solved', 'Solved'];
    } elseif ($status === 'needs_answer') {
        $flags[] = ['is-needs', 'Needs answer'];
    } elseif ($status !== 'open') {
        $flags[] = ['is-' . $statusSlug, ucwords(str_replace('_', ' ', $status))];
    }
    if ($pinned) { $flags[] = ['is-pinned', 'Pinned']; }
    if ($locked) { $flags[] = ['is-locked', 'Locked']; }
}
?>
<li class="<?= $rowClasses ?>"<?= $inboxUnread ? ' data-inbox-unread="1"' : '' ?>>
    <?php if ($boardPresentation): ?>
        <?php /* The gutter is emitted read or unread, so every row shares one left edge. */ ?>
        <span class="unread-slot"><?php if ($unread): ?><span class="unread-dot" title="Unread" aria-label="Unread"></span><?php endif; ?></span>
    <?php elseif ($unread): ?>
        <span class="unread-dot" title="Unread" aria-label="Unread"></span>
    <?php endif; ?>
    <?php if ($show_avatars ?? true): ?><?= $this->partial('partials/monogram', ['name' => $a['mono_name'], 'username' => $a['mono_seed']]) ?><?php endif; ?>
    <div class="thread-row-main">
        <?php if (!$boardPresentation): ?>
            <div class="thread-row-chips">
                <?php if ($pinned): ?><span class="chip chip-pinned">Pinned</span><?php endif; ?>
                <?php if ($status === 'solved'): ?><span class="chip chip-solved">Solved</span>
                <?php elseif ($status === 'needs_answer'): ?><span class="chip chip-needs">Needs answer</span>
                <?php elseif ($status !== 'open'): ?><span class="chip chip-<?= $e($statusSlug) ?>"><?= $e(ucwords(str_replace('_', ' ', $status))) ?></span><?php endif; ?>
                <?php if ($locked): ?><span class="chip chip-locked">Locked</span><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($boardPresentation): ?><span class="thread-title-line"><?php endif; ?>
        <a class="thread-title" href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>">
            <?= $e($t['title']) ?>
        </a>
        <?php if ($boardPresentation): ?>
            <?php if ($starred): ?><span class="thread-star" title="Starred" aria-label="Starred">★</span><?php endif; ?>
            </span>
        <?php endif; ?>
        <span class="thread-meta">
            <?php foreach ($flags as [$flagClass, $flagLabel]): ?><span class="thread-flag <?= $flagClass ?>"><?= $e($flagLabel) ?></span><span class="sep" aria-hidden="true">·</span> <?php endforeach; ?>
            <?php if ($showBoard): ?><a class="thread-board" href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($t['board_name'] ?? $t['board_slug']) ?></a> · <?php endif; ?>
            by <?= $e($a['label']) ?>
            <?php if (!$boardPresentation): ?>
                · <?= $replyCount ?> <?= $replyNoun ?>
                · <?= $e($activityLabel) ?>
            <?php endif; ?>
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
    <?php if ($boardPresentation): ?>
        <?php /* The glyph is the scannable value; the count keeps its noun for screen readers. */ ?>
        <div class="thread-row-activity">
            <span class="thread-row-replies"><span aria-hidden="true"><?= $replyCount === 0 ? '—' : $replyCount ?></span><span class="sr-only"><?= $replyCount ?> <?= $replyNoun ?></span></span>
            <time datetime="<?= $e($activityAt) ?>"><?= $e($activityLabel) ?></time>
        </div>
    <?php endif; ?>
    <?php if ($starred && !$boardPresentation): ?><span class="thread-star" title="Starred" aria-label="Starred">★</span><?php endif; ?>
</li>
