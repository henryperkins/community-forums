<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BadgeRepository;
use App\Repository\ModerationLogRepository;
use App\Security\WriteGate;

final class BadgeRuleService
{
    private const TYPES = ['post_count', 'thread_count', 'reputation', 'solved_count'];

    public function __construct(
        private Database $db,
        private BadgeRepository $badges,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private ?NotificationService $notifications = null,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function rules(): array
    {
        return $this->badges->rules();
    }

    /** @param array<string,mixed> $input */
    public function create(User $actor, array $input): int
    {
        $this->writeGate->assertCanWrite($actor);
        $badgeId = (int) ($input['badge_id'] ?? 0);
        $ruleType = (string) ($input['rule_type'] ?? '');
        $threshold = (int) ($input['threshold'] ?? 0);
        $boardId = (int) ($input['board_id'] ?? 0);
        $boardId = $boardId > 0 ? $boardId : null;

        $errors = [];
        if ($this->db->fetchValue('SELECT 1 FROM badges WHERE id = ? AND is_enabled = 1 LIMIT 1', [$badgeId]) === false) {
            $errors['badge_id'] = 'Choose an enabled badge.';
        }
        if (!in_array($ruleType, self::TYPES, true)) {
            $errors['rule_type'] = 'Choose an approved rule type.';
        }
        if ($threshold < 1 || $threshold > 1_000_000) {
            $errors['threshold'] = 'Threshold must be between 1 and 1000000.';
        }
        if ($boardId !== null && $this->db->fetchValue('SELECT 1 FROM boards WHERE id = ? LIMIT 1', [$boardId]) === false) {
            $errors['board_id'] = 'Choose an existing board.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        $id = $this->badges->createRule($badgeId, $ruleType, $threshold, $boardId, $actor->id());
        $this->audit($actor->id(), 'badge_rule.create', $id, $ruleType);
        return $id;
    }

    /** @return array{rule:array<string,mixed>,users:array<int,array<string,mixed>>,total:int} */
    public function preview(int $ruleId, int $limit = 100): array
    {
        $rule = $this->ruleOrFail($ruleId);
        $users = $this->eligibleUsers($rule, $limit);
        return ['rule' => $rule, 'users' => $users, 'total' => count($users)];
    }

    public function enable(User $actor, int $ruleId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $this->ruleOrFail($ruleId);
        $this->badges->setRuleEnabled($ruleId, true);
        $this->audit($actor->id(), 'badge_rule.enable', $ruleId);
    }

    public function disable(User $actor, int $ruleId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $this->ruleOrFail($ruleId);
        $this->badges->setRuleEnabled($ruleId, false);
        $this->audit($actor->id(), 'badge_rule.disable', $ruleId);
    }

    public function backfill(User $actor, int $ruleId): int
    {
        $this->writeGate->assertCanWrite($actor);
        $rule = $this->ruleOrFail($ruleId);
        $awarded = 0;
        $this->db->transaction(function () use ($actor, $rule, &$awarded): void {
            foreach ($this->eligibleUsers($rule, 1000) as $user) {
                $key = $this->achievementKey($rule, (int) $user['id']);
                if ($this->badges->awardRuleBadge((int) $user['id'], (int) $rule['badge_id'], (int) $rule['id'], $actor->id(), $key)) {
                    $awarded++;
                    $this->notifications?->notifyBadge((int) $user['id']);
                }
            }
            $this->audit($actor->id(), 'badge_rule.backfill', (int) $rule['id'], 'awarded=' . $awarded);
        });
        return $awarded;
    }

    public function revoke(User $actor, int $ruleId): int
    {
        $this->writeGate->assertCanWrite($actor);
        $rule = $this->ruleOrFail($ruleId);
        $revoked = 0;
        $this->db->transaction(function () use ($actor, $rule, &$revoked): void {
            foreach ($this->badges->awardHistoryUserIds((int) $rule['id']) as $userId) {
                $key = $this->achievementKey($rule, $userId);
                if ($this->badges->revokeRuleBadge($userId, (int) $rule['badge_id'], (int) $rule['id'], $actor->id(), $key)) {
                    $revoked++;
                }
            }
            $this->audit($actor->id(), 'badge_rule.revoke', (int) $rule['id'], 'revoked=' . $revoked);
        });
        return $revoked;
    }

    /** @return array<string,mixed> */
    private function ruleOrFail(int $id): array
    {
        $rule = $this->badges->findRule($id);
        if ($rule === null) {
            throw new NotFoundException('Badge rule not found.');
        }
        return $rule;
    }

    /**
     * @param array<string,mixed> $rule
     * @return array<int,array<string,mixed>>
     */
    private function eligibleUsers(array $rule, int $limit): array
    {
        $limit = max(1, min(1000, $limit));
        $threshold = (int) $rule['threshold'];
        $badgeId = (int) $rule['badge_id'];
        $boardId = $rule['board_id'] === null ? null : (int) $rule['board_id'];

        [$metricSql, $params] = match ((string) $rule['rule_type']) {
            'post_count' => $boardId === null
                ? ['SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.is_deleted = 0 AND p.is_pending = 0', []]
                : ['SELECT COUNT(*) FROM posts p JOIN threads t ON t.id = p.thread_id WHERE p.user_id = u.id AND p.is_deleted = 0 AND p.is_pending = 0 AND t.board_id = ?', [$boardId]],
            'thread_count' => $boardId === null
                ? ['SELECT COUNT(*) FROM threads t WHERE t.user_id = u.id AND t.is_deleted = 0 AND t.is_pending = 0', []]
                : ['SELECT COUNT(*) FROM threads t WHERE t.user_id = u.id AND t.is_deleted = 0 AND t.is_pending = 0 AND t.board_id = ?', [$boardId]],
            'reputation' => $boardId === null
                ? ['SELECT u.reputation', []]
                : ["SELECT COALESCE(SUM(re.applied_delta), 0) FROM reputation_events re WHERE re.user_id = u.id AND re.board_id = ? AND re.reversed_at IS NULL", [$boardId]],
            'solved_count' => $boardId === null
                ? ['SELECT COUNT(*) FROM threads t JOIN posts p ON p.id = t.accepted_answer_post_id WHERE p.user_id = u.id AND p.is_deleted = 0 AND p.is_pending = 0', []]
                : ['SELECT COUNT(*) FROM threads t JOIN posts p ON p.id = t.accepted_answer_post_id WHERE p.user_id = u.id AND p.is_deleted = 0 AND p.is_pending = 0 AND t.board_id = ?', [$boardId]],
            default => ['SELECT 0', []],
        };

        $sql = "SELECT u.id, u.username, u.display_name, ($metricSql) AS metric
                FROM users u
                WHERE u.status = 'active'
                  AND NOT EXISTS (
                    SELECT 1 FROM user_badges ub
                    WHERE ub.user_id = u.id AND ub.badge_id = ?
                  )
                HAVING metric >= ?
                ORDER BY metric DESC, u.id ASC
                LIMIT " . $limit;

        return $this->db->fetchAll($sql, array_merge($params, [$badgeId, $threshold]));
    }

    /** @param array<string,mixed> $rule */
    private function achievementKey(array $rule, int $userId): string
    {
        return 'rule:' . (int) $rule['id'] . ':v' . (int) $rule['version'] . ':user:' . $userId;
    }

    private function audit(?int $actorId, string $action, int $ruleId, ?string $reason = null): void
    {
        $this->log->log([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => 'setting',
            'target_id' => $ruleId,
            'reason' => $reason,
        ]);
    }
}
