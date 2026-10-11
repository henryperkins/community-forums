<?php /** @var \App\Core\View $this */ ?>
<?php // Both responsive area menus consume the same role- and flag-filtered destinations. ?>
<?php foreach ($entries as $key => $entry): ?>
    <?php if ($key === $area): ?>
        <span class="<?= $e($item_class) ?> is-active" aria-current="page"><?= $e($entry['label']) ?></span>
    <?php elseif (!$entry['enabled']): ?>
        <span class="<?= $e($item_class) ?> is-disabled" aria-disabled="true" data-destination="<?= $e($entry['href']) ?>">
            <span class="subnav-item-label"><?= $e($entry['label']) ?></span>
            <span class="subnav-item-note"><?= $e($disabled_note) ?></span>
        </span>
    <?php else: ?>
        <a class="<?= $e($item_class) ?>" href="<?= $e($entry['href']) ?>"><?= $e($entry['label']) ?></a>
    <?php endif; ?>
<?php endforeach; ?>
