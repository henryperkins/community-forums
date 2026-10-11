<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Tag: ' . $tag['name']);
$this->section('canonical', '/tags/' . $tag['slug']);
?>
<?php if ($current_user !== null): $this->start('subheader_leading'); ?>
    <div class="page-toolbar tag-toolbar">
        <div class="page-toolbar-title-stack">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="/tags">Tags</a></nav>
            <h1 class="forum-page-title"><?= $e($tag['name']) ?></h1>
        </div>
        <?php if (!empty($expanded_feeds)): ?>
        <div class="page-toolbar-controls">
            <form class="inline header-follow" method="post" action="/tags/<?= $e($tag['slug']) ?>/follow">
                <?= $this->csrfField() ?>
                <button class="linkbtn" type="submit"><?= !empty($following) ? 'Unfollow tag' : 'Follow tag' ?></button>
                <span class="muted">Discovery feed only</span>
            </form>
        </div>
        <?php endif; ?>
    </div>
<?php $this->stop(); endif; ?>
<div class="read-main read-pad tag-view">
    <?php if ($current_user === null || !empty($tag['description'])): ?>
    <header class="board-header">
        <?php if ($current_user === null): ?>
            <p class="breadcrumb"><a href="/tags">Tags</a></p>
            <h1><?= $e($tag['name']) ?></h1>
        <?php endif; ?>
        <?php if (!empty($tag['description'])): ?><p class="muted"><?= $e($tag['description']) ?></p><?php endif; ?>
    </header>
    <?php endif; ?>

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
