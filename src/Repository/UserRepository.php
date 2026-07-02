<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Domain\User;

final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findEntity(int $id): ?User
    {
        $row = $this->find($id);
        return $row === null ? null : User::fromRow($row);
    }

    /** @return array<string,mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE username = ?', [$username]);
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
    }

    /**
     * Resolve a list of @handles (case-insensitive) to active accounts.
     *
     * @param list<string> $usernames
     * @return array<int,array{id:int,username:string,email:string,status:string}>
     */
    public function findByUsernames(array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter($usernames, static fn ($u): bool => is_string($u) && $u !== '')));
        if ($usernames === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($usernames), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, username, email, status FROM users WHERE username IN ($place)",
            $usernames,
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'username' => (string) $r['username'],
            'email' => (string) $r['email'],
            'status' => (string) $r['status'],
        ], $rows);
    }

    /**
     * @param list<string> $usernames
     * @return array<string,array{id:int,username:string}> lower(username) => row
     */
    public function activeMentionTargets(array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter($usernames, static fn ($u): bool => is_string($u) && $u !== '')));
        if ($usernames === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($usernames), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, username FROM users WHERE LOWER(username) IN ($place) AND status = 'active'",
            array_map('strtolower', $usernames),
        );
        $out = [];
        foreach ($rows as $row) {
            $out[strtolower((string) $row['username'])] = ['id' => (int) $row['id'], 'username' => (string) $row['username']];
        }
        return $out;
    }

    /** @return array<int,array{id:int,username:string,display_name:?string,role:string,status:string}> */
    public function suggestByPrefix(string $query, int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT id, username, display_name, role, status
             FROM users
             WHERE status = 'active' AND (username LIKE ? OR display_name LIKE ?)
             ORDER BY username ASC
             LIMIT " . max(1, min(25, $limit)),
            [$query . '%', $query . '%'],
        );
    }

    /**
     * @param list<int> $ids
     * @return array<int,array{email:string,status:string}> id => contact
     */
    public function contactsForIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll("SELECT id, email, status FROM users WHERE id IN ($place)", $ids);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = ['email' => (string) $r['email'], 'status' => (string) $r['status']];
        }
        return $out;
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM users WHERE username = ? LIMIT 1', [$username]) !== false;
    }

    /** Private-board membership check (board_members), used by access re-checks. */
    public function isBoardMember(int $boardId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM board_members WHERE board_id = ? AND user_id = ? LIMIT 1',
            [$boardId, $userId],
        ) !== false;
    }

    public function emailExists(string $email): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM users WHERE email = ? LIMIT 1', [$email]) !== false;
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM users');
    }

    public function adminCount(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    }

    public function activeAdminCountExcluding(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?",
            [$userId],
        );
    }

    public function activeAdminCountExcludingForUpdate(int $userId): int
    {
        $rows = $this->db->fetchAll(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC FOR UPDATE",
        );
        $count = 0;
        foreach ($rows as $row) {
            if ((int) $row['id'] !== $userId) {
                $count++;
            }
        }
        return $count;
    }

    /** @return list<int> ids of all admins (for staff notifications) */
    public function adminIds(): array
    {
        $rows = $this->db->fetchAll("SELECT id FROM users WHERE role = 'admin'");
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /**
     * @param array{username:string,email:string,password_hash:?string,display_name:?string,role?:string,status?:string} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO users (username, email, password_hash, display_name, role, status, created_at)
             VALUES (:username, :email, :password_hash, :display_name, :role, :status, UTC_TIMESTAMP())',
            [
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password_hash'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'role' => $data['role'] ?? 'user',
                'status' => $data['status'] ?? 'active',
            ],
        );
    }

    public function updateProfile(int $id, ?string $displayName, ?string $bio, ?string $location): void
    {
        $this->db->run(
            'UPDATE users SET display_name = :display_name, bio = :bio, location = :location WHERE id = :id',
            ['display_name' => $displayName, 'bio' => $bio, 'location' => $location, 'id' => $id],
        );
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function incrementPostCount(int $id, int $delta = 1): void
    {
        // GREATEST guard keeps the unsigned counter from underflowing on delete.
        $this->db->run(
            'UPDATE users SET post_count = GREATEST(0, CAST(post_count AS SIGNED) + ?) WHERE id = ?',
            [$delta, $id],
        );
    }

    /**
     * Adjust the denormalised reputation counter (Σ reactions received +
     * solved bonus). Clamped at 0 — reputation never goes negative
     * (COMMUNITY §2.1: "no negative reputation").
     */
    public function incrementReputation(int $id, int $delta): void
    {
        $this->db->run(
            'UPDATE users SET reputation = GREATEST(0, reputation + ?) WHERE id = ?',
            [$delta, $id],
        );
    }

    /** Out-of-band account-state setter (seed/fixtures/CLI) — no in-app UI in Phase 1. */
    public function setStatus(int $id, string $status, ?string $suspendedUntil = null): void
    {
        $this->db->run(
            'UPDATE users SET status = ?, suspended_until = ? WHERE id = ?',
            [$status, $suspendedUntil, $id],
        );
    }

    public function anonymizeDeletedAccount(int $id): void
    {
        $this->db->run(
            "UPDATE users
             SET username = :username,
                 email = :email,
                 password_hash = NULL,
                 display_name = 'Deleted user',
                 role = 'user',
                 title = NULL,
                 signature = NULL,
                 location = NULL,
                 bio = NULL,
                 website = NULL,
                 pronouns = NULL,
                 avatar_path = NULL,
                 avatar_source = 'monogram',
                 profile_visibility = 'members',
                 allow_dms = 'none',
                 show_presence = 0,
                 status = 'deleted',
                 suspended_until = NULL,
                 email_verified_at = NULL,
                 onboarded_at = NULL,
                 timezone = NULL,
                 digest_hour = NULL,
                 last_daily_digest_at = NULL,
                 last_seen_at = NULL,
                 signature_removed_at = NULL,
                 signature_removed_by = NULL,
                 avatar_removed_at = NULL,
                 avatar_removed_by = NULL
             WHERE id = :id",
            [
                'username' => 'deleted-user-' . $id,
                'email' => 'deleted-user-' . $id . '@deleted.invalid',
                'id' => $id,
            ],
        );
    }

    // ---- Phase 2 / M5 (community identity + account expansion) ------------

    /** Extended self-serve profile fields (USER §5). NULLs clear the field. */
    public function updateProfileFull(
        int $id,
        ?string $displayName,
        ?string $bio,
        ?string $location,
        ?string $website,
        ?string $pronouns,
        ?string $signature,
    ): void {
        $this->db->run(
            'UPDATE users SET display_name = :dn, bio = :bio, location = :loc,
                              website = :web, pronouns = :pro, signature = :sig
             WHERE id = :id',
            [
                'dn' => $displayName, 'bio' => $bio, 'loc' => $location,
                'web' => $website, 'pro' => $pronouns, 'sig' => $signature, 'id' => $id,
            ],
        );
    }

    /** Privacy controls (USER §4.7): profile visibility, DM policy, presence flag. */
    public function updatePrivacy(int $id, string $profileVisibility, string $allowDms, bool $showPresence): void
    {
        $this->db->run(
            'UPDATE users SET profile_visibility = :pv, allow_dms = :dm, show_presence = :sp WHERE id = :id',
            ['pv' => $profileVisibility, 'dm' => $allowDms, 'sp' => $showPresence ? 1 : 0, 'id' => $id],
        );
    }

    /** Timezone + digest hour for the timezone-aware daily digest (USER §4.6). */
    public function updateDigest(int $id, ?string $timezone, ?int $digestHour): void
    {
        $this->db->run(
            'UPDATE users SET timezone = :tz, digest_hour = :dh WHERE id = :id',
            ['tz' => $timezone, 'dh' => $digestHour, 'id' => $id],
        );
    }

    /** Cosmetic title override (COMMUNITY §8) — admin-set; NULL reverts to the derived title. */
    public function setTitle(int $id, ?string $title): void
    {
        $this->db->run('UPDATE users SET title = ? WHERE id = ?', [$title, $id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    /** Mark the account's email verified (idempotent); used by OAuth verified-email link/signup. */
    public function markEmailVerified(int $id): void
    {
        $this->db->run(
            'UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND email_verified_at IS NULL',
            [$id],
        );
    }

    /** Set the OAuth-imported avatar source (USER §5.2); monogram remains the fallback when absent. */
    public function setAvatarSource(int $id, string $source): void
    {
        $this->db->run('UPDATE users SET avatar_source = ? WHERE id = ?', [$source, $id]);
    }

    public function setAvatar(int $id, ?string $path, string $source, ?int $removedBy = null): void
    {
        if ($removedBy !== null) {
            $this->db->run(
                'UPDATE users
                 SET avatar_path = ?, avatar_source = ?, avatar_removed_at = UTC_TIMESTAMP(), avatar_removed_by = ?
                 WHERE id = ?',
                [$path, $source, $removedBy, $id],
            );
            return;
        }

        $this->db->run(
            'UPDATE users
             SET avatar_path = ?, avatar_source = ?, avatar_removed_at = NULL, avatar_removed_by = NULL
             WHERE id = ?',
            [$path, $source, $id],
        );
    }

    public function clearSignature(int $id, int $removedBy): void
    {
        $this->db->run(
            'UPDATE users
             SET signature = NULL, signature_removed_at = UTC_TIMESTAMP(), signature_removed_by = ?
             WHERE id = ?',
            [$removedBy, $id],
        );
    }

    /** Presence heartbeat (P2-11) — bumped at most once per heartbeat window by the caller. */
    public function updateLastSeen(int $id): void
    {
        $this->db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /**
     * Mark the product tour complete (cross-device) or clear it for a replay
     * (P3-11). Completion persists server-side so it carries across devices.
     */
    public function setOnboarded(int $id, bool $done): void
    {
        $this->db->run(
            'UPDATE users SET onboarded_at = ' . ($done ? 'UTC_TIMESTAMP()' : 'NULL') . ' WHERE id = ?',
            [$id],
        );
    }

    /**
     * Members visible in the presence roster: opted-in (show_presence = 1) and
     * seen since $since (UTC 'Y-m-d H:i:s'). Block filtering is applied by the caller.
     *
     * @return array<int,array{id:int,username:string,display_name:?string,last_seen_at:string}>
     */
    public function onlineSince(string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, username, display_name, last_seen_at
             FROM users
             WHERE show_presence = 1 AND status <> 'banned'
               AND last_seen_at IS NOT NULL AND last_seen_at >= ?
             ORDER BY last_seen_at DESC",
            [$since],
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'username' => (string) $r['username'],
            'display_name' => $r['display_name'] !== null ? (string) $r['display_name'] : null,
            'last_seen_at' => (string) $r['last_seen_at'],
        ], $rows);
    }

    /**
     * Number of accepted/"solved" answers authored by $userId (Trusted Answerer +
     * profile). Excludes self-answers (answer author == thread OP) to mirror the
     * reputation bonus rule (SolvedAnswerService, RepairService::reputationSolvedBonus)
     * so the badge thresholds can't be self-farmed.
     */
    public function solvedAnswerCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM threads t JOIN posts p ON p.id = t.accepted_answer_post_id
             WHERE p.user_id = ? AND p.user_id <> t.user_id AND t.is_deleted = 0 AND p.is_deleted = 0',
            [$userId],
        );
    }

    /**
     * All-time Top Contributors (COMMUNITY §7): ranked by reputation, excluding
     * banned accounts and anyone who opted out via user_preferences.hide_from_leaderboard.
     *
     * @return array<int,array<string,mixed>>
     */
    public function leaderboard(int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.display_name, u.title, u.reputation, u.post_count
             FROM users u
             LEFT JOIN user_preferences pf ON pf.user_id = u.id
             WHERE u.status <> 'banned' AND u.reputation > 0
               AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pf.prefs, '$.hide_from_leaderboard')), 'false') <> 'true'
             ORDER BY u.reputation DESC, u.id DESC
             LIMIT " . $limit,
        );
    }

    /** Sortable directory columns → SQL column (ORDER BY is not bindable, so
     *  the sort key is resolved through this allowlist, never interpolated). */
    private const DIRECTORY_SORTS = [
        'username' => 'username',
        'role' => 'role',
        'status' => 'status',
        'created_at' => 'created_at',
        'last_seen' => 'last_seen_at',
        'post_count' => 'post_count',
        'reputation' => 'reputation',
    ];

    /**
     * Admin user directory (ADMIN §5.1) with filtering + sorting. Accepts a
     * typed filter array: `q`, `role`, `status`, `joined_from`, `joined_to`,
     * `last_seen`, `min_posts`, `max_posts`, `sort`, `direction`, `limit`,
     * `offset`. LIMIT/OFFSET and interval days are clamped + inlined
     * (EMULATE_PREPARES=false forbids binding them and ORDER BY columns); every
     * repeated value gets a distinct named placeholder.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function directory(array $filters = []): array
    {
        [$where, $params] = $this->directoryFilters($filters);

        $sortKey = (string) ($filters['sort'] ?? 'created_at');
        $column = self::DIRECTORY_SORTS[$sortKey] ?? 'created_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT id, username, display_name, email, role, status, reputation, post_count, created_at, last_seen_at
                FROM users';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        // Tie-break on id in the same direction for a stable, deterministic page.
        $sql .= ' ORDER BY ' . $column . ' ' . $direction . ', id ' . $direction
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Build the shared WHERE fragments + bound params for the directory.
     *
     * @param array<string,mixed> $filters
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private function directoryFilters(array $filters): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(username LIKE :q1 OR display_name LIKE :q2 OR email LIKE :q3)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $role = (string) ($filters['role'] ?? '');
        if (in_array($role, ['user', 'moderator', 'admin'], true)) {
            $where[] = 'role = :role';
            $params['role'] = $role;
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['active', 'suspended', 'banned', 'deactivated'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $joinedFrom = trim((string) ($filters['joined_from'] ?? ''));
        if ($joinedFrom !== '') {
            $where[] = 'created_at >= :joined_from';
            $params['joined_from'] = $joinedFrom;
        }
        $joinedTo = trim((string) ($filters['joined_to'] ?? ''));
        if ($joinedTo !== '') {
            $where[] = 'created_at <= :joined_to';
            $params['joined_to'] = $joinedTo;
        }

        $lastSeen = (string) ($filters['last_seen'] ?? '');
        if ($lastSeen === 'never') {
            $where[] = 'last_seen_at IS NULL';
        } elseif (ctype_digit($lastSeen) && (int) $lastSeen > 0) {
            $days = min(3650, (int) $lastSeen); // clamped + inlined; INTERVAL is not bindable
            $where[] = 'last_seen_at >= UTC_TIMESTAMP() - INTERVAL ' . $days . ' DAY';
        }

        $minPosts = $filters['min_posts'] ?? '';
        if ($minPosts !== '' && $minPosts !== null) {
            $where[] = 'post_count >= :min_posts';
            $params['min_posts'] = max(0, (int) $minPosts);
        }
        $maxPosts = $filters['max_posts'] ?? '';
        if ($maxPosts !== '' && $maxPosts !== null) {
            $where[] = 'post_count <= :max_posts';
            $params['max_posts'] = max(0, (int) $maxPosts);
        }

        return [$where, $params];
    }
}
