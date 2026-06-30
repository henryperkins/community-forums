<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $site_name); ?>
<div class="read-main read-pad board-index">
    <h1 class="page-title"><?= $e($site_name) ?></h1>

    <?php $hasBoards = false; foreach ($sections as $s) { if (!empty($s['boards'])) { $hasBoards = true; break; } } ?>

    <?php if (!$hasBoards): ?>
        <p class="muted empty">No boards have been created yet.<?php if ($current_user !== null && $current_user->isAdmin()): ?> <a href="/admin/structure">Create one in the admin console.</a><?php endif; ?></p>
    <?php else: ?>
        <?php foreach ($sections as $s): ?>
            <?php if (empty($s['boards'])) { continue; } ?>
            <section class="cat-block">
                <h2 class="cat-title"><?= $e($s['category']['name']) ?></h2>
                <ul class="board-list">
                    <?php foreach ($s['boards'] as $b): ?>
                        <li class="board-row">
                            <a class="board-link" href="/c/<?= $e($b['slug']) ?>">
                                <span class="board-name"><span class="hash">#</span><?= $e($b['name']) ?>
                                    <?php if ($b['visibility'] !== 'public'): ?><span class="tag"><?= $e($b['visibility']) ?></span><?php endif; ?>
                                </span>
                                <?php if (!empty($b['description'])): ?><span class="board-desc"><?= $e($b['description']) ?></span><?php endif; ?>
                            </a>
                            <span class="board-stats"><?= (int) $b['thread_count'] ?> threads · <?= (int) $b['post_count'] ?> posts</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
