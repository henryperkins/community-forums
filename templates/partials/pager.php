<?php /** @var \App\Core\View $this */ ?>
<?php
$pagerPath = (string) ($path ?? '');
$pagerQuery = is_array($query ?? null) ? $query : [];
$pagerPage = max(1, (int) ($page ?? 1));
$pagerTotal = max(1, (int) ($total_pages ?? 1));
$pagerPage = min($pagerPage, $pagerTotal);
$pagerLabel = isset($aria_label) ? (string) $aria_label : 'Pagination';
$pagerHref = static function (int $target) use ($pagerPath, $pagerQuery): string {
    $params = $pagerQuery;
    $params['page'] = $target;
    $separator = str_contains($pagerPath, '?') ? '&' : '?';

    return $pagerPath . $separator . http_build_query($params);
};
?>
<nav class="pager" aria-label="<?= $e($pagerLabel) ?>">
    <?php if ($pagerPage > 1): ?>
        <a class="pager-control" href="<?= $e($pagerHref($pagerPage - 1)) ?>">Previous</a>
    <?php else: ?>
        <span class="pager-control is-disabled" aria-disabled="true">Previous</span>
    <?php endif; ?>
    <span class="pager-label">Page <?= $pagerPage ?> of <?= $pagerTotal ?></span>
    <?php if ($pagerPage < $pagerTotal): ?>
        <a class="pager-control" href="<?= $e($pagerHref($pagerPage + 1)) ?>">Next</a>
    <?php else: ?>
        <span class="pager-control is-disabled" aria-disabled="true">Next</span>
    <?php endif; ?>
</nav>
