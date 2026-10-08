<?php /** @var \App\Core\View $this */ ?>
<?php if ($kind === 'order'): ?>
    <span class="inbox-scope-group-label">Sort</span>
    <?php foreach (\App\Support\InboxView::ORDERS as $item): ?>
        <a href="<?= $e(\App\Support\InboxView::query($scope, $item)) ?>"<?= $item === $order ? ' class="is-active" aria-current="page"' : '' ?>>
            <span><?= $e(ucfirst(\App\Support\InboxView::ORDER_LABELS[$item]['full'])) ?></span>
            <?php if ($item === $order): ?><?= $this->partial('partials/icon', ['name' => 'check']) ?><?php endif; ?>
        </a>
    <?php endforeach; ?>
<?php else: ?>
    <?php foreach (\App\Support\InboxView::GROUPS as $groupLabel => $groupScopes): ?>
        <?php $visibleGroup = array_values(array_filter($groupScopes, static fn (string $item): bool => isset($available[$item]))); ?>
        <?php if ($visibleGroup !== []): ?>
            <span class="inbox-scope-group-label"><?= $e($groupLabel) ?></span>
            <?php foreach ($visibleGroup as $item): ?>
                <a href="<?= $e(\App\Support\InboxView::query($item, $order)) ?>"<?= $item === $scope ? ' class="is-active" aria-current="page"' : '' ?>>
                    <span><?= $e(\App\Support\InboxView::LABELS[$item]) ?></span>
                    <span class="inbox-choice-state">
                        <?php if ($item === $scope): ?><?= $this->partial('partials/icon', ['name' => 'check']) ?><?php endif; ?>
                        <span data-inbox-scope-count="<?= $e($item) ?>"><?= (int) ($scope_counts[$item] ?? 0) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
