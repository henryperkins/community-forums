<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Invitation + redemption rows (P5-13, tables from 0053). Tokens are stored
 * hash-only (sha256 of the raw token); no method here ever sees or returns a
 * raw token. `consumeUse` is the single concurrency gate for redemption.
 */
final class InvitationRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{token_hash:string,created_by:?int,email:?string,domain:?string,onboarding_board_id:?int,max_uses:int,expires_at:string} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO invitations (token_hash, created_by, email, domain, onboarding_board_id, max_uses, expires_at)
             VALUES (:token_hash, :created_by, :email, :domain, :board_id, :max_uses, :expires_at)',
            [
                'token_hash' => $data['token_hash'],
                'created_by' => $data['created_by'],
                'email' => $data['email'],
                'domain' => $data['domain'],
                'board_id' => $data['onboarding_board_id'],
                'max_uses' => $data['max_uses'],
                'expires_at' => $data['expires_at'],
            ],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM invitations WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByTokenHash(string $hash): ?array
    {
        return $this->db->fetch('SELECT * FROM invitations WHERE token_hash = ?', [$hash]);
    }

    /**
     * Newest first, with the creator handle for the console. Clamped to the
     * newest 200 rows: invitations are never purged (revoked/expired rows are
     * retained by design), so an uncapped scan would grow for the life of the
     * install; the console is an issuance surface, not an archive browser.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT i.*, u.username AS creator_username
               FROM invitations i
          LEFT JOIN users u ON u.id = i.created_by
           ORDER BY i.id DESC
              LIMIT 200',
        );
    }

    /** Mark revoked; the WHERE keeps it idempotent. @return int affected rows (1 = transition) */
    public function revoke(int $id, int $revokedBy): int
    {
        return $this->db->run(
            'UPDATE invitations SET revoked_at = UTC_TIMESTAMP(), revoked_by = ? WHERE id = ? AND revoked_at IS NULL',
            [$revokedBy, $id],
        )->rowCount();
    }

    /**
     * The atomic use-consumption gate (TM-IN-02): the WHERE re-checks every
     * validity condition, so concurrent redeemers of the same row serialize
     * on the InnoDB row lock and at most `max_uses` of them ever win.
     *
     * @return int affected rows (1 = this caller won a use, 0 = lost/invalid)
     */
    public function consumeUse(int $id): int
    {
        return $this->db->run(
            'UPDATE invitations SET used_count = used_count + 1
              WHERE id = ? AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                AND used_count < max_uses',
            [$id],
        )->rowCount();
    }

    public function recordRedemption(int $invitationId, ?int $userId, ?string $ip): int
    {
        $packed = $ip !== null && $ip !== '' ? (inet_pton($ip) ?: null) : null;
        return $this->db->insert(
            'INSERT INTO invitation_redemptions (invitation_id, user_id, ip, redeemed_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$invitationId, $userId, $packed],
        );
    }

    public function redemptionCount(int $invitationId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM invitation_redemptions WHERE invitation_id = ?',
            [$invitationId],
        );
    }
}
