<?php /** Presentation page and validated paths supplied by the controller. */ ?>
<section class="notification-panel">
    <header class="notification-heading">
        <h1 data-notification-heading aria-label="<?= $page['unread'] > 0 ? 'Notifications, ' . (int) $page['unread'] . ' unread' : 'Notifications' ?>">Notifications <span class="badge" data-notification-count aria-hidden="true"<?= $page['unread'] === 0 ? ' hidden' : '' ?>><?= $page['unread'] > 99 ? '99+' : (int) $page['unread'] ?></span></h1>
        <div class="notification-actions">
            <form method="post" action="/notifications/read-all"><?= $this->csrfField() ?><input type="hidden" name="return" value="<?= $e($return_path) ?>"><button class="linkbtn" type="submit"<?= $page['unread'] === 0 ? ' disabled' : '' ?>>Mark all read</button></form>
            <form method="post" action="/notifications/clear"><?= $this->csrfField() ?><input type="hidden" name="return" value="<?= $e($return_path) ?>"><button class="linkbtn danger" type="submit"<?= empty($page['items']) && $page['unread'] === 0 && empty($page['before']) && !$page['unread_only'] ? ' disabled' : '' ?>>Clear all</button></form>
        </div>
    </header>
    <p class="notification-prose">Activity involving your account. Topics you follow are in your <a href="/inbox">inbox</a>. <a href="/settings/notifications">Notification settings</a></p>
    <nav class="notification-history" aria-label="Notification history">
        <?php foreach (['all' => 'All', 'unread' => 'Unread'] as $filter => $label): ?>
            <a href="<?= $e(\App\Service\NotificationReadService::historyUrl($base_path, ['filter' => $filter])) ?>"<?= $page['unread_only'] === ($filter === 'unread') ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($page['before'] !== null): ?><a href="<?= $e(\App\Service\NotificationReadService::historyUrl($base_path, ['filter' => $page['unread_only'] ? 'unread' : 'all'])) ?>">Latest</a><?php endif; ?>
        <?php if ($page['next_before'] !== null): ?><a rel="next" href="<?= $e(\App\Service\NotificationReadService::historyUrl($base_path, ['filter' => $page['unread_only'] ? 'unread' : 'all', 'before' => $page['next_before']])) ?>">Next</a><?php endif; ?>
    </nav>
    <?php if (empty($page['items'])): ?><p class="muted empty"><?= $page['unread_only'] ? 'All caught up. No unread notifications.' : ($page['before'] !== null ? 'No older notifications.' : 'No notifications yet.') ?></p><?php endif; ?>
    <ul class="notification-list" data-notification-list>
        <?php foreach ($page['items'] as $item): ?><?= $this->partial('partials/notification_row', ['item' => $item, 'return_path' => $return_path]) ?><?php endforeach; ?>
    </ul>
</section>
