<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Linked third-party logins (USER §2, §7, P2-10). Keyed on
 * (provider, provider_user_id) — never on email — so a provider email change
 * does not break login and an attacker-controlled email cannot take over an
 * account. We store the normalised identity only; provider tokens are not kept.
 */
final class OAuthIdentityRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByProvider(string $provider, string $providerUserId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM oauth_identities WHERE provider = ? AND provider_user_id = ?',
            [$provider, $providerUserId],
        );
    }

    /** @return array<int,array<string,mixed>> identities linked to this account */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM oauth_identities WHERE user_id = ? ORDER BY provider ASC',
            [$userId],
        );
    }

    public function existsForUserProvider(int $userId, string $provider): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM oauth_identities WHERE user_id = ? AND provider = ? LIMIT 1',
            [$userId, $provider],
        ) !== false;
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM oauth_identities WHERE user_id = ?',
            [$userId],
        );
    }

    /**
     * @param array{user_id:int,provider:string,provider_user_id:string,email?:?string,email_verified?:bool,avatar_url?:?string} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO oauth_identities (user_id, provider, provider_user_id, email, email_verified, avatar_url, created_at, last_login_at)
             VALUES (:uid, :provider, :puid, :email, :verified, :avatar, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                'uid' => $data['user_id'],
                'provider' => $data['provider'],
                'puid' => $data['provider_user_id'],
                'email' => $data['email'] ?? null,
                'verified' => !empty($data['email_verified']) ? 1 : 0,
                'avatar' => $data['avatar_url'] ?? null,
            ],
        );
    }

    public function touchLogin(int $id): void
    {
        $this->db->run('UPDATE oauth_identities SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /** @return bool true if a row was deleted */
    public function delete(int $userId, string $provider): bool
    {
        return $this->db->run(
            'DELETE FROM oauth_identities WHERE user_id = ? AND provider = ?',
            [$userId, $provider],
        )->rowCount() > 0;
    }
}
