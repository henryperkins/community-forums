<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The console's previous/next pager, the one every paged console list uses.
 *
 *   page         int      the current page, counted from 1
 *   total_pages  int      when the page count is known; labelled "Page N of M"
 *   has_next     bool     when only "is there another page?" is known; labelled "Page N"
 *   path, query           the target is path?query&page=N ...
 *   href         Closure  ...or fn (int $page): string builds it, for routes that
 *                         count from 0 or carry their own query
 *   aria_label   string   the nav's name (default "Pagination")
 *   noun         string   also names each control, as "Previous tag page"
 *
 * An unavailable move is a disabled span, not a dead link: a link stays in the tab
 * order and still announces as one.
 */
$pagerPage = max(1, (int) ($page ?? 1));
$pagerTotal = isset($total_pages) ? max(1, (int) $total_pages) : null;
if ($pagerTotal !== null) {
    $pagerPage = min($pagerPage, $pagerTotal);
}
$pagerHasNext = $pagerTotal !== null ? $pagerPage < $pagerTotal : !empty($has_next);
$pagerLabel = isset($aria_label) ? (string) $aria_label : 'Pagination';
$pagerNoun = trim((string) ($noun ?? ''));
$pagerPath = (string) ($path ?? '');
$pagerQuery = is_array($query ?? null) ? $query : [];
$pagerHref = ($href ?? null) instanceof \Closure
    ? $href
    : static function (int $target) use ($pagerPath, $pagerQuery): string {
        $params = $pagerQuery;
        $params['page'] = $target;
        $separator = str_contains($pagerPath, '?') ? '&' : '?';

        return $pagerPath . $separator . http_build_query($params);
    };
?>
<nav class="pager" aria-label="<?= $e($pagerLabel) ?>">
    <?php if ($pagerPage > 1): ?>
        <a class="pager-control" href="<?= $e($pagerHref($pagerPage - 1)) ?>"<?php if ($pagerNoun !== ''): ?> aria-label="<?= $e('Previous ' . $pagerNoun . ' page') ?>"<?php endif; ?>>Previous</a>
    <?php else: ?>
        <span class="pager-control is-disabled" aria-disabled="true">Previous</span>
    <?php endif; ?>
    <span class="pager-label">Page <?= $pagerPage ?><?php if ($pagerTotal !== null): ?> of <?= $pagerTotal ?><?php endif; ?></span>
    <?php if ($pagerHasNext): ?>
        <a class="pager-control" href="<?= $e($pagerHref($pagerPage + 1)) ?>"<?php if ($pagerNoun !== ''): ?> aria-label="<?= $e('Next ' . $pagerNoun . ' page') ?>"<?php endif; ?>>Next</a>
    <?php else: ?>
        <span class="pager-control is-disabled" aria-disabled="true">Next</span>
    <?php endif; ?>
</nav>
