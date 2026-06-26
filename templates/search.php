<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $query !== '' ? 'Search: ' . $query : 'Search'); ?>
<div class="search-view">
    <header class="board-header">
        <h1>Search</h1>
        <form class="search-form" method="get" action="/search" role="search">
            <input class="input" type="search" name="q" value="<?= $e($query) ?>"
                   placeholder="Search threads and posts…" autofocus>
            <button class="btn" type="submit">Search</button>
        </form>
    </header>

    <?php if ($searched): ?>
        <?php if (empty($results)): ?>
            <p class="muted empty">No results for “<?= $e($query) ?>”.</p>
        <?php else: ?>
            <ul class="search-results">
                <?php foreach ($results as $r): ?>
                    <li class="search-result">
                        <a class="search-title" href="<?= $e($r['url']) ?>">
                            <?php if ($r['type'] === 'post'): ?><span class="tag">post</span><?php endif; ?>
                            <?= $e($r['title']) ?>
                        </a>
                        <span class="search-board"><a href="/c/<?= $e($r['board_slug']) ?>"><span class="hash">#</span><?= $e($r['board_name']) ?></a></span>
                        <?php if (($r['snippet'] ?? '') !== ''): ?>
                            <p class="search-snippet"><?= $r['snippet'] /* already HTML-escaped in the service */ ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted">Search thread titles and posts you can access.</p>
    <?php endif; ?>
</div>
