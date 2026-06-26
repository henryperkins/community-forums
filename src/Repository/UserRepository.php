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
}
