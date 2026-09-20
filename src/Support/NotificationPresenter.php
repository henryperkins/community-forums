<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\User;

/** Presentation only: callers supply rows already authorized and privacy-masked. */
final class NotificationPresenter
{
    private const ICONS = [
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

    public static function item(array $authorizedRow, User $viewer): array
    {
        $n = $authorizedRow;
        $actor = (string) (($n['actor_display_name'] ?? '') ?: (($n['actor_username'] ?? '') ?: 'Someone'));
        $type = (string) $n['type'];
        $context = (string) ($n['thread_title'] ?? '');
        $named = $context !== '';
        $appeal = $type === 'mod' && empty($n['thread_id']) && empty($n['post_id']) && empty($n['conversation_id']);
        $message = match ($type) {
            'reply' => $actor . ($named ? ' replied to' : ' replied'),
            'new_thread' => $actor . ' started a thread',
            'new_post' => $actor . ($named ? ' posted in' : ' posted'),
            'mention' => $actor . ($named ? ' mentioned you in' : ' mentioned you'),
            'reaction' => $actor . ' reacted to your post',
            'follow' => $actor . ' followed you',
            'badge' => 'You earned a badge',
            'solved' => 'Your answer was accepted' . ($named ? ' in' : ''),
            'dm' => $actor . ' sent you a message',
            'mod' => $appeal ? 'Your appeal has been resolved' : (!empty($n['conversation_id']) ? 'A direct-message report needs review' : 'A moderator action affects you'),
            'announcement' => 'Announcement',
            default => 'Notification',
        };
        return [
            'id' => (int) $n['id'], 'type' => $type,
            'icon' => self::ICONS[$type] ?? self::ICONS['reply'],
            'message' => $message, 'context' => $context,
            'is_read' => (int) $n['is_read'] === 1,
            'created_at' => (string) $n['created_at'],
            'relative_time' => relative_datetime((string) $n['created_at']),
            'full_time' => human_datetime((string) $n['created_at']),
            'action_label' => $appeal ? 'Acknowledge notification' : 'Open notification',
        ];
    }
}
