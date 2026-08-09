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
$visibilityLabel = match ((string) ($board['visibility'] ?? 'public')) {
    'hidden' => 'Hidden board',
    'private' => 'Private board',
    default => 'Public board',
};
?>
<div class="read-main read-pad board-view">
    <nav class="breadcrumb board-identity-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Forum index</a>
        <span class="breadcrumb-sep" aria-hidden="true">/</span>
        <span aria-current="page"><span class="hash">#</span><?= $e($board['name']) ?></span>
    </nav>

    <header class="board-identity" data-board-identity>
        <div class="board-identity-copy">
            <p class="eyebrow">Board</p>
            <h1><span class="hash">#</span><?= $e($board['name']) ?></h1>
            <?php if (!empty($board['description'])): ?><p class="board-identity-description"><?= $e($board['description']) ?></p><?php endif; ?>
            <div class="board-identity-facts" aria-label="Board facts">
                <span data-board-fact="topics"><?= $topicCount ?> <?= $topicCount === 1 ? 'topic' : 'topics' ?></span>
                <span data-board-fact="posts"><?= $postCount ?> <?= $postCount === 1 ? 'post' : 'posts' ?></span>
                <span data-board-fact="visibility"><?= $visibilityLabel ?></span>
                <?php if ($archived): ?><span data-board-fact="archive">Archived</span><?php endif; ?>
            </div>
        </div>
        <?php if (!empty($can_follow_board) || !empty($can_post)): ?>
            <div class="board-identity-actions">
                <?php if (!empty($can_follow_board)): ?>
                    <form method="post" action="/b/<?= (int) $board['id'] ?>/follow">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-secondary<?= !empty($is_following_board) ? ' btn-on' : '' ?>" type="submit" data-follow-board aria-pressed="<?= !empty($is_following_board) ? 'true' : 'false' ?>"><?= !empty($is_following_board) ? 'Following' : 'Follow board' ?></button>
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

    <section class="board-topics" data-board-topics aria-labelledby="board-topics-heading">
        <header class="board-topics-heading">
            <div>
                <p class="eyebrow">Latest activity</p>
                <h2 id="board-topics-heading">Topics</h2>
            </div>
            <p>Pinned first, then last post</p>
        </header>

        <?php if (empty($threads)): ?>
            <p class="muted empty">No topics here yet.</p>
        <?php else: ?>
            <ul class="thread-list board-topics-list">
                <?php foreach ($threads as $t): ?>
                    <?= $this->partial('partials/thread_row', [
                        't' => $t,
                        'board' => $board,
                        'show_avatars' => $show_avatars ?? true,
                        'presentation' => 'board',
                    ]) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?= $this->partial('partials/pagination', [
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per_page)),
            'base_url' => '/c/' . $board['slug'] . '?',
        ]) ?>
    </section>

    <?php if (!empty($can_post)): ?>
        <a class="fab" href="#new-topic" aria-label="Start a new topic"><?= $this->partial('partials/icon', ['name' => 'plus']) ?></a>
    <?php endif; ?>
</div>
