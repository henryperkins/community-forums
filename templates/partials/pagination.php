<?php /** @var \App\Core\View $this */ ?>
<?php $pages = (int) ($pages ?? 1); $page = (int) ($page ?? 1); ?>
<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <a class="page" rel="prev" href="<?= $e($base_url) ?>page=<?= $page - 1 ?>">‹ Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="page current" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a class="page" href="<?= $e($base_url) ?>page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
            <a class="page" rel="next" href="<?= $e($base_url) ?>page=<?= $page + 1 ?>">Next ›</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
