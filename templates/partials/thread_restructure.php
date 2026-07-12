<?php /** @var \App\Core\View $this */ ?>
<?php if (!empty($features['split_merge']) && !empty($can_write) && !empty($can_split_merge)): ?>
<?php $movablePosts = array_values(array_filter($posts ?? [], static fn (array $post): bool => (int) ($post['is_op'] ?? 0) !== 1)); ?>
<div class="thread-restructure-scrim" data-thread-restructure-scrim hidden></div>
<details class="thread-restructure" data-thread-restructure>
    <summary>Split or merge topic</summary>
    <section class="thread-restructure-dialog" aria-labelledby="thread-restructure-title-<?= (int) $thread['id'] ?>">
        <header>
            <h2 id="thread-restructure-title-<?= (int) $thread['id'] ?>">Split or merge this topic</h2>
            <button type="button" data-thread-restructure-close hidden aria-label="Close split or merge"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button>
        </header>
        <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/split">
            <?= $this->csrfField() ?>
            <h3>Split replies out</h3>
            <?php foreach ($movablePosts as $post): ?>
                <?php $author = mask_author($post['author_display_name'] ?? null, $post['author_username'] ?? null, $post['author_role'] ?? 'user', (int) ($post['is_anonymous'] ?? 0) === 1); ?>
                <label class="sm-post"><input type="checkbox" name="post_ids[]" value="<?= (int) $post['id'] ?>"><span><strong><?= $e($author['label']) ?> · #<?= (int) $post['id'] ?></strong><span><?= $e(mb_strimwidth(strip_tags((string) ($post['body_html'] ?? '')), 0, 120, '…')) ?></span></span></label>
            <?php endforeach; ?>
            <label>New topic title<input class="input" name="title" maxlength="255" required></label>
            <button class="btn btn-small" type="submit"<?= $movablePosts === [] ? ' disabled' : '' ?>>Split replies out</button>
        </form>
        <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/merge">
            <?= $this->csrfField() ?>
            <h3>Merge into another topic</h3>
            <label>Target topic ID<input class="input" type="number" name="target_thread_id" min="1" required></label>
            <p>All posts move into the chosen topic. The move is logged and reversible through repair tooling.</p>
            <button class="btn btn-small" type="submit">Merge topics</button>
        </form>
    </section>
</details>
<?php endif; ?>
