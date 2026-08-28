<?php /** @var \App\Core\View $this */ ?>
<?php
$threadId = (int) $thread['id'];
$canonical = '/t/' . $threadId . '-' . (string) $thread['slug'];
$replyCount = max(0, (int) $total_posts - 1);
?>
<article class="inbox-preview" data-inbox-preview="<?= $threadId ?>">
    <header class="inbox-preview-header">
        <p class="inbox-preview-kicker"><a href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a><span>/</span><time datetime="<?= $e(iso_datetime($thread['created_at'])) ?>"><?= $e(human_datetime($thread['created_at'])) ?></time></p>
        <h2><?= $e($thread['title']) ?></h2>
        <p class="inbox-preview-count"><?= $replyCount ?> <?= $replyCount === 1 ? 'reply' : 'replies' ?></p>
        <a class="inbox-preview-open" href="<?= $e($canonical) ?>">Open full topic</a>
    </header>

    <ol class="inbox-preview-posts">
        <?php foreach ($posts as $post): ?>
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
