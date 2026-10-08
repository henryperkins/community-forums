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
$selectedIds = array_fill_keys($selected_thread_ids ?? [], true);
?>
<?php $this->start('subheader_leading'); ?>
<div class="page-toolbar inbox-toolbar">
    <div class="inbox-title-line">
        <h1 class="forum-page-title">Inbox</h1>
        <?php if ((int) $unread_count > 0): ?>
            <span class="badge" data-inbox-unread-count="<?= (int) $unread_count ?>"><?= (int) $unread_count ?> unread</span>
        <?php endif; ?>
    </div>
    <?= $this->partial('partials/inbox_view_controls', compact('scope', 'order', 'scopeLabel', 'orderLabel', 'available', 'scope_counts', 'total')) ?>
                <details class="inbox-actions inbox-menu" name="inbox-controls" data-inbox-menu data-inbox-menu-align="end">
                    <summary aria-label="Inbox actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                    <div class="inbox-menu-panel">
                        <?php if (!empty($threads)): ?>
                            <form class="inbox-mark-all" method="post" action="/inbox/bulk">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="scope" value="<?= $e($scope) ?>">
                                <input type="hidden" name="order" value="<?= $e($order) ?>">
                                <input type="hidden" name="page" value="<?= (int) $page ?>">
                                <input type="hidden" name="action" value="read">
                                <?php foreach ($threads as $thread): ?><input type="hidden" name="thread_ids[]" value="<?= (int) $thread['id'] ?>"><?php endforeach; ?>
                                <button type="submit">Mark this page read</button>
                            </form>
                        <?php endif; ?>
                        <details class="inbox-help">
                            <summary>Help</summary>
                            <div class="inbox-help-panel" data-inbox-help-content>
                            <header class="inbox-help-head"><h2 id="inbox-help-heading">Inbox help</h2><button type="button" data-inbox-help-close hidden aria-label="Close Inbox help"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button></header>
                            <p>The view chooses which topics appear. Sorting changes their order.</p>
                            <?php if (!empty($features['topic_workflow'])): ?><p>Til tomorrow hides a topic for 24 hours. Turn Show in Inbox off to hide it until you restore it. Find either kind in Snoozed.</p><?php endif; ?>
                            <div class="inbox-keyboard-help">
                                <p>Keyboard shortcuts</p>
                                <dl>
                                    <div><dt><kbd>j</kbd> / <kbd>k</kbd></dt><dd>Move between topics</dd></div>
                                    <div><dt><kbd>Enter</kbd></dt><dd>Open topic</dd></div>
                                    <div><dt><kbd>e</kbd></dt><dd>Mark read</dd></div>
                                    <?php if ($current_user !== null && $current_user->isActive()): ?>
                                        <div><dt><kbd>s</kbd></dt><dd>Star topic</dd></div>
                                        <?php if (!empty($features['topic_workflow'])): ?><div><dt><kbd>#</kbd></dt><dd>Til tomorrow</dd></div><?php endif; ?>
                                    <?php endif; ?>
                                </dl>
                            </div>
                            </div>
                        </details>
                        <button type="button" class="inbox-help-open" data-inbox-help-open hidden>Help <?= $this->partial('partials/icon', ['name' => 'chevron-right']) ?></button>
                    </div>
                </details>
</div>
<?php $this->stop(); ?>
<div class="inbox-shell" data-inbox data-inbox-url="<?= $e($currentUrl) ?>" data-inbox-scope="<?= $e($scope) ?>" data-inbox-order="<?= $e($order) ?>">
    <section class="inbox-list" data-inbox-list tabindex="-1" aria-label="Topics">
        <?php if (!empty($inbox_error)): ?><p class="inbox-feedback is-active" role="alert"><?= $e($inbox_error) ?> Your available selections have been kept.</p><?php endif; ?>
        <div class="inbox-feedback" data-inbox-feedback>
            <p role="status" aria-live="polite" aria-atomic="true" data-inbox-status></p>
            <div class="inbox-recovery" data-inbox-recovery hidden>
                <button class="btn btn-small" type="button" data-inbox-retry>Try again</button>
                <a class="btn btn-small" data-inbox-full-topic>Open full topic</a>
                <button class="btn btn-small" type="button" data-inbox-return-preview hidden>Return to previous topic</button>
            </div>
        </div>
        <template data-inbox-empty-template><?= $this->partial('partials/inbox_empty', compact('emptyTitle', 'scope', 'order')) ?></template>
        <template data-inbox-page-read-template><div class="inbox-empty-state" tabindex="-1" data-inbox-empty-state><p class="inbox-empty-title">You've read every topic on this page.</p><a class="btn btn-small" href="<?= $e($currentUrl) ?>">Load remaining topics</a></div></template>
        <?php if (!empty($threads)): ?>
            <form class="inbox-sweep<?= $selectedIds !== [] ? ' is-active' : '' ?>" id="inbox-bulk-form" method="post" action="/inbox/bulk" data-inbox-sweep>
                <?= $this->csrfField() ?>
                <input type="hidden" name="scope" value="<?= $e($scope) ?>">
                <input type="hidden" name="order" value="<?= $e($order) ?>">
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <input type="hidden" name="until" value="tomorrow">
                <span data-inbox-selection-label aria-live="polite"><?= $selectedIds !== [] ? count($selectedIds) . ' selected' : 'Selected topics' ?></span>
                <button type="submit" name="action" value="read">Mark read</button>
                <details class="inbox-menu inbox-bulk-menu" name="inbox-controls" data-inbox-menu data-inbox-bulk-menu data-inbox-menu-align="end">
                    <summary>Actions <?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?></summary>
                    <div class="inbox-menu-panel">
                        <button type="submit" name="action" value="unread">Mark unread</button>
                        <?php if ($current_user !== null && $current_user->isActive()): ?>
                            <button type="submit" name="action" value="star">Star</button>
                            <?php if (!empty($features['topic_workflow'])): ?>
                                <div class="inbox-menu-divider"></div>
                                <button type="submit" name="action" value="snooze">Til tomorrow</button>
                                <?php if ($scope === 'snoozed'): ?>
                                    <button type="submit" name="action" value="restore">Show in Inbox</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="hide"><span>Hide from Inbox</span><span class="inbox-menu-note">Until you restore them</span></button>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </details>
                <button type="button" class="inbox-clear-selection" data-inbox-clear-selection hidden aria-label="Clear selection"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button>
            </form>
            <label class="inbox-select-all"><input type="checkbox" data-inbox-select-all> <span>Select all on this page</span></label>
            <ul class="inbox-thread-list" data-inbox-thread-list>
                <?php foreach ($threads as $thread): ?>
                    <?= $this->partial('partials/thread_row', [
                        't' => $thread,
                        'presentation' => 'inbox',
                        'return_to' => $currentUrl,
                        'order' => $order,
                        'workflow_enabled' => !empty($features['topic_workflow']),
                        'selected' => isset($selectedIds[(int) $thread['id']]),
                    ]) ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= $this->partial('partials/inbox_empty', compact('emptyTitle', 'scope', 'order')) ?>
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
                <p class="muted">Choose a topic to read it here. Your place in the list is kept.</p>
            </div>
        </div>
    </section>
</div>
