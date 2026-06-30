<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Tags'); ?>
<div class="read-main read-pad tag-view">
    <header class="board-header">
        <h1>Tags</h1>
        <p class="muted">Approved community topics you can follow for discovery.</p>
    </header>

    <?php if (empty($tags)): ?>
        <p class="muted empty">No tags have been added yet.</p>
    <?php else: ?>
        <ul class="tag-cloud">
            <?php foreach ($tags as $tag): ?>
                <?php $count = (int) ($tag['thread_count'] ?? 0); ?>
                <li>
                    <a class="tag-card" href="/tags/<?= $e($tag['slug']) ?>">
                        <span class="tag-name"><?= $e($tag['name']) ?></span>
                        <span class="tag-count"><?= $count ?> topic<?= $count === 1 ? '' : 's' ?></span>
                        <?php if (($tag['description'] ?? '') !== ''): ?><span class="tag-desc"><?= $e((string) $tag['description']) ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
