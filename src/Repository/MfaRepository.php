<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class MfaRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function totpForUser(int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM user_totp_credentials WHERE user_id = ?', [$userId]);
    }

    public function enabledForUser(int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM user_totp_credentials
              WHERE user_id = ? AND enabled_at IS NOT NULL AND disabled_at IS NULL
              LIMIT 1',
            [$userId],
        ) !== false;
    }

    /** @param array{ciphertext:string,nonce:string,tag:string} $secret */
    public function savePendingTotp(int $userId, array $secret): void
    {
        $this->db->run(
            'INSERT INTO user_totp_credentials
                (user_id, secret_ciphertext, secret_nonce, secret_tag, enabled_at, verified_at, disabled_at, last_used_step, created_at, updated_at)
             VALUES
                (:user_id, :ciphertext, :nonce, :tag, NULL, NULL, NULL, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                secret_ciphertext = VALUES(secret_ciphertext),
                secret_nonce = VALUES(secret_nonce),
                secret_tag = VALUES(secret_tag),
                enabled_at = NULL,
                verified_at = NULL,
                disabled_at = NULL,
                last_used_step = NULL,
                updated_at = UTC_TIMESTAMP()',
            [
                'user_id' => $userId,
                'ciphertext' => $secret['ciphertext'],
                'nonce' => $secret['nonce'],
                'tag' => $secret['tag'],
            ],
        );
    }

    public function enableTotp(int $userId, int $usedStep): void
    {
        $this->db->run(
            'UPDATE user_totp_credentials
                SET enabled_at = UTC_TIMESTAMP(),
                    verified_at = UTC_TIMESTAMP(),
                    disabled_at = NULL,
                    last_used_step = ?
              WHERE user_id = ?',
            [$usedStep, $userId],
        );
    }

    public function markTotpUsed(int $userId, int $usedStep): void
    {
        $this->db->run(
            'UPDATE user_totp_credentials
                SET last_used_step = GREATEST(COALESCE(last_used_step, 0), ?)
              WHERE user_id = ?',
            [$usedStep, $userId],
        );
    }

    public function disableTotp(int $userId): void
    {
        $this->db->run(
            'UPDATE user_totp_credentials
                SET disabled_at = UTC_TIMESTAMP(), enabled_at = NULL, verified_at = NULL, last_used_step = NULL
              WHERE user_id = ?',
            [$userId],
        );
        $this->db->run('DELETE FROM user_recovery_codes WHERE user_id = ?', [$userId]);
    }

    /** @param list<string> $hashes */
    public function replaceRecoveryCodes(int $userId, array $hashes): void
    {
        $batch = bin2hex(random_bytes(16));
        $this->db->transaction(function () use ($userId, $hashes, $batch): void {
            $this->db->run('DELETE FROM user_recovery_codes WHERE user_id = ?', [$userId]);
            foreach ($hashes as $hash) {
                $this->db->run(
                    'INSERT INTO user_recovery_codes (user_id, batch_id, code_hash, created_at)
                     VALUES (?, ?, ?, UTC_TIMESTAMP())',
                    [$userId, $batch, $hash],
                );
            }
        });
    }

    public function unusedRecoveryCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [$userId],
        );
    }

    public function consumeRecoveryCodeHash(int $userId, string $hash): bool
    {
        return $this->db->run(
            'UPDATE user_recovery_codes
                SET used_at = UTC_TIMESTAMP()
              WHERE user_id = ? AND code_hash = ? AND used_at IS NULL
              LIMIT 1',
            [$userId, $hash],
        )->rowCount() === 1;
    }

    public function createLoginChallenge(int $userId, string $nextPath, ?string $ip, ?string $userAgent): string
    {
        $token = bin2hex(random_bytes(32));
        $packedIp = is_string($ip) && $ip !== '' ? (@inet_pton($ip) ?: null) : null;
        $this->db->run(
            'INSERT INTO mfa_login_challenges
                (user_id, token_hash, next_path, ip, user_agent, created_at, expires_at)
             VALUES
                (?, ?, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))',
            [$userId, hash('sha256', $token), $nextPath, $packedIp, $userAgent],
        );
        return $token;
    }

    /** @return array<string,mixed>|null */
    public function findValidLoginChallenge(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }
        return $this->db->fetch(
            'SELECT * FROM mfa_login_challenges
              WHERE token_hash = ?
                AND consumed_at IS NULL
                AND expires_at > UTC_TIMESTAMP()
              LIMIT 1',
            [hash('sha256', $token)],
        );
    }

    public function consumeLoginChallenge(int $id): bool
    {
        return $this->db->run(
            'UPDATE mfa_login_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = ? AND consumed_at IS NULL',
            [$id],
        )->rowCount() === 1;
    }
}
