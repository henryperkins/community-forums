<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationAppealRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Security\Cap;

final class AppealService
{
    private const OUTCOMES = ['upheld', 'modified', 'reversed', 'dismissed'];

    public function __construct(
        private Database $db,
        private ModerationAppealRepository $appeals,
        private ModerationLogRepository $logs,
        private NotificationRepository $notifications,
        private PostRepository $posts,
        private UserRepository $users,
        private ModerationService $moderation,
        private UserModerationService $userModeration,
        private BoardModeratorRepository $boardMods,
    ) {
    }

    public function openForPost(User $appellant, int $postId, string $reason): int
    {
        $reason = $this->requireReason($reason);
        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['user_id'] !== $appellant->id() || (int) $post['is_deleted'] !== 1) {
            throw new NotFoundException('Appealable post not found.');
        }
        $deletedAt = (string) ($post['deleted_at'] ?? '');
        if ($deletedAt === '' || (strtotime($deletedAt . ' UTC') ?: 0) < time() - 30 * 86400) {
            throw new ValidationException(['reason' => 'Appeals must be opened within 30 days of the moderation action.']);
        }
        if ($this->appeals->activeForTarget($appellant->id(), 'post', $postId) !== null) {
            throw new ValidationException(['reason' => 'You already have an open appeal for this target.']);
        }
        $log = $this->db->fetch(
            "SELECT id, action FROM moderation_log
             WHERE target_type = 'post' AND target_id = ? AND action = 'delete_post'
             ORDER BY id DESC LIMIT 1",
            [$postId],
        );
        if ($log === null) {
            throw new ValidationException(['reason' => 'Only moderator removals can be appealed here.']);
        }

        return $this->db->transaction(function () use ($appellant, $post, $postId, $reason, $log): int {
            $id = $this->appeals->create([
                'appellant_id' => $appellant->id(),
                'target_type' => 'post',
                'target_id' => $postId,
                'moderation_log_id' => (int) $log['id'],
                'original_action' => (string) $log['action'],
                'target_summary' => mb_strimwidth((string) $post['body'], 0, 180, '...'),
                'reason' => $reason,
            ]);
            $this->appeals->event($id, $appellant->id(), 'opened', $reason);
            $this->logs->log([
                'actor_id' => $appellant->id(),
                'action' => 'appeal_opened',
                'target_type' => 'post',
                'target_id' => $postId,
                'reason' => 'appeal:' . $id,
                'after' => ['appeal_id' => $id],
            ]);
            return $id;
        });
    }

    public function openForModerationLog(User $appellant, int $logId, string $reason): int
    {
        $reason = $this->requireReason($reason);
        $log = $this->db->fetch('SELECT * FROM moderation_log WHERE id = ?', [$logId]);
        if ($log === null || (string) $log['target_type'] !== 'user' || (int) $log['target_id'] !== $appellant->id()) {
            throw new NotFoundException('Appealable moderation action not found.');
        }
        $action = (string) $log['action'];
        if (!in_array($action, ['warn', 'suspend', 'ban', 'clear_signature', 'clear_avatar'], true)) {
            throw new ValidationException(['reason' => 'That moderation action is not appealable.']);
        }
        $created = strtotime((string) $log['created_at'] . ' UTC') ?: 0;
        if ($created < time() - 30 * 86400) {
            throw new ValidationException(['reason' => 'Appeals must be opened within 30 days of the moderation action.']);
        }
        if ($this->appeals->activeForTarget($appellant->id(), 'user', $appellant->id()) !== null) {
            throw new ValidationException(['reason' => 'You already have an open appeal for this target.']);
        }

        return $this->db->transaction(function () use ($appellant, $log, $action, $reason): int {
            $id = $this->appeals->create([
                'appellant_id' => $appellant->id(),
                'target_type' => 'user',
                'target_id' => $appellant->id(),
                'moderation_log_id' => (int) $log['id'],
                'original_action' => $action,
                'target_summary' => $action,
                'reason' => $reason,
            ]);
            $this->appeals->event($id, $appellant->id(), 'opened', $reason);
            $this->logs->log([
                'actor_id' => $appellant->id(),
                'action' => 'appeal_opened',
                'target_type' => 'user',
                'target_id' => $appellant->id(),
                'reason' => 'appeal:' . $id,
                'after' => ['appeal_id' => $id, 'original_action' => $action],
            ]);
            return $id;
        });
    }

    public function resolve(User $actor, int $appealId, string $outcome, string $note): void
    {
        if (!in_array($outcome, self::OUTCOMES, true)) {
            throw new ValidationException(['outcome' => 'Choose a valid appeal outcome.']);
        }
        $appeal = $this->appeals->find($appealId);
        if ($appeal === null || (string) $appeal['status'] !== 'open') {
            throw new NotFoundException('Appeal not found.');
        }
        $note = trim($note);

        $this->assertCanResolve($actor, $appeal);
        $this->db->transaction(function () use ($actor, $appeal, $appealId, $outcome, $note): void {
            if ($outcome === 'reversed') {
                $this->reverseTarget($actor, $appeal, $note);
            }
            if (!$this->appeals->resolve($appealId, $actor->id(), $outcome, $note)) {
                return;
            }
            $this->appeals->event($appealId, $actor->id(), $outcome, $note !== '' ? $note : null);
            $this->logs->log([
                'actor_id' => $actor->id(),
                'action' => 'appeal_resolved',
                'target_type' => (string) $appeal['target_type'],
                'target_id' => (int) $appeal['target_id'],
                'reason' => 'appeal:' . $appealId,
                'after' => ['status' => $outcome, 'note' => $note],
            ]);
            $this->notifications->create([
                'user_id' => (int) $appeal['appellant_id'],
                'type' => 'mod',
                'actor_id' => $actor->id(),
            ]);
        });
    }

    /**
     * The member /appeals view model (PR #44 spec §4): current appeals plus
     * the eligible targets the no-JS forms render from — one shape for the
     * happy render AND both 422 re-renders, so a failed submit can never drop
     * the form context.
     *
     * @return array{
     *   appeals:array<int,array<string,mixed>>,
     *   eligible:array{posts:array<int,array<string,mixed>>,moderation_logs:array<int,array<string,mixed>>}
     * }
     */
    public function memberViewModel(int $userId): array
    {
        return [
            'appeals' => $this->forUser($userId),
            'eligible' => $this->eligibleTargetsForUser($userId),
        ];
    }

    /**
     * The staff /mod/appeals view model — queue rows + the outcome vocabulary.
     *
     * @return array{appeals:array<int,array<string,mixed>>, outcomes:list<string>}
     */
    public function queueViewModel(User $actor): array
    {
        return [
            'appeals' => $this->queue($actor),
            'outcomes' => self::OUTCOMES,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return $this->appeals->forUser($userId);
    }

    /**
     * Member-facing appeal targets that can be opened without JavaScript.
     *
     * @return array{
     *   posts:array<int,array<string,mixed>>,
     *   moderation_logs:array<int,array<string,mixed>>
     * }
     */
    public function eligibleTargetsForUser(int $userId): array
    {
        $posts = $this->db->fetchAll(
            "SELECT p.id, p.body, p.deleted_at, p.thread_id,
                    t.title AS thread_title, t.slug AS thread_slug,
                    (
                        SELECT ml.id
                        FROM moderation_log ml
                        WHERE ml.target_type = 'post'
                          AND ml.target_id = p.id
                          AND ml.action = 'delete_post'
                        ORDER BY ml.id DESC
                        LIMIT 1
                    ) AS moderation_log_id
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             LEFT JOIN moderation_appeals a
               ON a.appellant_id = :uid_active
              AND a.target_type = 'post'
              AND a.target_id = p.id
              AND a.status = 'open'
             WHERE p.user_id = :uid_posts
               AND p.is_deleted = 1
               AND p.deleted_at IS NOT NULL
               AND p.deleted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
               AND a.id IS NULL
               AND EXISTS (
                   SELECT 1 FROM moderation_log ml
                   WHERE ml.target_type = 'post'
                     AND ml.target_id = p.id
                     AND ml.action = 'delete_post'
               )
             ORDER BY p.deleted_at DESC, p.id DESC
             LIMIT 20",
            ['uid_active' => $userId, 'uid_posts' => $userId],
        );

        $hasOpenUserAppeal = $this->appeals->activeForTarget($userId, 'user', $userId) !== null;
        $logs = $hasOpenUserAppeal ? [] : $this->db->fetchAll(
            "SELECT id, action, reason, created_at
             FROM moderation_log
             WHERE target_type = 'user'
               AND target_id = ?
               AND action IN ('warn', 'suspend', 'ban', 'clear_signature', 'clear_avatar')
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
             ORDER BY created_at DESC, id DESC
             LIMIT 20",
            [$userId],
        );

        return ['posts' => $posts, 'moderation_logs' => $logs];
    }

    /**
     * Staff appeal queue, scoped to the actor's board authority (mirrors the
     * report queue): admins see every open appeal; a board moderator sees only
     * post appeals in boards they moderate. A user who moderates nothing is
     * refused. Authority comes from board_moderators, never a bare users.role.
     *
     * @return array<int,array<string,mixed>>
     */
    public function queue(User $actor): array
    {
        $isAdmin = $actor->isAdmin();
        $boardIds = $isAdmin ? [] : $this->boardMods->boardsFor($actor->id());
        if (!$isAdmin && $boardIds === []) {
            throw new ForbiddenException('Staff access required.');
        }
        return $this->appeals->openQueue($isAdmin, $boardIds);
    }

    /** @param array<string,mixed> $appeal */
    private function assertCanResolve(User $actor, array $appeal): void
    {
        if ((string) $appeal['target_type'] === 'user') {
            if (!$actor->isAdmin()) {
                throw new ForbiddenException('Administrator access required.');
            }
            return;
        }

        $post = $this->posts->findWithContext((int) $appeal['target_id']);
        if ($post === null || !$this->moderation->canModerate($actor, (int) $post['board_id'], Cap::APPEAL_RESOLVE_CONTENT)) {
            throw new ForbiddenException('You do not moderate this appeal target.');
        }
    }

    /** @param array<string,mixed> $appeal */
    private function reverseTarget(User $actor, array $appeal, string $note): void
    {
        if ((string) $appeal['target_type'] === 'post') {
            $this->moderation->restorePost($actor, (int) $appeal['target_id'], $note);
            return;
        }

        if (in_array((string) ($appeal['original_action'] ?? ''), ['suspend', 'ban'], true)) {
            $this->userModeration->lift($actor, (int) $appeal['target_id']);
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException(['reason' => 'Explain why you are appealing this moderation action.']);
        }
        if (mb_strlen($reason) > 2000) {
            throw new ValidationException(['reason' => 'Appeal text must be 2000 characters or fewer.']);
        }
        return $reason;
    }
}
