<?php /** @var \App\Core\View $this */ ?>
<?php
$choices = compact('scope', 'order', 'available', 'scope_counts');
$countText = (int) $total . ((int) $total === 1 ? ' topic' : ' topics');
?>
<nav class="inbox-view-bar" aria-label="Inbox view">
    <details class="inbox-scope-menu inbox-menu" name="inbox-controls" data-inbox-scope-menu data-inbox-menu>
        <summary aria-label="Show: <?= $e($scopeLabel) ?>, <?= $e($countText) ?>">
            <span class="inbox-control-label">Show:</span>
            <span class="inbox-selected-scope"><?= $e($scopeLabel) ?></span>
            <span class="inbox-scope-count"><span data-inbox-current-count><?= (int) $total ?></span> <span data-inbox-count-label><?= (int) $total === 1 ? 'topic' : 'topics' ?></span></span>
            <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
        </summary>
        <div class="inbox-scope-menu-panel inbox-menu-panel">
            <?= $this->partial('partials/inbox_choices', ['kind' => 'scope'] + $choices) ?>
        </div>
    </details>
    <details class="inbox-sort-menu inbox-menu" name="inbox-controls" data-inbox-menu>
        <summary aria-label="Sort: <?= $e(ucfirst($orderLabel['full'])) ?>">
            <span class="inbox-control-label">Sort:</span>
            <span class="inbox-order-full"><?= $e(ucfirst($orderLabel['full'])) ?></span><span class="inbox-order-short" aria-hidden="true"><?= $e($orderLabel['short']) ?></span>
            <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
        </summary>
        <div class="inbox-menu-panel">
            <?= $this->partial('partials/inbox_choices', ['kind' => 'order'] + $choices) ?>
        </div>
    </details>
    <details class="inbox-compact-menu inbox-menu" name="inbox-controls" data-inbox-menu>
        <summary aria-label="Show: <?= $e($scopeLabel) ?>, <?= $e($countText) ?>; Sort: <?= $e(ucfirst($orderLabel['full'])) ?>">
            <span class="inbox-selected-scope"><?= $e($scopeLabel) ?></span>
            <span class="inbox-compact-meta"><span class="inbox-scope-count"><span data-inbox-current-count><?= (int) $total ?></span> <span data-inbox-count-label><?= (int) $total === 1 ? 'topic' : 'topics' ?></span></span><span aria-hidden="true"> · </span><span><?= $e($orderLabel['short']) ?></span></span>
            <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
        </summary>
        <div class="inbox-menu-panel inbox-scope-menu-panel">
            <?= $this->partial('partials/inbox_choices', ['kind' => 'order'] + $choices) ?>
            <?= $this->partial('partials/inbox_choices', ['kind' => 'scope'] + $choices) ?>
        </div>
    </details>
</nav>
