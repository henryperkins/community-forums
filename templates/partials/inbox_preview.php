<?php /** @var \App\Core\View $this */ ?>
<?php
$threadId = (int) $thread['id'];
$canonical = '/t/' . $threadId . '-' . (string) $thread['slug'];
$replyCount = max(0, (int) $total_posts - 1);
$activityAt = (string) (($thread['last_post_at'] ?? null) ?: $thread['created_at']);

/**
 * The opening post is the topic, not the first of its replies. The design sets
 * it as the lede directly beneath a byline that names the author, their standing
 * and the size of the conversation (ForumInbox.dc.html:268-288); listing it as
 * post #1 left the pane with no attribution for the topic at all.
 *
 * Identified by is_op rather than by position: an opening post a moderator has
 * soft-deleted is absent from this page entirely, and the first row would then
 * be somebody else's reply wearing the topic's byline.
 */
$op = null;
$replies = [];
foreach ($posts as $post) {
    if ($op === null && (int) ($post['is_op'] ?? 0) === 1) {
        $op = $post;
        continue;
    }
    $replies[] = $post;
}
$opAuthor = $op === null ? null : mask_author(
    $op['author_display_name'] ?? null,
    $op['author_username'] ?? null,
    $op['author_role'] ?? 'user',
    !empty($op['is_anonymous']),
);
// A rank beside a masked name narrows the field the mask exists to widen, so an
// anonymous opening post states no standing either.
$opTier = ($opAuthor !== null && $opAuthor['profile_url'] !== null)
    ? trim((string) ($op['author_title'] ?? ''))
    : '';
?>
<article class="inbox-preview" data-inbox-preview="<?= $threadId ?>">
    <header class="inbox-preview-header">
        <p class="inbox-preview-kicker"><a href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a><span aria-hidden="true">/</span><time datetime="<?= $e(iso_datetime($activityAt)) ?>" title="<?= $e(human_datetime($activityAt)) ?>"><?= $e(relative_datetime($activityAt)) ?></time></p>
        <h2><?= $e($thread['title']) ?></h2>
        <div class="inbox-preview-attribution">
            <?php if ($opAuthor !== null): ?>
                <?= $this->partial('partials/monogram', ['name' => $opAuthor['mono_name'], 'username' => $opAuthor['mono_seed']]) ?>
                <span class="inbox-preview-author"><?= $e($opAuthor['label']) ?></span>
                <?php if ($opTier !== ''): ?><span class="inbox-preview-tier"><?= $e($opTier) ?></span><?php endif; ?>
            <?php endif; ?>
            <span class="inbox-preview-count"><?= $replyCount ?> <?= $replyCount === 1 ? 'reply' : 'replies' ?></span>
            <a class="inbox-preview-open" href="<?= $e($canonical) ?>">Open full topic</a>
        </div>
    </header>

    <?php if ($op !== null): ?>
        <div class="inbox-preview-lede formatted-content"><?= $op['body_html'] /* sanitized at write time */ ?></div>
    <?php endif; ?>

    <?php if ($replies !== []): ?>
        <ol class="inbox-preview-posts">
            <?php foreach ($replies as $post): ?>
                <?php $author = mask_author($post['author_display_name'] ?? null, $post['author_username'] ?? null, $post['author_role'] ?? 'user', !empty($post['is_anonymous'])); ?>
                <li data-inbox-preview-post="<?= (int) $post['id'] ?>">
                    <?= $this->partial('partials/monogram', ['name' => $author['mono_name'], 'username' => $author['mono_seed']]) ?>
                    <div>
                        <p class="inbox-preview-byline"><span><?= $e($author['label']) ?></span><time datetime="<?= $e(iso_datetime($post['created_at'])) ?>"><?= $e(post_datetime($post['created_at'])) ?></time><?php if ((int) ($thread['accepted_answer_post_id'] ?? 0) === (int) $post['id']): ?><span class="chip chip-solved">Accepted</span><?php endif; ?></p>
                        <div class="formatted-content"><?= $post['body_html'] /* sanitized at write time */ ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if ((int) $total_posts > count($posts)): ?><p class="inbox-preview-bounded"><a href="<?= $e($canonical) ?>">Continue with <?= (int) $total_posts - count($posts) ?> more posts</a></p><?php endif; ?>

    <?php if ((int) $thread['is_locked'] === 1): ?>
        <p class="joinbar">This topic is locked. The wardens closed it once the council had spoken; the record stays readable.</p>
    <?php elseif ($can_reply): ?>
        <div class="inbox-preview-reply">
            <span class="eyebrow">Add your counsel</span>
            <?= $this->partial('partials/composer', [
                'thread' => $thread,
                'reply_errors' => [],
                'reply_old' => [],
                'page' => 1,
            ]) ?>
        </div>
    <?php else: ?>
        <p class="joinbar">You don't have permission to reply in this board.</p>
    <?php endif; ?>
</article>
