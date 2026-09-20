<?php

declare(strict_types=1);

namespace App\Repository;

/** SQL fragments shared by notification, subscription and delivery selection. */
final class NotificationEligibility
{
    public static function board(array $scope, string $alias = 'b'): string
    {
        if (!empty($scope['is_admin'])) {
            return "$alias.id IS NOT NULL";
        }
        $ids = array_unique(array_map('intval', array_merge($scope['member_board_ids'], $scope['assigned_board_ids'])));
        $assigned = $ids === [] ? '0 = 1' : "$alias.id IN (" . implode(',', $ids) . ')';
        return "($alias.id IS NOT NULL AND ($alias.visibility <> 'private' OR $assigned))";
    }

    /** $recipient and $actor are internal column expressions or validated integer literals. */
    public static function unblocked(string $recipient, string $actor): string
    {
        return "NOT EXISTS (SELECT 1 FROM blocks nb WHERE nb.user_id = $recipient AND nb.blocked_user_id = $actor)
            AND NOT EXISTS (SELECT 1 FROM blocks nb2 WHERE nb2.user_id = $actor AND nb2.blocked_user_id = $recipient)";
    }

    public static function notification(array $scope): string
    {
        if (empty($scope['features']['notifications'])) {
            return '0 = 1';
        }
        $board = self::board($scope);
        $block = self::unblocked('n.user_id', 'n.actor_id');
        $dm = !empty($scope['features']['dms']) ? '1 = 1' : '0 = 1';
        $reports = !empty($scope['may_review_dm_reports']) ? '1 = 1' : '0 = 1';
        return "(n.type IN ('mod', 'announcement', 'badge') OR ($block))
            AND (n.thread_id IS NULL OR (t.id IS NOT NULL AND t.is_deleted = 0 AND t.is_pending = 0 AND $board))
            AND (n.post_id IS NULL OR (pp.id IS NOT NULL AND pp.is_deleted = 0 AND pp.is_pending = 0 AND pp.thread_id = t.id))
            AND (n.conversation_id IS NULL OR (
                EXISTS (SELECT 1 FROM conversations nc WHERE nc.id = n.conversation_id)
                AND ((n.type = 'mod' AND $reports) OR (n.type = 'dm' AND $dm
                    AND EXISTS (SELECT 1 FROM conversation_participants cp
                        WHERE cp.conversation_id = n.conversation_id AND cp.user_id = n.user_id
                          AND n.created_at >= cp.joined_at
                          AND (cp.left_at IS NULL OR n.created_at <= cp.left_at)
                          AND (cp.joined_after_message_id = 0 OR EXISTS (
                              SELECT 1 FROM dm_messages nm WHERE nm.conversation_id = cp.conversation_id
                                AND nm.id > cp.joined_after_message_id AND nm.created_at <= n.created_at
                                AND (cp.left_at IS NULL OR nm.created_at <= cp.left_at)
                          )))))))
            AND (n.type <> 'dm' OR (n.conversation_id IS NOT NULL AND $dm))
            AND (n.type NOT IN ('reply', 'mention', 'reaction', 'new_post', 'new_thread', 'solved') OR n.thread_id IS NOT NULL)";
    }
}
