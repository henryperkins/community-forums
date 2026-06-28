<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Notifications'); ?>
<?php
$verb = static function (array $n): string {
    $actor = ($n['actor_display_name'] ?? '') !== '' ? $n['actor_display_name'] : ($n['actor_username'] ?? 'Someone');
    return match ($n['type']) {
        'reply' => $actor . ' replied',
        'new_thread' => $actor . ' started a thread',
        'new_post' => $actor . ' posted',
        'mention' => $actor . ' mentioned you',
        'reaction' => $actor . ' reacted to your post',
        'follow' => $actor . ' followed you',
        'badge' => 'You earned a badge',
        'solved' => 'Your answer was accepted',
        'dm' => $actor . ' sent you a message',
        'mod' => ($n['conversation_id'] ?? null) !== null ? 'A direct-message report needs review' : 'A moderator action affects you',
        'announcement' => 'Announcement',
        default => 'Notification',
    };
};
?>
<div class="notifications-view">
    <header class="board-header">
        <h1>Notifications
            <?php if ((int) $unread_count > 0): ?><span class="badge"><?= (int) $unread_count ?> unread</span><?php endif; ?>
        </h1>
        <?php if (!empty($notifications)): ?>
            <div class="notif-actions">
                <form class="inline" method="post" action="/notifications/read-all">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit">Mark all read</button>
                </form>
                <form class="inline" method="post" action="/notifications/clear">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn danger" type="submit">Clear all</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($notifications)): ?>
        <p class="muted empty">No notifications yet.</p>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($notifications as $n): ?>
                <li class="notif-row<?= (int) $n['is_read'] === 0 ? ' notif-unread' : '' ?>">
                    <form class="notif-open" method="post" action="/notifications/<?= (int) $n['id'] ?>/read">
                        <?= $this->csrfField() ?>
                        <button class="notif-link" type="submit">
                            <span class="notif-text"><?= $e($verb($n)) ?></span>
                            <?php if (($n['thread_title'] ?? '') !== ''): ?>
                                <span class="notif-thread">— <?= $e($n['thread_title']) ?></span>
                            <?php endif; ?>
                            <span class="notif-time"><?= $e(human_datetime($n['created_at'])) ?></span>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
