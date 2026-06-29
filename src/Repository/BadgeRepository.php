<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Badge catalogue + awards (COMMUNITY §6, P2-09). The catalogue is seeded by
 * migration 0040; awarding is idempotent (one row per user+badge), so auto
 * triggers and re-runs never double-award.
 */
final class BadgeRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM badges WHERE slug = ?', [$slug]);
    }

    /** @return array<int,array<string,mixed>> the whole catalogue, stable order */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM badges ORDER BY id ASC');
    }

    /**
     * Award $slug to $userId, once. Returns true only when newly awarded (so the
     * caller fires a single badge notification). awardedBy is set for manual grants.
     */
    public function awardBySlug(int $userId, string $slug, ?int $awardedBy = null): bool
    {
        $badge = $this->findBySlug($slug);
        if ($badge === null) {
            return false;
        }
        return $this->db->run(
            'INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_at, awarded_by)
             VALUES (?, ?, UTC_TIMESTAMP(), ?)',
            [$userId, (int) $badge['id'], $awardedBy],
        )->rowCount() === 1;
    }

    public function hasBadgeSlug(int $userId, string $slug): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = ? AND b.slug = ? LIMIT 1',
            [$userId, $slug],
        ) !== false;
    }

    /** Remove a manually-granted badge (moderation lever, COMMUNITY §10). @return bool removed */
    public function revokeBySlug(int $userId, string $slug): bool
    {
        return $this->db->run(
            'DELETE ub FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = ? AND b.slug = ?',
            [$userId, $slug],
        )->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> badges this user holds, earliest first */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT b.slug, b.name, b.description, b.icon, b.kind, ub.awarded_at
             FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = ?
             ORDER BY ub.awarded_at ASC, b.id ASC',
            [$userId],
        );
    }

    /**
     * Manual badges available for admin grant (ADMIN §5.2): enabled, manual-kind,
     * in display order then name.
     *
     * @return array<int,array<string,mixed>>
     */
    public function manualCatalogue(): array
    {
        return $this->db->fetchAll(
            "SELECT id, slug, name, description, icon
             FROM badges
             WHERE kind = 'manual' AND is_enabled = 1
             ORDER BY display_order ASC, name ASC",
        );
    }

    /**
     * Manual badges $userId currently holds, so the record screen can render a
     * revoke control per held manual badge. Earliest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function manualHeldByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT b.slug, b.name, b.icon, ub.awarded_at
             FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = ? AND b.kind = 'manual'
             ORDER BY ub.awarded_at ASC, b.id ASC",
            [$userId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function rules(): array
    {
        return $this->db->fetchAll(
            'SELECT br.*, b.slug AS badge_slug, b.name AS badge_name, boards.name AS board_name
             FROM badge_rules br
             JOIN badges b ON b.id = br.badge_id
             LEFT JOIN boards ON boards.id = br.board_id
             ORDER BY br.created_at DESC, br.id DESC',
        );
    }

    /** @return array<string,mixed>|null */
    public function findRule(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT br.*, b.slug AS badge_slug, b.name AS badge_name, boards.name AS board_name
             FROM badge_rules br
             JOIN badges b ON b.id = br.badge_id
             LEFT JOIN boards ON boards.id = br.board_id
             WHERE br.id = ?',
            [$id],
        );
    }

    public function createRule(int $badgeId, string $ruleType, int $threshold, ?int $boardId, int $actorId): int
    {
        return $this->db->insert(
            'INSERT INTO badge_rules (badge_id, rule_type, threshold, board_id, repeatable, is_enabled, version, created_by, created_at)
             VALUES (?, ?, ?, ?, 0, 0, 1, ?, UTC_TIMESTAMP())',
            [$badgeId, $ruleType, $threshold, $boardId, $actorId],
        );
    }

    public function setRuleEnabled(int $id, bool $enabled): void
    {
        $this->db->run(
            'UPDATE badge_rules SET is_enabled = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$enabled ? 1 : 0, $id],
        );
    }

    public function awardRuleBadge(int $userId, int $badgeId, int $ruleId, int $actorId, string $achievementKey): bool
    {
        $inserted = $this->db->run(
            'INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_at, awarded_by)
             VALUES (?, ?, UTC_TIMESTAMP(), ?)',
            [$userId, $badgeId, $actorId],
        )->rowCount() === 1;

        if ($inserted) {
            $this->db->run(
                "INSERT IGNORE INTO badge_award_history
                    (user_id, badge_id, badge_rule_id, achievement_key, action, actor_id, reason, created_at)
                 VALUES (?, ?, ?, ?, 'award', ?, 'badge_rule_backfill', UTC_TIMESTAMP())",
                [$userId, $badgeId, $ruleId, $achievementKey, $actorId],
            );
        }

        return $inserted;
    }

    /** @return list<int> */
    public function awardHistoryUserIds(int $ruleId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT user_id FROM badge_award_history
             WHERE badge_rule_id = ? AND action = 'award'
               AND NOT EXISTS (
                 SELECT 1 FROM badge_award_history r
                 WHERE r.badge_rule_id = badge_award_history.badge_rule_id
                   AND r.user_id = badge_award_history.user_id
                   AND r.achievement_key = badge_award_history.achievement_key
                   AND r.action = 'revoke'
               )
             ORDER BY user_id ASC",
            [$ruleId],
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    public function revokeRuleBadge(int $userId, int $badgeId, int $ruleId, int $actorId, string $achievementKey): bool
    {
        $removed = $this->db->run(
            'DELETE FROM user_badges WHERE user_id = ? AND badge_id = ?',
            [$userId, $badgeId],
        )->rowCount() > 0;

        if ($removed) {
            $this->db->run(
                "INSERT IGNORE INTO badge_award_history
                    (user_id, badge_id, badge_rule_id, achievement_key, action, actor_id, reason, created_at)
                 VALUES (?, ?, ?, ?, 'revoke', ?, 'badge_rule_revoke', UTC_TIMESTAMP())",
                [$userId, $badgeId, $ruleId, $achievementKey, $actorId],
            );
        }

        return $removed;
    }
}
