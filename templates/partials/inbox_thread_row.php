<?php /** @var \App\Core\View $this */ ?>
<?php
$author = mask_author(
    $t['author_display_name'] ?? null,
    $t['author_username'] ?? null,
    $t['author_role'] ?? 'user',
    !empty($t['op_is_anonymous']),
);
$threadId = (int) $t['id'];
$unread = !empty($t['is_unread']);
$inboxUnread = !empty($t['is_inbox_unread']);
$starred = !empty($t['is_starred']);
$status = (string) ($t['status'] ?? 'open');
$statusClass = preg_replace('/[^a-z_]/', '', $status);
$activityAt = (string) (($t['last_post_at'] ?? null) ?: $t['created_at']);
$excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($t['excerpt_html'] ?? ''))) ?? '');
$excerpt = mb_strimwidth($excerpt, 0, 180, '…');
$replyCount = (int) ($t['reply_count'] ?? 0);
?>
<li class="inbox-thread-row<?= $unread ? ' is-unread' : '' ?><?= $status !== 'open' ? ' thread-status-' . $e($statusClass) : '' ?><?= !empty($t['is_pinned']) ? ' is-pinned' : '' ?><?= !empty($t['is_locked']) ? ' is-locked' : '' ?>" data-inbox-row data-thread-id="<?= $threadId ?>" data-inbox-unread="<?= $inboxUnread ? '1' : '0' ?>" data-inbox-starred="<?= $starred ? '1' : '0' ?>">
    <span class="inbox-row-select">
        <input id="inbox-select-<?= $threadId ?>" type="checkbox" name="thread_ids[]" value="<?= $threadId ?>" form="inbox-bulk-form" data-inbox-select aria-label="Select <?= $e($t['title']) ?>">
    </span>
    <span class="unread-slot"><?php if ($unread): ?><span class="unread-dot" title="Unread" role="img" aria-label="Unread"></span><?php endif; ?></span>
    <?php if ($show_avatars ?? true): ?><?= $this->partial('partials/monogram', ['name' => $author['mono_name'], 'username' => $author['mono_seed']]) ?><?php endif; ?>
    <div class="inbox-row-main">
        <div class="inbox-row-heading">
            <a class="inbox-row-title" href="/t/<?= $threadId ?>-<?= $e($t['slug']) ?>" data-inbox-preview-url="/inbox/preview/<?= $threadId ?>"><?= $e($t['title']) ?></a>
            <span class="inbox-row-chips">
                <?php if (!empty($t['for_you_reason'])): ?><span class="chip chip-reason"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><?= $e($t['for_you_reason']) ?></span><?php endif; ?>
                <?php if (!empty($t['is_pinned'])): ?><span class="chip chip-pinned">Pinned</span><?php endif; ?>
                <?php if ($status === 'solved'): ?><span class="chip chip-solved">Solved</span><?php endif; ?>
                <?php if ($status === 'needs_answer'): ?><span class="chip chip-needs">Needs answer</span><?php endif; ?>
                <?php if ($status === 'decision_made'): ?><span class="chip chip-decision_made">Decision</span><?php endif; ?>
                <?php if (!empty($t['is_locked'])): ?><span class="chip chip-locked">Locked</span><?php endif; ?>
            </span>
        </div>
        <?php if ($excerpt !== ''): ?><p class="inbox-row-snippet"><?= $e($excerpt) ?></p><?php endif; ?>
        <span class="inbox-row-meta">
            <a href="/c/<?= $e($t['board_slug']) ?>"><span class="hash">#</span><?= $e($t['board_name']) ?></a>
            <span>by <?= $e($author['label']) ?></span>
            <span><?= $replyCount ?> <?= $replyCount === 1 ? 'reply' : 'replies' ?></span>
            <time datetime="<?= $e(iso_datetime($activityAt)) ?>" title="<?= $e(human_datetime($activityAt)) ?>"><?= $e(relative_datetime($activityAt)) ?></time>
            <?php if (($order ?? 'active') === 'commended' && (int) ($t['commend_count'] ?? 0) > 0): ?><span class="inbox-row-commends" title="Commends"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><?= $e(number_format((int) $t['commend_count'])) ?></span><?php endif; ?>
            <?php if (!empty($t['assigned_username'])): ?><span>assigned to @<?= $e($t['assigned_username']) ?></span><?php endif; ?>
            <?php if (!empty($t['snoozed_until'])): ?><span>snoozed until <?= $e(human_date($t['snoozed_until'])) ?></span><?php endif; ?>
        </span>
    </div>

    <?php if ($current_user !== null && $current_user->isActive()): ?>
        <form class="inbox-row-star" method="post" action="/t/<?= $threadId ?>/star" data-inbox-action="star">
            <?= $this->csrfField() ?>
            <input type="hidden" name="return" value="<?= $e($return_to) ?>">
            <button type="submit" aria-pressed="<?= $starred ? 'true' : 'false' ?>" title="<?= $starred ? 'Remove star' : 'Star this topic' ?>"><?= $this->partial('partials/icon', ['name' => $starred ? 'star-filled' : 'star']) ?></button>
        </form>
    <?php elseif ($starred): ?><span class="thread-star" title="Starred" aria-label="Starred"><?= $this->partial('partials/icon', ['name' => 'star-filled']) ?></span><?php endif; ?>

    <details class="inbox-row-menu" data-inbox-row-menu>
        <summary aria-label="More actions for <?= $e($t['title']) ?>"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
        <div class="inbox-row-menu-panel">
            <form method="post" action="/t/<?= $threadId ?>/read" data-inbox-action="read">
                <?= $this->csrfField() ?>
                <input type="hidden" name="return" value="<?= $e($return_to) ?>">
                <input type="hidden" name="state" value="<?= $unread ? 'read' : 'unread' ?>">
                <button type="submit"><?= $unread ? 'Mark read' : 'Mark unread' ?></button>
            </form>
            <?php if (!empty($workflow_enabled) && $current_user !== null && $current_user->isActive()): ?>
                <?php foreach (['later_today' => 'Later today', 'tomorrow' => 'Tomorrow', 'monday' => 'Monday', 'week' => 'Next week'] as $until => $label): ?>
                    <form method="post" action="/t/<?= $threadId ?>/snooze" data-inbox-action="snooze" data-inbox-snooze="<?= $e($until) ?>">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="return" value="<?= $e($return_to) ?>">
                        <input type="hidden" name="until" value="<?= $e($until) ?>">
                        <button type="submit">Snooze · <?= $e($label) ?></button>
                    </form>
                <?php endforeach; ?>
                <?php if (!empty($t['snoozed_until'])): ?>
                    <form method="post" action="/t/<?= $threadId ?>/snooze">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="return" value="<?= $e($return_to) ?>">
                        <button type="submit">Clear snooze</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </details>
</li>
