<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', $site_name);
$this->section('route', 'boards');
$pane = (string) ($pane ?? 'boards');
$sort = (string) ($directory_sort ?? 'category');
$peek = (int) ($directory_peek ?? 3);
$availablePanes = is_array($available_panes ?? null) ? $available_panes : ['boards' => true];
$groups = is_array($directory_groups ?? null) ? $directory_groups : [];
$totals = is_array($directory_totals ?? null) ? $directory_totals : ['boards' => 0, 'topics' => 0, 'posts' => 0];
$sortLabels = [
    'category' => 'By category',
    'active' => 'Active',
    'newest' => 'Newest',
    'unanswered' => 'Unanswered',
    'top' => 'Top',
    'solved' => 'Solved',
];
$orderNotes = [
    'category' => 'Grouped by category',
    'active' => 'Ranked by last reply',
    'newest' => 'Ranked by newest topic',
    'unanswered' => 'Boards with the most unanswered topics first',
    'top' => 'Ranked by most-commended topic',
    'solved' => 'Boards with the most recently settled topics first',
];
$viewUrl = static function (array $changes = []) use ($sort, $peek): string {
    return '/?' . http_build_query(array_merge([
        'pane' => 'boards',
        'sort' => $sort,
        'peek' => $peek,
    ], $changes));
};
$paneLabels = ['boards' => 'Boards', 'tags' => 'Tags', 'notices' => 'Notices', 'connections' => 'Connections'];
// The design's notice names the topic it is about — "Galadriel mentioned you in
// 'Evaluations as ritual, not gate'" (BoardIndex.dc.html:448). recent() already
// selects thread_title, so the verb and the topic are returned separately and
// the topic is rendered in its own element rather than concatenated into one
// string: only the topic is quoted, and only the topic changes weight when the
// notice is unread.
$notificationVerb = static function (array $notice): string {
    $actor = ($notice['actor_display_name'] ?? '') !== ''
        ? (string) $notice['actor_display_name']
        : (string) ($notice['actor_username'] ?? 'Someone');
    $named = ($notice['thread_title'] ?? '') !== '';
    return match ((string) ($notice['type'] ?? '')) {
        'reply' => $actor . ($named ? ' replied to' : ' replied'),
        'new_thread' => $actor . ($named ? ' opened' : ' started a topic'),
        'new_post' => $actor . ($named ? ' posted in' : ' posted'),
        'mention' => $actor . ($named ? ' mentioned you in' : ' mentioned you'),
        'reaction' => $actor . ' commended your post',
        'follow' => $actor . ' followed you',
        'badge' => 'You earned a badge',
        'solved' => 'Your answer was accepted' . ($named ? ' in' : ''),
        'dm' => $actor . ' sent you a message',
        'mod' => 'A moderator action affects you',
        'announcement' => 'A new announcement was published',
        default => 'A new notice arrived',
    };
};
?>
<div class="read-main read-pad board-index" data-directory-pane="<?= $e($pane) ?>">
    <nav class="forum-directory__tabs" aria-label="Board index panes">
        <?php foreach ($paneLabels as $paneKey => $label): ?>
            <?php if (empty($availablePanes[$paneKey])) { continue; } ?>
            <a href="/?pane=<?= $e($paneKey) ?>"<?= $pane === $paneKey ? ' aria-current="page"' : '' ?>>
                <?= $e($label) ?>
                <?php if ($paneKey === 'notices' && (int) ($notification_unread ?? 0) > 0): ?><span class="directory-tab-dot"><span class="sr-only">Unread notices</span></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($pane === 'boards'): ?>
        <section class="forum-directory" data-directory-sort="<?= $e($sort) ?>" data-directory-peek="<?= $peek ?>">
            <header class="forum-directory__hero">
                <h1>Every board in the valley</h1>
                <p>Browse the boards the council keeps and pick one to see its topics. Your own cross-board queue is the <a href="/inbox">inbox</a>.</p>
                <p class="forum-directory__stats" aria-label="Forum totals">
                    <span data-forum-total="boards"><?= (int) $totals['boards'] ?> board<?= (int) $totals['boards'] === 1 ? '' : 's' ?></span>
                    <span data-forum-total="topics"><?= (int) $totals['topics'] ?> topic<?= (int) $totals['topics'] === 1 ? '' : 's' ?></span>
                    <span data-forum-total="posts"><?= (int) $totals['posts'] ?> post<?= (int) $totals['posts'] === 1 ? '' : 's' ?></span>
                </p>
            </header>

            <div class="directory-viewbar" data-viewbar="wide">
                <span class="directory-viewbar__label">Viewing</span>
                <div class="directory-viewbar__choices" role="group" aria-label="Order">
                    <?php foreach ($sortLabels as $sortKey => $label): ?>
                        <?php $return = $viewUrl(['sort' => $sortKey]); ?>
                        <?php if ($current_user !== null): ?>
                            <form method="post" action="/settings/member-surfaces">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="directory_sort" value="<?= $e($sortKey) ?>">
                                <input type="hidden" name="return" value="<?= $e($return) ?>">
                                <button type="submit" data-directory-sort-option="<?= $e($sortKey) ?>" aria-pressed="<?= $sort === $sortKey ? 'true' : 'false' ?>"><?= $e($label) ?></button>
                            </form>
                        <?php else: ?>
                            <a href="<?= $e($return) ?>" data-directory-sort-option="<?= $e($sortKey) ?>"<?= $sort === $sortKey ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="directory-viewbar__peek" role="group" aria-label="Peek">
                    <span>Peek</span>
                    <?php foreach ([0, 3, 5] as $peekValue): ?>
                        <?php $return = $viewUrl(['peek' => $peekValue]); ?>
                        <?php if ($current_user !== null): ?>
                            <form method="post" action="/settings/member-surfaces">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="directory_peek" value="<?= $peekValue ?>">
                                <input type="hidden" name="return" value="<?= $e($return) ?>">
                                <button type="submit" data-directory-peek-option="<?= $peekValue ?>" aria-pressed="<?= $peek === $peekValue ? 'true' : 'false' ?>"><?= $peekValue === 0 ? 'Off' : $peekValue ?></button>
                            </form>
                        <?php else: ?>
                            <a href="<?= $e($return) ?>" data-directory-peek-option="<?= $peekValue ?>"<?= $peek === $peekValue ? ' aria-current="true"' : '' ?>><?= $peekValue === 0 ? 'Off' : $peekValue ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <span class="directory-viewbar__density"><?= ($appearance['density'] ?? 'comfortable') === 'compact' ? 'Compact' : 'Comfortable' ?> rows <a href="/settings/appearance">change</a></span>
            </div>

            <details class="directory-viewbar-mobile" data-viewbar="narrow">
                <summary>Viewing <strong><?= $e($sortLabels[$sort] ?? $sortLabels['category']) ?></strong><?= $peek > 0 ? ' · peek ' . $peek : ' · no peek' ?></summary>
                <div class="directory-viewbar-mobile-panel">
                    <span class="directory-viewbar__label">Order</span>
                    <div class="directory-viewbar__choices" role="group" aria-label="Order">
                        <?php foreach ($sortLabels as $sortKey => $label): ?>
                            <?php $return = $viewUrl(['sort' => $sortKey]); ?>
                            <?php if ($current_user !== null): ?>
                                <form method="post" action="/settings/member-surfaces">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="directory_sort" value="<?= $e($sortKey) ?>">
                                    <input type="hidden" name="return" value="<?= $e($return) ?>">
                                    <button type="submit" data-directory-sort-option="<?= $e($sortKey) ?>" aria-pressed="<?= $sort === $sortKey ? 'true' : 'false' ?>"><?= $e($label) ?></button>
                                </form>
                            <?php else: ?>
                                <a href="<?= $e($return) ?>" data-directory-sort-option="<?= $e($sortKey) ?>"<?= $sort === $sortKey ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <span class="directory-viewbar__label">Peek</span>
                    <div class="directory-viewbar__peek" role="group" aria-label="Peek">
                        <?php foreach ([0, 3, 5] as $peekValue): ?>
                            <?php $return = $viewUrl(['peek' => $peekValue]); ?>
                            <?php if ($current_user !== null): ?>
                                <form method="post" action="/settings/member-surfaces">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="directory_peek" value="<?= $peekValue ?>">
                                    <input type="hidden" name="return" value="<?= $e($return) ?>">
                                    <button type="submit" data-directory-peek-option="<?= $peekValue ?>" aria-pressed="<?= $peek === $peekValue ? 'true' : 'false' ?>"><?= $peekValue === 0 ? 'Off' : $peekValue ?></button>
                                </form>
                            <?php else: ?>
                                <a href="<?= $e($return) ?>" data-directory-peek-option="<?= $peekValue ?>"<?= $peek === $peekValue ? ' aria-current="true"' : '' ?>><?= $peekValue === 0 ? 'Off' : $peekValue ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <a class="directory-viewbar-mobile-settings" href="/settings/appearance">Row appearance settings</a>
                </div>
            </details>

            <p class="directory-order-note"><?= $e($orderNotes[$sort] ?? $orderNotes['category']) ?> · <?= (int) $totals['boards'] ?> board<?= (int) $totals['boards'] === 1 ? '' : 's' ?> · the same order every member sees.</p>
            <?php if ($current_user === null): ?>
                <p class="directory-guest-note">Order and peek are yours to set as a guest. <a href="/login?next=%2F">Log in</a> to have this view remembered, and to keep a queue of your own.</p>
            <?php endif; ?>

            <?php if ((int) $totals['boards'] === 0): ?>
                <p class="muted empty">No boards have been created yet.<?php if ($current_user !== null && $current_user->isAdmin()): ?> <a href="/admin/structure">Create one in the admin console.</a><?php endif; ?></p>
            <?php else: ?>
                <div class="forum-directory__groups"<?= $sort !== 'category' ? ' data-directory-ranked' : '' ?>>
                    <?php foreach ($groups as $group): ?>
                        <?php if (empty($group['boards'])) { continue; } ?>
                        <section class="forum-directory__group"<?php if (!empty($group['show_heading'])): ?> data-directory-category="<?= $e((string) $group['category']['name']) ?>"<?php endif; ?>>
                            <?php if (!empty($group['show_heading'])): ?>
                                <h2><span><?= $e((string) $group['category']['name']) ?></span><span aria-hidden="true"></span></h2>
                            <?php endif; ?>
                            <div class="forum-directory__boards">
                                <?php foreach ($group['boards'] as $board): ?>
                                    <?= $this->partial('partials/directory_board_row', [
                                        'board' => $board,
                                        'directory_sort' => $sort,
                                        'directory_peek' => $peek,
                                    ]) ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($pane === 'tags'): ?>
        <section class="directory-light-pane directory-tags-pane">
            <h1>Tags</h1>
            <p>A tag crosses boards; a board does not cross tags.</p>
            <?php if (empty($tags)): ?>
                <p class="muted empty">No public tags are available yet.</p>
            <?php else: ?>
                <ul class="directory-tag-list">
                    <?php foreach ($tags as $tag): ?>
                        <li><a href="/tags/<?= $e((string) $tag['slug']) ?>" data-directory-tag="<?= $e((string) $tag['slug']) ?>"><span><?= $e((string) $tag['name']) ?></span><span><?= (int) $tag['thread_count'] ?> topic<?= (int) $tag['thread_count'] === 1 ? '' : 's' ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($pane === 'notices'): ?>
        <section class="directory-light-pane directory-notices-pane">
            <header class="directory-pane-heading">
                <h1>Notices</h1>
                <?php if ($current_user !== null && !empty($notifications)): ?>
                    <?php /* The design disables Mark all read when nothing is
                             unread (BoardIndex.dc.html:244) — offering it with
                             nothing to mark states a queue that is not there. */ ?>
                    <div class="directory-pane-actions">
                        <form method="post" action="/notifications/read-all"><?= $this->csrfField() ?><input type="hidden" name="return" value="/?pane=notices"><button class="linkbtn" type="submit"<?= (int) ($notification_unread ?? 0) === 0 ? ' disabled' : '' ?>>Mark all read</button></form>
                        <form method="post" action="/notifications/clear"><?= $this->csrfField() ?><input type="hidden" name="return" value="/?pane=notices"><button class="linkbtn danger" type="submit">Clear</button></form>
                    </div>
                <?php endif; ?>
            </header>
            <p>What happened to your account. The topics themselves wait in the <a href="/inbox">inbox</a>.</p>
            <?php if ($current_user === null): ?>
                <p class="directory-signin-state"><a href="/login?next=%2F%3Fpane%3Dnotices">Log in</a> to see notices about your account.</p>
            <?php elseif (empty($notifications)): ?>
                <p class="muted empty">All quiet. Nothing is waiting for you.</p>
            <?php else: ?>
                <ul class="directory-notice-list">
                    <?php foreach ($notifications as $notice): ?>
                        <?php $unread = (int) $notice['is_read'] === 0; ?>
                        <li class="<?= $unread ? 'is-unread' : 'is-read' ?>">
                            <form method="post" action="/notifications/<?= (int) $notice['id'] ?>/read">
                                <?= $this->csrfField() ?>
                                <button type="submit">
                                    <?php /* The mark carries its own text, so unread
                                             never rests on colour alone. */ ?>
                                    <span class="directory-notice-mark"><?php if ($unread): ?><span class="sr-only">Unread.</span><?php endif; ?></span>
                                    <span class="directory-notice-text"><?= $e($notificationVerb($notice)) ?><?php if (($notice['thread_title'] ?? '') !== ''): ?> <span class="directory-notice-topic">“<?= $e((string) $notice['thread_title']) ?>”</span><?php endif; ?></span>
                                    <time datetime="<?= $e(iso_datetime((string) $notice['created_at'])) ?>"><?= $e(relative_datetime((string) $notice['created_at'])) ?></time>
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="directory-light-pane directory-connections-pane" data-connection-mode="<?= $e((string) ($connection_mode ?? 'followers')) ?>">
            <h1>Connections<?php if ($current_user !== null): ?> <span>· <a href="/u/<?= $e($current_user->username()) ?>">@<?= $e($current_user->username()) ?></a></span><?php endif; ?></h1>
            <p>Following a member brings their new topics into your inbox. It tells them nothing you would not say aloud.</p>
            <?php if ($current_user === null): ?>
                <p class="directory-signin-state"><a href="/login?next=%2F%3Fpane%3Dconnections">Log in</a> to see your followers and the people you follow.</p>
            <?php else: ?>
                <nav class="directory-connection-tabs" aria-label="Connection lists">
                    <a href="/?pane=connections&amp;connection=followers"<?= ($connection_mode ?? 'followers') === 'followers' ? ' aria-current="page"' : '' ?>>Followers <?= (int) ($follower_count ?? 0) ?></a>
                    <a href="/?pane=connections&amp;connection=following"<?= ($connection_mode ?? 'followers') === 'following' ? ' aria-current="page"' : '' ?>>Following <?= (int) ($following_count ?? 0) ?></a>
                </nav>
                <?php if (empty($connections)): ?>
                    <p class="muted empty"><?= ($connection_mode ?? 'followers') === 'followers' ? 'No followers yet.' : 'Not following anyone yet.' ?></p>
                <?php else: ?>
                    <ul class="directory-people-list">
                        <?php foreach ($connections as $person): ?>
                            <?php $display = ($person['display_name'] ?? '') !== '' ? (string) $person['display_name'] : (string) $person['username']; ?>
                            <li>
                                <?= $this->partial('partials/monogram', ['name' => $display, 'username' => (string) $person['username']]) ?>
                                <span><a href="/u/<?= $e((string) $person['username']) ?>"><?= $e($display) ?></a><span>@<?= $e((string) $person['username']) ?> · <?= (int) ($person['reputation'] ?? 0) ?> regard</span></span>
                                <?php if (($connection_mode ?? 'followers') === 'followers'): ?>
                                    <form method="post" action="/u/<?= $e($current_user->username()) ?>/followers/<?= (int) $person['id'] ?>/remove">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn danger" type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
