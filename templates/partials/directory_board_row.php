<?php /** @var \App\Core\View $this */ ?>
<?php
$sort = (string) ($directory_sort ?? 'category');
$peek = (int) ($directory_peek ?? 3);
$topics = is_array($board['topics'] ?? null) ? $board['topics'] : [];
$signal = match ($sort) {
    'active' => !empty($board['latest_activity_at']) ? relative_datetime((string) $board['latest_activity_at']) : '',
    'newest' => !empty($board['newest_thread_at']) ? 'opened ' . relative_datetime((string) $board['newest_thread_at']) : '',
    'unanswered' => (int) ($board['unanswered_count'] ?? 0) > 0 ? (int) $board['unanswered_count'] . ' unanswered' : '',
    'top' => (int) ($board['top_commend_count'] ?? 0) . ' commends',
    'solved' => (int) ($board['settled_count'] ?? 0) > 0 ? (int) $board['settled_count'] . ' settled' : '',
    default => '',
};
$emptyLabel = match ($sort) {
    'unanswered' => 'Every topic here has an answer.',
    'solved' => 'Nothing settled here yet.',
    default => 'No topics yet.',
};
?>
<article class="forum-directory__board" data-directory-board="<?= $e((string) $board['slug']) ?>">
    <div class="forum-directory__board-row" data-brow>
        <a href="/c/<?= $e((string) $board['slug']) ?>">
            <span class="forum-directory__board-name"><?= $e((string) $board['name']) ?></span>
            <?php if (!empty($board['description'])): ?><span class="forum-directory__board-description"><?= $e((string) $board['description']) ?></span><?php endif; ?>
            <span class="forum-directory__board-facts">
                <?php if ($signal !== ''): ?><span class="forum-directory__board-signal"><?= $e($signal) ?></span><?php endif; ?>
                <span><?= (int) $board['thread_count'] ?> topic<?= (int) $board['thread_count'] === 1 ? '' : 's' ?> · <?= (int) $board['post_count'] ?> post<?= (int) $board['post_count'] === 1 ? '' : 's' ?></span>
            </span>
        </a>
    </div>
    <?php if ($peek > 0): ?>
        <ul class="forum-directory__peek" data-directory-peek>
            <?php foreach ($topics as $topic): ?>
                <?php
                $author = mask_author(
                    $topic['author_display_name'] ?? null,
                    $topic['author_username'] ?? null,
                    $topic['author_role'] ?? 'user',
                    (int) ($topic['op_is_anonymous'] ?? 0) === 1,
                );
                $meta = match ($sort) {
                    'top' => $author['label'] . ' · ' . (int) $topic['commend_count'] . ' commends',
                    'unanswered' => $author['label'] . ' · opened ' . relative_datetime((string) $topic['created_at']) . ' · no answer',
                    'solved' => $author['label'] . ' · ' . ((string) $topic['status'] === 'decision_made' ? 'decision' : 'solved') . ' · ' . relative_datetime((string) ($topic['status_changed_at'] ?? $topic['last_post_at'])),
                    'newest' => $author['label'] . ' · opened ' . relative_datetime((string) $topic['created_at']),
                    default => $author['label'] . ' · ' . relative_datetime((string) $topic['last_post_at']),
                };
                ?>
                <li data-directory-topic="<?= $e((string) $topic['title']) ?>">
                    <span aria-hidden="true"></span>
                    <a href="/t/<?= (int) $topic['thread_id'] ?>-<?= $e((string) $topic['slug']) ?>"><?= $e((string) $topic['title']) ?></a>
                    <span><?= $e($meta) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if ($topics === []): ?><li class="forum-directory__peek-empty"><?= $e($emptyLabel) ?></li><?php endif; ?>
        </ul>
    <?php endif; ?>
</article>
