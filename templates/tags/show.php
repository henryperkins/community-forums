<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Tag: ' . $tag['name']);
$this->section('canonical', '/tags/' . $tag['slug']);
?>
<div class="tag-view">
    <header class="board-header">
        <p class="breadcrumb"><a href="/tags">Tags</a></p>
        <h1><?= $e($tag['name']) ?></h1>
        <?php if (!empty($tag['description'])): ?><p class="muted"><?= $e($tag['description']) ?></p><?php endif; ?>
        <?php if ($current_user !== null && !empty($expanded_feeds)): ?>
            <form class="inline" method="post" action="/tags/<?= $e($tag['slug']) ?>/follow">
                <?= $this->csrfField() ?>
                <button class="linkbtn" type="submit"><?= !empty($following) ? 'Unfollow tag' : 'Follow tag' ?></button>
                <span class="muted">Discovery feed only</span>
            </form>
        <?php endif; ?>
    </header>

    <?php if (empty($threads)): ?>
        <p class="muted empty">No visible topics use this tag.</p>
    <?php else: ?>
        <ul class="thread-list">
            <?php foreach ($threads as $t): ?>
                <?= $this->partial('partials/thread_row', ['t' => $t, 'show_board' => true]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => $pages,
        'base_url' => '/tags/' . $tag['slug'] . '?',
    ]) ?>
</div>
