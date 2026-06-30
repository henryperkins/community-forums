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
// Per-type Lucide line-icon paths (CSP-safe inline SVG — no script/style).
$notifIcons = [
    'reply' => ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    'new_thread' => ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    'new_post' => ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    'mention' => ['M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8'],
    'reaction' => ['M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z'],
    'follow' => ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M19 8v6', 'M22 11h-6'],
    'badge' => ['M12 15m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0', 'M8.2 13.9 7 22l5-3 5 3-1.2-8.1'],
    'solved' => ['M22 11.1V12a10 10 0 1 1-5.9-9.1', 'M22 4 12 14.01l-3-3'],
    'dm' => ['M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'm22 6-10 7L2 6'],
    'mod' => ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    'announcement' => ['M3 11l18-5v12L3 14v-3z', 'M11.6 16.8a3 3 0 1 1-5.8-1.6'],
];
?>
<div class="read-main read-pad notifications-view">
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
                            <span class="notif-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><?php foreach (($notifIcons[$n['type']] ?? $notifIcons['reply']) as $d): ?><path d="<?= $e($d) ?>"></path><?php endforeach; ?></svg>
                            </span>
                            <span class="notif-body">
                                <span class="notif-text"><?= $e($verb($n)) ?></span>
                                <?php if (($n['thread_title'] ?? '') !== ''): ?>
                                    <span class="notif-thread">— <?= $e($n['thread_title']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="notif-time"><?= $e(human_datetime($n['created_at'])) ?></span>
                            <span class="notif-dot<?= (int) $n['is_read'] === 1 ? ' is-read' : '' ?>" aria-hidden="true"></span>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
