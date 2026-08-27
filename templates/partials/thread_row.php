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

// Board presentation states status once, as a pill in its own reserved column
// AFTER the title — so the title is still the first thing read, and the column
// stays put whether or not a topic has a status. Pinned and Locked are not
// status: they are marks on the title itself and ride the title line.
$statusPill = null;
if ($boardPresentation) {
    $statusPill = match ($status) {
        'solved' => ['chip-solved', 'Solved', 'check'],
        'needs_answer' => ['chip-needs', 'Needs answer', null],
        'decision_made' => ['chip-decision_made', 'Decision', null],
        'archived' => ['chip-archived', 'Archived', null],
        default => null,
    };
}
// The gutter marker is a real control only for a signed-in reader with
// engagement live; everyone else gets the same glyph, inert.
$readToggle = ($read_toggle ?? false) && $boardPresentation;
$readReturn = (string) ($return_to ?? '');
?>
<li class="<?= $rowClasses ?>"<?= $inboxUnread ? ' data-inbox-unread="1"' : '' ?>>
    <?php if ($boardPresentation): ?>
        <?php /* The gutter is emitted read or unread, so every row shares one left edge. */ ?>
        <span class="unread-slot">
            <?php if ($readToggle): ?>
                <form class="unread-form" method="post" action="/t/<?= (int) $t['id'] ?>/read">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="state" value="<?= $unread ? 'read' : 'unread' ?>">
                    <?php if ($readReturn !== ''): ?><input type="hidden" name="return" value="<?= $e($readReturn) ?>"><?php endif; ?>
                    <button class="unread-toggle" type="submit" title="<?= $unread ? 'Unread — mark as read' : 'Read — mark as unread' ?>" aria-label="<?= $unread ? 'Unread. Mark as read.' : 'Read. Mark as unread.' ?>">
                        <span class="<?= $unread ? 'unread-dot' : 'unread-ring' ?>" aria-hidden="true"></span>
                    </button>
                </form>
            <?php elseif ($unread): ?>
                <span class="unread-dot" title="Unread" aria-label="Unread"></span>
            <?php endif; ?>
        </span>
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
            <?php /* Siblings of the anchor, never children: the title alone is the
                     link's accessible name, and specs match it exactly. */ ?>
            <?php if ($pinned): ?><span class="thread-mark is-pinned" title="Pinned"><?= $this->partial('partials/icon', ['name' => 'pin']) ?>Pinned</span><?php endif; ?>
            <?php if ($locked): ?><span class="thread-mark is-locked" title="Locked"><?= $this->partial('partials/icon', ['name' => 'lock']) ?>Locked</span><?php endif; ?>
            </span>
        <?php endif; ?>
        <span class="thread-meta">
            <?php if ($boardPresentation): ?>
                <span class="thread-meta-author">by <?= $e($a['label']) ?></span>
                <?php if (!empty($t['assigned_username'])): ?><span class="thread-meta-aside">assigned to @<?= $e($t['assigned_username']) ?></span><?php endif; ?>
                <?php /* The day, not the minute: a snooze is a date you are waiting on. */ ?>
                <?php if (!empty($t['snoozed_until'])): ?><span class="thread-meta-aside">snoozed until <?= $e(human_date($t['snoozed_until'])) ?></span><?php endif; ?>
            <?php else: ?>
                <?php if ($showBoard): ?><a class="thread-board" href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($t['board_name'] ?? $t['board_slug']) ?></a> · <?php endif; ?>
                by <?= $e($a['label']) ?>
                · <?= $replyCount ?> <?= $replyNoun ?>
                · <?= $e($activityLabel) ?>
                <?php if (!empty($t['assigned_username'])): ?>
                    · assigned to @<?= $e($t['assigned_username']) ?>
                <?php endif; ?>
                <?php if (!empty($t['for_you_reason'])): ?>
                    · <?= $e($t['for_you_reason']) ?>
                <?php endif; ?>
                <?php if (!empty($t['snoozed_until'])): ?>
                    · snoozed until <?= $e(human_datetime($t['snoozed_until'])) ?>
                <?php endif; ?>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($boardPresentation): ?>
        <?php /* The pill column is emitted whether or not there is a status, so
                 the activity column never shifts between rows. */ ?>
        <span class="thread-row-status">
            <?php if ($statusPill !== null): ?>
                <span class="chip <?= $statusPill[0] ?>"><?php if ($statusPill[2] !== null): ?><?= $this->partial('partials/icon', ['name' => $statusPill[2]]) ?><?php endif; ?><?= $e($statusPill[1]) ?></span>
            <?php endif; ?>
        </span>
        <?php /* Elapsed time in the column, the exact instant on the element —
                 a column is read by comparing its rows. */ ?>
        <div class="thread-row-activity">
            <time datetime="<?= $e($activityAt) ?>" title="<?= $e($activityLabel) ?>"><?= $e(relative_datetime($activityAt)) ?></time>
            <span class="thread-row-replies"><?= $replyCount ?> <?= $replyNoun ?></span>
        </div>
        <?php /* A reserved cell, so a starred row is the same width as its neighbours. */ ?>
        <span class="thread-row-star"><?php if ($starred): ?><span class="thread-star" title="Starred" aria-label="Starred">★</span><?php endif; ?></span>
    <?php endif; ?>
    <?php if ($starred && !$boardPresentation): ?><span class="thread-star" title="Starred" aria-label="Starred">★</span><?php endif; ?>
</li>
