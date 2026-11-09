<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\User;

/** Presentation only: callers supply rows already authorized and privacy-masked. */
final class NotificationPresenter
{
    /**
     * The icon each notification type draws (ADR 0060): the Comment family for
     * discussion, Chat for direct messages and the moderation shield the
     * account menu uses. Unknown types draw the reply icon.
     */
    public const ICONS = [
        'reply' => 'reply',
        'new_thread' => 'new-topic',
        'new_post' => 'comment',
        'mention' => 'mention',
        'reaction' => 'reaction',
        'follow' => 'follow',
        'badge' => 'badge',
        'solved' => 'solved',
        'dm' => 'direct-message',
        'mod' => 'shield',
        'announcement' => 'announcement',
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
