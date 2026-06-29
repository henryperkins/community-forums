<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BadgeRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;

/**
 * Badge awarding (COMMUNITY §6, P2-09). Auto badges award on their triggering
 * event by re-deriving the milestone from authoritative counts; manual badges
 * are admin-granted. Awards are idempotent (one row per user+badge), so this is
 * safe to call after every post/reaction/solved event — it only ever notifies on
 * a genuinely new award.
 */
final class BadgeService
{
    private const AUTO = [
        'welcome', 'first-post', 'first-thread', 'conversation-starter',
        'appreciated', 'well-liked', 'problem-solver', 'trusted-answerer', 'anniversary',
    ];

    public function __construct(
        private Database $db,
        private BadgeRepository $badges,
        private UserRepository $users,
        private ?NotificationService $notifications = null,
        private int $conversationStarterThreads = 10,
        private int $trustedAnswererSolved = 10,
        private int $appreciatedRep = 100,
        private int $wellLikedRep = 1000,
        private ?ModerationLogRepository $log = null,
        private ?WriteGate $writeGate = null,
    ) {
    }

    /**
     * Award any newly-earned automatic badges to $userId and notify once each.
     * Cheap when nothing is pending: it first checks which auto badges are still
     * missing and only computes the metrics those require.
     *
     * @return list<string> slugs newly awarded
     */
    public function evaluateForUser(int $userId): array
    {
        $held = [];
        foreach ($this->badges->forUser($userId) as $b) {
            $held[(string) $b['slug']] = true;
        }
        $missing = array_values(array_filter(self::AUTO, static fn (string $s): bool => !isset($held[$s])));
        if ($missing === []) {
            return [];
        }

        $user = $this->users->find($userId);
        if ($user === null) {
            return [];
        }
        $missingSet = array_flip($missing);

        $threadCount = isset($missingSet['first-thread']) || isset($missingSet['conversation-starter'])
            ? $this->threadCount($userId) : 0;
        $replyCount = isset($missingSet['first-post']) ? $this->replyCount($userId) : 0;
        $solved = isset($missingSet['problem-solver']) || isset($missingSet['trusted-answerer'])
            ? $this->users->solvedAnswerCount($userId) : 0;
        $reputation = (int) ($user['reputation'] ?? 0);

        $earned = [];
        if (isset($missingSet['welcome']) && ($user['email_verified_at'] ?? null) !== null) {
            $earned[] = 'welcome';
        }
        if (isset($missingSet['first-thread']) && $threadCount >= 1) {
            $earned[] = 'first-thread';
        }
        if (isset($missingSet['first-post']) && $replyCount >= 1) {
            $earned[] = 'first-post';
        }
        if (isset($missingSet['conversation-starter']) && $threadCount >= $this->conversationStarterThreads) {
            $earned[] = 'conversation-starter';
        }
        if (isset($missingSet['appreciated']) && $reputation >= $this->appreciatedRep) {
            $earned[] = 'appreciated';
        }
        if (isset($missingSet['well-liked']) && $reputation >= $this->wellLikedRep) {
            $earned[] = 'well-liked';
        }
        if (isset($missingSet['problem-solver']) && $solved >= 1) {
            $earned[] = 'problem-solver';
        }
        if (isset($missingSet['trusted-answerer']) && $solved >= $this->trustedAnswererSolved) {
            $earned[] = 'trusted-answerer';
        }
        if (isset($missingSet['anniversary']) && $this->isAnniversary((string) ($user['created_at'] ?? ''))) {
            $earned[] = 'anniversary';
        }

        $awarded = [];
        foreach ($earned as $slug) {
            if ($this->badges->awardBySlug($userId, $slug)) {
                $awarded[] = $slug;
                $this->notifications?->notifyBadge($userId);
            }
        }
        return $awarded;
    }

    /** Admin manual grant of a `kind=manual` badge (COMMUNITY §6, ADMIN §5.2). */
    public function grantManual(User $admin, int $userId, string $slug, ?string $reason = null): void
    {
        $this->writeGate?->assertCanWrite($admin);
        $badge = $this->badges->findBySlug($slug);
        if ($badge === null || $badge['kind'] !== 'manual') {
            throw new ValidationException(['slug' => 'That badge cannot be granted manually.']);
        }
        $this->db->transaction(function () use ($admin, $userId, $slug, $reason): void {
            if ($this->badges->awardBySlug($userId, $slug, $admin->id())) {
                $this->audit($admin->id(), 'badge.grant', $userId, $reason);
                $this->notifications?->notifyBadge($userId);
            }
        });
    }

    /** Moderator lever: clear a manually-granted badge (COMMUNITY §10). Silent (no notification). */
    public function revokeManual(User|int $actor, int $userId, string $slug, ?string $reason = null): bool
    {
        if ($actor instanceof User) {
            $this->writeGate?->assertCanWrite($actor);
        }
        $badge = $this->badges->findBySlug($slug);
        if ($badge === null || $badge['kind'] !== 'manual') {
            throw new ValidationException(['slug' => 'That badge cannot be revoked manually.']);
        }
        $actorId = $actor instanceof User ? $actor->id() : $actor;
        return $this->db->transaction(function () use ($actorId, $userId, $slug, $reason): bool {
            if ($this->badges->revokeBySlug($userId, $slug)) {
                $this->audit($actorId, 'badge.revoke', $userId, $reason);
                return true;
            }
            return false;
        });
    }

    private function audit(?int $actorId, string $action, int $userId, ?string $reason): void
    {
        $this->log?->log([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $userId,
            'reason' => $reason,
        ]);
    }

    private function threadCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM threads WHERE user_id = ? AND is_deleted = 0',
            [$userId],
        );
    }

    private function replyCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM posts WHERE user_id = ? AND is_op = 0 AND is_deleted = 0',
            [$userId],
        );
    }

    private function isAnniversary(string $createdAt): bool
    {
        if ($createdAt === '') {
            return false;
        }
        $ts = strtotime($createdAt . ' UTC');
        return $ts !== false && $ts <= time() - 31536000; // ≥ 365 days
    }
}
