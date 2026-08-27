<?php /** @var \App\Core\View $this */ ?>
<?php
$pages = (int) ($pages ?? 1);
$page = (int) ($page ?? 1);
// 'board' states where you are and offers only the two moves. The default
// numbered strip is unchanged for every other caller.
$variant = (string) ($variant ?? 'default');
$countLabel = trim((string) ($count_label ?? ''));
?>
<?php if ($variant === 'board'): ?>
    <?php if ($pages > 1 || $countLabel !== ''): ?>
        <nav class="pagination pagination-board" aria-label="Pagination">
            <?php if ($countLabel !== ''): ?><span class="pagination-count"><?= $e($countLabel) ?></span><?php endif; ?>
            <?php if ($pages > 1): ?>
                <?php /* A dead anchor is still in the tab order and still announces
                         as a link; the unavailable move is not a link at all. */ ?>
                <?php if ($page > 1): ?>
                    <a class="page" rel="prev" href="<?= $e($base_url) ?>page=<?= $page - 1 ?>">Previous</a>
                <?php else: ?>
                    <span class="page is-disabled" aria-disabled="true">Previous</span>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="page" rel="next" href="<?= $e($base_url) ?>page=<?= $page + 1 ?>">Next</a>
                <?php else: ?>
                    <span class="page is-disabled" aria-disabled="true">Next</span>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php elseif ($pages > 1): ?>
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
