<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Tags'); ?>
<div class="tag-view">
    <header class="board-header">
        <h1>Tags</h1>
        <p class="muted">Approved community topics you can follow for discovery.</p>
    </header>

    <?php if (empty($tags)): ?>
        <p class="muted empty">No tags have been added yet.</p>
    <?php else: ?>
        <ul class="badge-row">
            <?php foreach ($tags as $tag): ?>
                <li class="badge-chip"><a href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
