<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * One topic row, three presentations (docs/design-system/imladris/components/forum/thread-row.card.html).
 *
 *   t                 array   the topic, joined with its board, its author and the viewer's state
 *   presentation      'default' a topic list inside a page (tags)
 *                     'board'   the canonical index at /c/{slug}; activity sits in a right-hand rail
 *                     'inbox'   the personal queue at /inbox; selection, a star toggle and a row menu are slots
 *   show_board        bool    default presentation: name the board in the meta line
 *   show_avatars      bool    render the author's monogram (default true)
 *   read_toggle       bool    board presentation: the gutter marker is a read/unread form
 *   return_to         string  where the row's forms return to
 *   order             string  inbox presentation: 'commended' prints the commend count
 *   workflow_enabled  bool    inbox presentation: the row menu offers snooze
 *
 * Topic facts (author, title, status, pinned, locked, replies, last activity) are
 * computed once and read the same in every presentation. Viewer facts (unread,
 * star, assignment, snooze) render in every presentation too: the point of the
 * queue, and a quiet marker on the index.
 */
$presentation = (string) ($presentation ?? 'default');
if (!in_array($presentation, ['default', 'board', 'inbox'], true)) {
    $presentation = 'default';
}
$boardPresentation = $presentation === 'board';
$inboxPresentation = $presentation === 'inbox';
$threadId = (int) $t['id'];
$title = (string) $t['title'];
$topicUrl = '/t/' . $threadId . '-' . (string) $t['slug'];
// Mask the starter's identity when the OP post was made anonymously.
$a = mask_author($t['author_display_name'] ?? null, $t['author_username'] ?? null, $t['author_role'] ?? 'user', !empty($t['op_is_anonymous']));
$unread = !empty($t['is_unread']);
$inboxUnread = !empty($t['is_inbox_unread']);
$starred = !empty($t['is_starred']);
$pinned = (int) ($t['is_pinned'] ?? 0) === 1;
$locked = (int) ($t['is_locked'] ?? 0) === 1;
$status = (string) ($t['status'] ?? 'open');
$statusSlug = preg_replace('/[^a-z_]/', '', $status);
$statusChip = match ($status) {
    'open' => null,
    'solved' => ['chip-solved', 'Solved'],
    'needs_answer' => ['chip-needs', 'Needs answer'],
    'decision_made' => ['chip-decision_made', 'Decision'],
    'archived' => ['chip-archived', 'Archived'],
    default => ['chip-' . $statusSlug, ucfirst(str_replace('_', ' ', $status))],
};
$activityAt = (string) (($t['last_post_at'] ?? null) ?: $t['created_at']);
$replyCount = (int) ($t['reply_count'] ?? 0);
$replyNoun = $replyCount === 1 ? 'reply' : 'replies';
$boardName = (string) ($t['board_name'] ?? $t['board_slug'] ?? '');
$rowClasses = 'thread-row' . ($presentation === 'default' ? '' : ' thread-row-' . $presentation);
if ($unread) { $rowClasses .= ' thread-unread'; }
if ($pinned) { $rowClasses .= ' thread-pinned'; }
if ($locked) { $rowClasses .= ' thread-locked'; }
if ($status !== 'open') { $rowClasses .= ' thread-status-' . $statusSlug; }
$starMarker = $starred
    ? '<span class="thread-star" title="Starred" role="img" aria-label="Starred">' . $this->partial('partials/icon', ['name' => 'commend-star']) . '</span>'
    : '';
// The gutter marker is a real control only for a signed-in reader with
// engagement live; everyone else gets the same glyph, inert.
$readToggle = ($read_toggle ?? false) && $boardPresentation;
$returnTo = (string) ($return_to ?? '');
$canWrite = ($current_user ?? null) !== null && $current_user->isActive();
$excerpt = '';
if ($inboxPresentation) {
    $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($t['excerpt_html'] ?? ''))) ?? '');
    $excerpt = mb_strimwidth($excerpt, 0, 180, '…');
}
?>
<li class="<?= $rowClasses ?>"<?php if ($inboxPresentation): ?> data-inbox-row data-thread-id="<?= $threadId ?>" data-inbox-unread="<?= $inboxUnread ? '1' : '0' ?>" data-inbox-starred="<?= $starred ? '1' : '0' ?>" data-board-slug="<?= $e($t['board_slug']) ?>"<?php elseif ($inboxUnread): ?> data-inbox-unread="1"<?php endif; ?>>
    <?php if ($inboxPresentation): ?>
        <span class="thread-row-select">
            <input id="inbox-select-<?= $threadId ?>" type="checkbox" name="thread_ids[]" value="<?= $threadId ?>" form="inbox-bulk-form" data-inbox-select aria-label="Select <?= $e($title) ?>">
        </span>
    <?php endif; ?>
    <?php if ($boardPresentation): ?>
        <?php /* The gutter is emitted read or unread, so every row shares one left edge. */ ?>
        <span class="unread-slot">
            <?php if ($readToggle): ?>
                <form class="unread-form" method="post" action="/t/<?= $threadId ?>/read">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="state" value="<?= $unread ? 'read' : 'unread' ?>">
                    <?php if ($returnTo !== ''): ?><input type="hidden" name="return" value="<?= $e($returnTo) ?>"><?php endif; ?>
                    <button class="unread-toggle" type="submit" title="<?= $unread ? 'Unread — mark as read' : 'Read — mark as unread' ?>" aria-label="<?= $unread ? 'Unread. Mark as read.' : 'Read. Mark as unread.' ?>">
                        <span class="<?= $unread ? 'unread-dot' : 'unread-ring' ?>" aria-hidden="true"></span>
                    </button>
                </form>
            <?php elseif ($unread): ?>
                <span class="unread-dot" title="Unread" role="img" aria-label="Unread"></span>
            <?php endif; ?>
        </span>
    <?php elseif ($inboxPresentation): ?>
        <span class="unread-slot"><?php if ($unread): ?><span class="unread-dot" title="Unread" role="img" aria-label="Unread"></span><?php endif; ?></span>
    <?php elseif ($unread): ?>
        <span class="unread-dot" title="Unread" role="img" aria-label="Unread"></span>
    <?php endif; ?>
    <?php if ($show_avatars ?? true): ?><?= $this->partial('partials/monogram', ['name' => $a['mono_name'], 'username' => $a['mono_seed']]) ?><?php endif; ?>
    <div class="thread-row-main">
        <?php if ($boardPresentation): ?>
            <span class="thread-title-line">
                <a class="thread-title" href="<?= $e($topicUrl) ?><?= $unread ? '?unread=1' : '' ?>"><?= $e($title) ?></a>
                <?php /* Siblings of the anchor, never children: the title alone is the
                         link's accessible name, and specs match it exactly. Pinned and
                         Locked are marks on the title; status has its own column. */ ?>
                <?php if ($pinned): ?><span class="thread-mark is-pinned" title="Pinned"><?= $this->partial('partials/icon', ['name' => 'pin']) ?>Pinned</span><?php endif; ?>
                <?php if ($locked): ?><span class="thread-mark is-locked" title="Locked"><?= $this->partial('partials/icon', ['name' => 'lock']) ?>Locked</span><?php endif; ?>
            </span>
        <?php elseif ($inboxPresentation): ?>
            <div class="thread-title-line">
                <a class="thread-title" href="<?= $e($topicUrl) ?>" data-inbox-preview-url="/inbox/preview/<?= $threadId ?>"><?= $e($title) ?></a>
                <?php /* The queue leads with why the topic is here, then the topic's own marks. */ ?>
                <span class="thread-row-chips">
                    <?php if (!empty($t['for_you_reason'])): ?><span class="chip chip-reason"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><?= $e($t['for_you_reason']) ?></span><?php endif; ?>
                    <?php if ($pinned): ?><span class="chip chip-pinned">Pinned</span><?php endif; ?>
                    <?php if ($statusChip !== null): ?><span class="chip <?= $e($statusChip[0]) ?>"><?= $e($statusChip[1]) ?></span><?php endif; ?>
                    <?php if ($locked): ?><span class="chip chip-locked">Locked</span><?php endif; ?>
                </span>
            </div>
            <?php if ($excerpt !== ''): ?><p class="thread-snippet"><?= $e($excerpt) ?></p><?php endif; ?>
        <?php else: ?>
            <div class="thread-row-chips">
                <?php if ($pinned): ?><span class="chip chip-pinned">Pinned</span><?php endif; ?>
                <?php if ($statusChip !== null): ?><span class="chip <?= $e($statusChip[0]) ?>"><?= $e($statusChip[1]) ?></span><?php endif; ?>
                <?php if ($locked): ?><span class="chip chip-locked">Locked</span><?php endif; ?>
            </div>
            <a class="thread-title" href="<?= $e($topicUrl) ?><?= $unread ? '?unread=1' : '' ?>"><?= $e($title) ?></a>
        <?php endif; ?>
        <span class="thread-meta">
            <?php if ($boardPresentation): ?>
                <span class="thread-meta-author">by <?= $e($a['label']) ?></span>
                <?php if (!empty($t['assigned_username'])): ?><span class="thread-meta-aside">assigned to @<?= $e($t['assigned_username']) ?></span><?php endif; ?>
                <?php /* The day, not the minute: a snooze is a date you are waiting on. */ ?>
                <?php if (!empty($t['snoozed_until'])): ?><span class="thread-meta-aside">snoozed until <?= $e(human_date($t['snoozed_until'])) ?></span><?php endif; ?>
            <?php elseif ($inboxPresentation): ?>
                <a href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($boardName) ?></a>
                <span>by <?= $e($a['label']) ?></span>
                <span><?= $replyCount ?> <?= $replyNoun ?></span>
                <time datetime="<?= $e(iso_datetime($activityAt)) ?>" title="<?= $e(human_datetime($activityAt)) ?>"><?= $e(relative_datetime($activityAt)) ?></time>
                <?php if (($order ?? 'active') === 'commended' && (int) ($t['commend_count'] ?? 0) > 0): ?><span class="thread-meta-commends" title="Commends"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><?= $e(number_format((int) $t['commend_count'])) ?></span><?php endif; ?>
                <?php if (!empty($t['assigned_username'])): ?><span>assigned to @<?= $e($t['assigned_username']) ?></span><?php endif; ?>
                <?php if (!empty($t['snoozed_until'])): ?><span>snoozed until <?= $e(human_date($t['snoozed_until'])) ?></span><?php endif; ?>
            <?php else: ?>
                <?php if (($show_board ?? false) && !empty($t['board_slug'])): ?><a class="thread-board" href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($boardName) ?></a> · <?php endif; ?>
                by <?= $e($a['label']) ?>
                · <?= $replyCount ?> <?= $replyNoun ?>
                · <time datetime="<?= $e(iso_datetime($activityAt)) ?>" title="<?= $e(human_datetime($activityAt)) ?>"><?= $e(relative_datetime($activityAt)) ?></time>
                <?php if (!empty($t['assigned_username'])): ?>
                    · assigned to @<?= $e($t['assigned_username']) ?>
                <?php endif; ?>
                <?php if (!empty($t['snoozed_until'])): ?>
                    · snoozed until <?= $e(human_date($t['snoozed_until'])) ?>
                <?php endif; ?>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($boardPresentation): ?>
        <?php /* The status column is emitted whether or not there is a status, so
                 the activity column never shifts between rows. */ ?>
        <span class="thread-row-status">
            <?php if ($statusChip !== null): ?>
                <span class="chip <?= $e($statusChip[0]) ?>"><?php if ($status === 'solved'): ?><?= $this->partial('partials/icon', ['name' => 'check']) ?><?php endif; ?><?= $e($statusChip[1]) ?></span>
            <?php endif; ?>
        </span>
        <?php /* Elapsed time in the column, the exact instant on the element —
                 a column is read by comparing its rows. */ ?>
        <div class="thread-row-activity">
            <time datetime="<?= $e(iso_datetime($activityAt)) ?>" title="<?= $e(human_datetime($activityAt)) ?>"><?= $e(relative_datetime($activityAt)) ?></time>
            <span class="thread-row-replies"><?= $replyCount ?> <?= $replyNoun ?></span>
        </div>
        <?php /* A reserved cell, so a starred row is the same width as its neighbours. */ ?>
        <span class="thread-row-star"><?= $starMarker ?></span>
    <?php elseif ($inboxPresentation): ?>
        <?php if ($canWrite): ?>
            <?= $this->partial('partials/star_toggle', [
                'thread_id' => $threadId,
                'starred' => $starred,
                'return_to' => $returnTo,
                'variant' => 'icon',
                'topic_title' => $title,
                'inbox_action' => true,
                'form_class' => 'thread-row-star',
            ]) ?>
        <?php else: ?>
            <span class="thread-row-star"><?= $starMarker ?></span>
        <?php endif; ?>
        <details class="thread-row-menu" data-inbox-row-menu>
            <summary aria-label="More actions for <?= $e($title) ?>"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
            <div class="thread-row-menu-panel">
                <form method="post" action="/t/<?= $threadId ?>/read" data-inbox-action="read">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="return" value="<?= $e($returnTo) ?>">
                    <input type="hidden" name="state" value="<?= $unread ? 'read' : 'unread' ?>">
                    <button type="submit"><?= $unread ? 'Mark read' : 'Mark unread' ?></button>
                </form>
                <?php if (!empty($workflow_enabled) && $canWrite): ?>
                    <?php foreach (['later_today' => 'Later today', 'tomorrow' => 'Tomorrow', 'monday' => 'Monday', 'week' => 'Next week'] as $until => $label): ?>
                        <form method="post" action="/t/<?= $threadId ?>/snooze" data-inbox-action="snooze" data-inbox-snooze="<?= $e($until) ?>">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="return" value="<?= $e($returnTo) ?>">
                            <input type="hidden" name="until" value="<?= $e($until) ?>">
                            <button type="submit">Snooze · <?= $e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                    <?php if (!empty($t['snoozed_until'])): ?>
                        <form method="post" action="/t/<?= $threadId ?>/snooze">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="return" value="<?= $e($returnTo) ?>">
                            <button type="submit">Clear snooze</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </details>
    <?php else: ?>
        <?= $starMarker ?>
    <?php endif; ?>
</li>
