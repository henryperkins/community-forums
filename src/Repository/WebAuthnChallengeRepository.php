<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class WebAuthnChallengeRepository
{
    public function __construct(private Database $db)
    {
    }

    public function mint(?int $userId, string $sessionHash, string $purpose, string $challenge, int $ttlSeconds): int
    {
        return $this->db->insert(
            'INSERT INTO webauthn_challenges (user_id, session_token_hash, purpose, challenge, created_at, expires_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))',
            [$userId, $sessionHash, $purpose, $challenge, $ttlSeconds],
        );
    }

    public function consume(string $challenge, string $sessionHash, string $purpose, ?int $userId): bool
    {
        return $this->db->run(
            'UPDATE webauthn_challenges
             SET consumed_at = UTC_TIMESTAMP()
             WHERE challenge = ? AND session_token_hash = ? AND purpose = ? AND user_id <=> ?
               AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$challenge, $sessionHash, $purpose, $userId],
        )->rowCount() === 1;
    }

    public function purgeExpired(): int
    {
        return $this->db->run(
            'DELETE FROM webauthn_challenges WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)',
        )->rowCount();
    }
}
