<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', '#' . $board['name']);
$this->section('canonical', '/c/' . $board['slug']);
if (!empty($board['description'])) {
    $this->section('description', mb_strimwidth((string) $board['description'], 0, 160, '…'));
}
if (($board['visibility'] ?? 'public') !== 'public') {
    $this->section('robots', 'noindex, nofollow');
}
$topicCount = (int) ($board['thread_count'] ?? $total);
$postCount = (int) ($board['post_count'] ?? 0);
$archived = (int) ($board['is_archived'] ?? 0) === 1;
// The eyebrow names the shelf this board sits on. An orphaned board (no
// category row) falls back to the constant the band used before.
$categoryName = trim((string) ($board['category_name'] ?? '')) ?: 'Board';
$accessWord = match ((string) ($board['visibility'] ?? 'public')) {
    'hidden' => 'Hidden',
    'private' => 'Private',
    default => 'Public',
};
$perPage = max(1, (int) $per_page);
$pageCount = max(1, (int) ceil($total / $perPage));
$shownCount = count($threads);
$topicNoun = $total === 1 ? 'topic' : 'topics';
$unreadCount = (int) ($unread_count ?? 0);
$canMarkRead = !empty($can_mark_read);
// Every gutter form posts back to the exact page the reader is on.
$returnTo = '/c/' . $board['slug'] . ($page > 1 ? '?page=' . (int) $page : '');
?>
<div class="read-main read-pad board-view">
    <nav class="breadcrumb board-identity-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Forum index</a>
        <span class="breadcrumb-sep" aria-hidden="true">/</span>
        <span aria-current="page"><span class="hash">#</span><?= $e($board['name']) ?></span>
    </nav>

    <header class="board-identity" data-board-identity>
        <div class="board-identity-copy">
            <p class="eyebrow"><?= $e($categoryName) ?></p>
            <h1><span class="hash">#</span><?= $e($board['name']) ?></h1>
            <?php if (!empty($board['description'])): ?><p class="board-identity-description"><?= $e($board['description']) ?></p><?php endif; ?>
        </div>
        <div class="board-identity-aside">
            <?php /* Label + value, ruled apart — the facts read as a register
                     rather than a run-on sentence, and each one names itself. */ ?>
            <dl class="board-identity-facts" aria-label="Board facts">
                <div data-board-fact="topics">
                    <dt>Topics</dt>
                    <dd><?= $e(number_format($topicCount)) ?></dd>
                </div>
                <div data-board-fact="posts">
                    <dt>Posts</dt>
                    <dd><?= $e(number_format($postCount)) ?></dd>
                </div>
                <div data-board-fact="visibility">
                    <dt>Access</dt>
                    <dd><?= $accessWord ?></dd>
                </div>
                <?php if ($archived): ?>
                    <div data-board-fact="archive">
                        <dt>Status</dt>
                        <dd>Archived</dd>
                    </div>
                <?php endif; ?>
            </dl>
            <?php if (!empty($can_follow_board) || !empty($can_post)): ?>
                <div class="board-identity-actions">
                    <?php if (!empty($can_follow_board)): ?>
                        <form method="post" action="/b/<?= (int) $board['id'] ?>/follow">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-secondary<?= !empty($is_following_board) ? ' btn-on' : '' ?>" type="submit" data-follow-board aria-pressed="<?= !empty($is_following_board) ? 'true' : 'false' ?>" title="Following affects your discovery feed; it does not change this board's order."><?= !empty($is_following_board) ? 'Following' : 'Follow board' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($can_post)): ?>
                        <button class="btn btn-accent" type="button" hidden data-open-topic-composer aria-controls="new-topic" aria-expanded="false">
                            <?= $this->partial('partials/icon', ['name' => 'plus']) ?>
                            <span>New topic</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php /* A pointer-only echo of the slab for the scrolled state. aria-hidden
             because the slab's own controls are still in the DOM below; the FAB
             and the #new-topic <details> keep the keyboard path intact. The
             wrapper is zero-height, so it costs no flow height either way. */ ?>
    <div class="board-identity-sticky" aria-hidden="true">
        <div class="board-identity-condensed">
            <h2><span class="hash">#</span><?= $e($board['name']) ?></h2>
            <span class="board-identity-condensed-facts"><?= $topicCount ?> <?= $topicCount === 1 ? 'topic' : 'topics' ?></span>
            <?php if (!empty($can_post)): ?>
                <button class="btn btn-accent" type="button" tabindex="-1" hidden data-open-topic-composer aria-controls="new-topic"><span>New topic</span></button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($can_follow_board)): ?>
        <p class="board-identity-follow-note">Following affects your discovery feed; it does not change this board's order.</p>
    <?php endif; ?>

    <?php if ($archived): ?>
        <div class="joinbar joinbar-archived" data-archived-banner>This board is retired and read-only. You can still read and search its topics, but new topics and replies are closed.</div>
    <?php elseif ($current_user === null): ?>
        <div class="joinbar">You're browsing as a guest — <a href="/login?next=/c/<?= $e($board['slug']) ?>">log in</a> to start a topic.</div>
    <?php endif; ?>

    <?php if (!empty($can_post)): ?>
        <details class="composer-details" id="new-topic">
            <summary class="btn">New topic</summary>
            <?= $this->partial('partials/new_thread_form', [
                'board' => $board,
                'errors' => [],
                'old' => [],
                'show_avatars' => $show_avatars ?? true,
            ]) ?>
        </details>
    <?php endif; ?>

    <section class="board-topics<?= ($show_avatars ?? true) ? '' : ' is-flat' ?>" data-board-topics aria-labelledby="board-topics-heading">
        <?php /* The header carries the row's own track list, so its labels rule
                 the columns beneath them instead of merely sitting above them. */ ?>
        <header class="board-topics-heading">
            <div class="board-topics-heading-main">
                <p class="eyebrow">Latest activity</p>
                <h2 id="board-topics-heading">Topics</h2>
                <p class="board-topics-order">Pinned first, then last post</p>
            </div>
            <div class="board-topics-heading-aside">
                <?php if ($canMarkRead && $unreadCount > 0): ?>
                    <span class="board-topics-unread" data-board-unread><?= (int) $unreadCount ?> unread</span>
                    <form method="post" action="/c/<?= $e($board['slug']) ?>/read">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="return" value="<?= $e($returnTo) ?>">
                        <button class="board-topics-markread" type="submit" data-mark-board-read>Mark all read</button>
                    </form>
                <?php endif; ?>
            </div>
        </header>

        <?php if (empty($threads)): ?>
            <div class="board-empty">
                <?= $this->partial('partials/icon', ['name' => 'eight-point-star', 'class' => 'board-empty-mark']) ?>
                <p class="board-empty-headline">No topics here yet.</p>
                <?php if (!empty($can_post)): ?>
                    <p class="board-empty-sub">Be the first to open one in #<?= $e($board['name']) ?>.</p>
                    <?php /* Carries the same hook as the slab and the FAB, or JS
                             would leave it a dead anchor: app.js hides the
                             <summary> as soon as a promoted trigger exists, so
                             jumping to #new-topic would land on nothing. */ ?>
                    <a class="btn btn-secondary board-empty-cta" href="#new-topic" data-open-topic-composer>New topic</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="thread-list board-topics-list">
                <?php foreach ($threads as $t): ?>
                    <?= $this->partial('partials/thread_row', [
                        't' => $t,
                        'board' => $board,
                        'show_avatars' => $show_avatars ?? true,
                        'presentation' => 'board',
                        'read_toggle' => $canMarkRead,
                        'return_to' => $returnTo,
                    ]) ?>
                <?php endforeach; ?>
            </ul>

            <?= $this->partial('partials/pagination', [
                'page' => $page,
                'pages' => $pageCount,
                'base_url' => '/c/' . $board['slug'] . '?',
                'variant' => 'board',
                'count_label' => 'Showing ' . number_format($shownCount) . ' of ' . number_format($total) . ' ' . $topicNoun,
            ]) ?>
        <?php endif; ?>
    </section>

    <?php if (!empty($can_post)): ?>
        <a class="fab" href="#new-topic" aria-label="Start a new topic"><?= $this->partial('partials/icon', ['name' => 'plus']) ?></a>
    <?php endif; ?>
</div>
