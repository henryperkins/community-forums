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

    public function usernameExists(string $username): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM users WHERE username = ? LIMIT 1', [$username]) !== false;
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

    /** Out-of-band account-state setter (seed/fixtures/CLI) — no in-app UI in Phase 1. */
    public function setStatus(int $id, string $status, ?string $suspendedUntil = null): void
    {
        $this->db->run(
            'UPDATE users SET status = ?, suspended_until = ? WHERE id = ?',
            [$status, $suspendedUntil, $id],
        );
    }
}
