<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Single-table SQL for api_tokens (hash-only, scoped Bearer tokens). */
final class ApiTokenRepository
{
    public function __construct(private Database $db)
    {
    }

    public function insert(string $name, string $hash, string $scopesJson, int $createdBy, ?string $expiresAt): int
    {
        return $this->db->insert(
            'INSERT INTO api_tokens (name, token_hash, scopes, created_by, created_at, expires_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?)',
            [$name, $hash, $scopesJson, $createdBy, $expiresAt],
        );
    }

    public function findActiveByHash(string $hash): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM api_tokens
             WHERE token_hash = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
            [$hash],
        );
    }

    public function touchLastUsed(int $id): void
    {
        $this->db->run('UPDATE api_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /** @return int rows affected — 0 when the id is unknown or already revoked */
    public function revoke(int $id): int
    {
        return $this->db->run(
            'UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL',
            [$id],
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> admin listing; excludes token_hash */
    public function list(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, scopes, created_by, created_at, last_used_at, expires_at, revoked_at
             FROM api_tokens ORDER BY id DESC',
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM api_tokens WHERE id = ?', [$id]);
    }
}
