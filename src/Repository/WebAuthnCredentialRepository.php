<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class WebAuthnCredentialRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function activeForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM webauthn_credentials WHERE user_id = ? AND revoked_at IS NULL ORDER BY id',
            [$userId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function activeForUserForUpdate(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM webauthn_credentials WHERE user_id = ? AND revoked_at IS NULL ORDER BY id FOR UPDATE',
            [$userId],
        );
    }

    public function countActiveForUser(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ? AND revoked_at IS NULL',
            [$userId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findActiveByCredentialId(string $rawId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM webauthn_credentials WHERE credential_id = ? AND revoked_at IS NULL LIMIT 1',
            [$rawId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $userId, int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM webauthn_credentials WHERE id = ? AND user_id = ?',
            [$id, $userId],
        );
    }

    /** @param array<string,mixed> $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO webauthn_credentials
                (user_id, credential_id, public_key, sign_count, aaguid, transports,
                 is_discoverable, is_backup_eligible, is_backed_up, nickname, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                (int) $row['user_id'],
                (string) $row['credential_id'],
                (string) $row['public_key'],
                (int) $row['sign_count'],
                $row['aaguid'],
                (string) $row['transports'],
                (int) $row['is_discoverable'],
                (int) $row['is_backup_eligible'],
                (int) $row['is_backed_up'],
                $row['nickname'],
            ],
        );
    }

    public function rename(int $userId, int $id, string $nickname): bool
    {
        return $this->db->run(
            'UPDATE webauthn_credentials SET nickname = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$nickname, $id, $userId],
        )->rowCount() === 1;
    }

    public function revoke(int $userId, int $id): bool
    {
        return $this->db->run(
            'UPDATE webauthn_credentials SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$id, $userId],
        )->rowCount() === 1;
    }

    public function updateOnUse(int $id, int $signCount): void
    {
        $this->db->run(
            'UPDATE webauthn_credentials SET sign_count = GREATEST(sign_count, ?), last_used_at = UTC_TIMESTAMP() WHERE id = ?',
            [$signCount, $id],
        );
    }
}
