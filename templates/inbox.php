<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Inbox'); $this->section('route', 'inbox'); ?>
<?php
$scopeLabel = \App\Support\InboxView::LABELS[$scope] ?? 'For You';
$orderLabel = \App\Support\InboxView::ORDER_LABELS[$order] ?? \App\Support\InboxView::ORDER_LABELS['active'];
$currentUrl = \App\Support\InboxView::query($scope, $order, $page);
$emptyTitle = match ($scope) {
    'for_you' => 'Nothing needs your attention right now.',
    'unread' => "You're all caught up — nothing unread.",
    default => 'Nothing in ' . $scopeLabel . '.',
};
$available = array_fill_keys($scopes, true);
?>
<div class="inbox-shell" data-inbox data-inbox-scope="<?= $e($scope) ?>" data-inbox-order="<?= $e($order) ?>">
    <section class="inbox-list" data-inbox-list tabindex="-1" aria-label="Topics">
        <header class="board-header inbox-list-head">
            <p class="inbox-kicker">Your personal forum view</p>
            <div class="inbox-title-line">
                <h1>Forum inbox</h1>
                <?php if ((int) $unread_count > 0): ?>
                    <span class="badge" data-inbox-unread-count="<?= (int) $unread_count ?>"><?= (int) $unread_count ?> unread</span>
                <?php endif; ?>
            </div>
            <p class="muted">Topics from across every board you can read, organized by the signals that make them yours. The full directory is <a href="/">Boards</a>; start a topic from the board it belongs to.</p>
        </header>

        <nav class="inbox-view-bar" aria-label="Inbox view">
            <span class="inbox-view-label">Viewing</span>
            <details class="inbox-scope-menu" data-inbox-scope-menu>
                <summary aria-haspopup="menu">
                    <span><?= $e($scopeLabel) ?></span>
                    <span data-inbox-current-count><?= (int) $total ?></span>
                    <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
                </summary>
                <div class="inbox-scope-menu-panel" role="menu">
                    <?php foreach (\App\Support\InboxView::GROUPS as $groupLabel => $groupScopes): ?>
                        <?php $visibleGroup = array_values(array_filter($groupScopes, static fn (string $item): bool => isset($available[$item]))); ?>
                        <?php if ($visibleGroup !== []): ?>
                            <span class="inbox-scope-group-label"><?= $e($groupLabel) ?></span>
                            <?php foreach ($visibleGroup as $item): ?>
                                <a role="menuitem" href="<?= $e(\App\Support\InboxView::query($item, $order)) ?>"<?= $item === $scope ? ' class="is-active" aria-current="page"' : '' ?>>
                                    <span><?= $e(\App\Support\InboxView::LABELS[$item]) ?></span>
                                    <span data-inbox-scope-count="<?= $e($item) ?>"><?= (int) ($scope_counts[$item] ?? 0) ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </details>

            <span class="inbox-order" role="group" aria-label="Order">
                <?php foreach (\App\Support\InboxView::ORDERS as $item): ?>
                    <?php $itemLabel = \App\Support\InboxView::ORDER_LABELS[$item]; ?>
                    <a href="<?= $e(\App\Support\InboxView::query($scope, $item)) ?>" title="Order by <?= $e($itemLabel['full']) ?>"<?= $item === $order ? ' class="is-active" aria-current="page"' : '' ?>><?= $e($itemLabel['short']) ?></a>
                <?php endforeach; ?>
            </span>

            <?php if (!empty($threads)): ?>
                <form class="inbox-mark-all" method="post" action="/inbox/bulk">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="scope" value="<?= $e($scope) ?>">
                    <input type="hidden" name="order" value="<?= $e($order) ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <input type="hidden" name="action" value="read">
                    <?php foreach ($threads as $thread): ?><input type="hidden" name="thread_ids[]" value="<?= (int) $thread['id'] ?>"><?php endforeach; ?>
                    <button type="submit">Mark all read</button>
                </form>
            <?php endif; ?>
            <span class="inbox-density"><?= ($appearance['density'] ?? 'comfortable') === 'compact' ? 'Compact' : 'Comfortable' ?> rows <a href="/settings/appearance" title="Density lives in your appearance preferences">change</a></span>
            <span class="inbox-key-hint">Ordered by <?= $e($orderLabel['full']) ?> — j/k move · enter open · e read · s star · # snooze</span>
        </nav>

        <?php if (!empty($threads)): ?>
            <form class="inbox-sweep" id="inbox-bulk-form" method="post" action="/inbox/bulk" data-inbox-sweep>
                <?= $this->csrfField() ?>
                <input type="hidden" name="scope" value="<?= $e($scope) ?>">
                <input type="hidden" name="order" value="<?= $e($order) ?>">
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <input type="hidden" name="until" value="monday">
                <span data-inbox-selection-label>Selected topics</span>
                <button type="submit" name="action" value="read">Mark read</button>
                <button type="submit" name="action" value="unread">Mark unread</button>
                <?php if ($current_user !== null && $current_user->isActive()): ?>
                    <button type="submit" name="action" value="star">Star</button>
                    <?php if (!empty($features['topic_workflow'])): ?><button type="submit" name="action" value="snooze">Snooze until Monday</button><?php endif; ?>
                <?php endif; ?>
            </form>
            <label class="inbox-select-all"><input type="checkbox" data-inbox-select-all> <span>Select all on screen</span></label>
            <ul class="inbox-thread-list" data-inbox-thread-list>
                <?php foreach ($threads as $thread): ?>
                    <?= $this->partial('partials/inbox_thread_row', [
                        't' => $thread,
                        'return_to' => $currentUrl,
                        'order' => $order,
                        'workflow_enabled' => !empty($features['topic_workflow']),
                    ]) ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="inbox-empty-state">
                <?= $this->partial('partials/icon', ['name' => 'eight-point-star', 'class' => 'inbox-empty-star']) ?>
                <p class="inbox-empty-title"><?= $e($emptyTitle) ?></p>
                <p class="muted">This is your <?= $e($scopeLabel) ?> scope — it fills as topics qualify. Order (<?= $e($orderLabel['full']) ?>) changes the sequence, never what is included.</p>
                <?php if ($scope !== 'for_you'): ?><a class="btn btn-small" href="<?= $e(\App\Support\InboxView::query('for_you', $order)) ?>">Back to For You</a><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($total > 0): ?><p class="inbox-shown-count">Showing <?= count($threads) ?> of <?= (int) $total ?> topics</p><?php endif; ?>
        <?= $this->partial('partials/pagination', [
            'page' => $page,
            'pages' => $pages,
            'base_url' => '/inbox?scope=' . rawurlencode($scope) . '&order=' . rawurlencode($order) . '&',
        ]) ?>
    </section>

    <section class="inbox-reading" id="inbox-reading-pane" data-inbox-reading tabindex="-1" aria-label="Reading pane">
        <button class="inbox-mobile-back" type="button" data-inbox-back>
            <?= $this->partial('partials/icon', ['name' => 'chevron-left']) ?>
            <span>Back to topics</span>
        </button>
        <div data-inbox-reading-content>
            <div class="inbox-empty">
                <?= $this->partial('partials/icon', ['name' => 'eight-point-star', 'class' => 'inbox-empty-star']) ?>
                <p class="inbox-empty-title">Choose a topic</p>
                <p class="muted">Select a topic on the left to read it here — your place in the list is kept. Without JavaScript, topics open as their own page.</p>
            </div>
        </div>
    </section>
</div>
