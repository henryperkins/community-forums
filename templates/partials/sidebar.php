<?php /** @var \App\Core\View $this */ ?>
<aside class="sidebar">
    <nav aria-label="Boards">
        <a class="sidebar-home <?= $request_path === '/' ? 'active' : '' ?>" href="/">Home</a>
        <?php if (empty($nav)): ?>
            <p class="muted sidebar-empty">No boards yet.</p>
        <?php else: ?>
            <?php foreach ($nav as $section): ?>
                <div class="nav-cat">
                    <span class="nav-cat-name"><?= $e($section['category']['name']) ?></span>
                    <ul class="nav-boards">
                        <?php foreach ($section['boards'] as $b): ?>
                            <li>
                                <a class="<?= $request_path === '/c/' . $b['slug'] ? 'active' : '' ?>" href="/c/<?= $e($b['slug']) ?>">
                                    <span class="hash">#</span><?= $e($b['name']) ?>
                                    <?php if ($b['visibility'] !== 'public'): ?><span class="tag"><?= $e($b['visibility']) ?></span><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
</aside>
