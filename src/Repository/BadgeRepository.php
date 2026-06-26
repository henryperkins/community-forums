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
}
