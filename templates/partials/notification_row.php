<?php /** @var \App\Core\View $this */ ?>
<li class="notification-row <?= $item['is_read'] ? 'is-read' : 'is-unread' ?>">
    <form method="post" action="/notifications/<?= (int) $item['id'] ?>/read">
        <?= $this->csrfField() ?><input type="hidden" name="return" value="<?= $e($return_path) ?>">
        <button class="notification-open" type="submit">
            <span class="notification-icon"><?= $this->partial('partials/icon', ['name' => $item['icon']]) ?></span>
            <span class="notification-body">
                <?php if (!$item['is_read']): ?><span class="notification-unread-dot" aria-hidden="true"></span><span class="sr-only">Unread. </span><?php endif; ?>
                <span class="notification-message"><?= $e($item['message']) ?></span>
                <?php if ($item['context'] !== ''): ?><span class="notification-context">“<?= $e($item['context']) ?>”</span><?php endif; ?>
                <span class="sr-only">. <?= $e($item['action_label']) ?>.</span>
            </span>
            <time datetime="<?= $e(iso_datetime($item['created_at'])) ?>" title="<?= $e($item['full_time']) ?>"><?= $e($item['relative_time']) ?></time>
        </button>
    </form>
</li>
