<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $site_name); ?>
<div class="read-main read-pad board-index">
    <?php
    $visibleBoards = 0;
    $visibleTopics = 0;
    $visiblePosts = 0;
    foreach ($sections as $section) {
        foreach (($section['boards'] ?? []) as $listedBoard) {
            $visibleBoards++;
            $visibleTopics += (int) ($listedBoard['thread_count'] ?? 0);
            $visiblePosts += (int) ($listedBoard['post_count'] ?? 0);
        }
    }
    $hasBoards = $visibleBoards > 0;
    ?>

    <header class="forum-directory__hero">
        <p class="eyebrow">Forum index</p>
        <h1><?= $e($site_name) ?></h1>
        <p>Browse the listed boards and pick one to see its topics. Use Forum inbox for your personal cross-board queue.</p>
        <div class="forum-directory__stats" aria-label="Forum totals">
            <span data-forum-total="boards"><?= $visibleBoards ?> board<?= $visibleBoards === 1 ? '' : 's' ?></span>
            <span data-forum-total="topics"><?= $visibleTopics ?> topic<?= $visibleTopics === 1 ? '' : 's' ?></span>
            <span data-forum-total="posts"><?= $visiblePosts ?> post<?= $visiblePosts === 1 ? '' : 's' ?></span>
        </div>
    </header>

    <?php if (!$hasBoards): ?>
        <p class="muted empty">No boards have been created yet.<?php if ($current_user !== null && $current_user->isAdmin()): ?> <a href="/admin/structure">Create one in the admin console.</a><?php endif; ?></p>
    <?php else: ?>
        <div class="forum-directory__categories">
            <?php foreach ($sections as $section): ?>
                <?php if (empty($section['boards'])) { continue; } ?>
                <section class="forum-directory__category">
                    <div class="forum-directory__category-heading">
                        <h2><?= $e($section['category']['name']) ?></h2>
                        <span aria-hidden="true"></span>
                    </div>
                    <ul class="forum-directory__boards">
                        <?php foreach ($section['boards'] as $board): ?>
                            <li>
                                <a class="forum-directory__board" href="/c/<?= $e($board['slug']) ?>">
                                    <span class="forum-directory__copy">
                                        <strong><span class="hash">#</span><?= $e($board['name']) ?><?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?></strong>
                                        <?php if (!empty($board['description'])): ?><span><?= $e($board['description']) ?></span><?php endif; ?>
                                    </span>
                                    <span class="forum-directory__counts">
                                        <span><strong><?= (int) $board['thread_count'] ?></strong> topic<?= (int) $board['thread_count'] === 1 ? '' : 's' ?></span>
                                        <span><strong><?= (int) $board['post_count'] ?></strong> post<?= (int) $board['post_count'] === 1 ? '' : 's' ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
