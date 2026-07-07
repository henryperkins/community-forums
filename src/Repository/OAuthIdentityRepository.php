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

    /** @param list<string>|array<int,string> $usableProviders */
    public function countUsableForUser(int $userId, array $usableProviders): int
    {
        $providers = self::providerList($usableProviders);
        if ($providers === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($providers), '?'));
        return (int) $this->db->fetchValue(
            "SELECT COUNT(DISTINCT provider)
             FROM oauth_identities
             WHERE user_id = ? AND provider IN ({$placeholders})",
            array_merge([$userId], $providers),
        );
    }

    private const SOLE_METHOD_FROM = "FROM users u
             JOIN oauth_identities oi ON oi.user_id = u.id AND oi.provider = ?
             WHERE u.password_hash IS NULL
               AND u.status NOT IN ('deleted', 'banned')
               AND NOT EXISTS (SELECT 1 FROM webauthn_credentials wc WHERE wc.user_id = u.id AND wc.revoked_at IS NULL)";

    /**
     * Accounts whose only sign-in method is the named OAuth provider.
     *
     * @param list<string>|array<int,string>|null $usableProviders currently configured provider keys; null preserves legacy "any linked provider" semantics
     * @return list<array{id:int,username:string,email:string}>
     */
    public function soleMethodAccounts(string $provider, ?array $usableProviders = null): array
    {
        $query = $this->soleMethodQuery($provider, $usableProviders);
        if ($query === null) {
            return [];
        }
        [$extraSql, $params] = $query;

        return $this->db->fetchAll(
            'SELECT u.id, u.username, u.email ' . self::SOLE_METHOD_FROM . " {$extraSql} ORDER BY u.id",
            $params,
        );
    }

    /**
     * Count-only twin of soleMethodAccounts() — identical predicates, no
     * hydration (the console index shows just the number, per provider row).
     *
     * @param list<string>|array<int,string>|null $usableProviders
     */
    public function soleMethodCount(string $provider, ?array $usableProviders = null): int
    {
        $query = $this->soleMethodQuery($provider, $usableProviders);
        if ($query === null) {
            return 0;
        }
        [$extraSql, $params] = $query;

        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) ' . self::SOLE_METHOD_FROM . " {$extraSql}",
            $params,
        );
    }

    /**
     * @param list<string>|array<int,string>|null $usableProviders
     * @return array{0:string,1:list<string>}|null null = the provider itself is not usable, so its removal locks nobody out
     */
    private function soleMethodQuery(string $provider, ?array $usableProviders): ?array
    {
        $extraSql = '';
        $params = [$provider];
        if ($usableProviders === null) {
            $extraSql = 'AND NOT EXISTS (SELECT 1 FROM oauth_identities o2 WHERE o2.user_id = u.id AND o2.provider <> ?)';
            $params[] = $provider;
        } else {
            $usable = self::providerList($usableProviders);
            if (!in_array($provider, $usable, true)) {
                return null;
            }
            $otherUsable = array_values(array_filter($usable, static fn (string $p): bool => $p !== $provider));
            if ($otherUsable !== []) {
                $placeholders = implode(',', array_fill(0, count($otherUsable), '?'));
                $extraSql = "AND NOT EXISTS (SELECT 1 FROM oauth_identities o2 WHERE o2.user_id = u.id AND o2.provider IN ({$placeholders}))";
                array_push($params, ...$otherUsable);
            }
        }
        return [$extraSql, $params];
    }

    /**
     * @param array{user_id:int,provider:string,provider_user_id:string,email?:?string,email_verified?:bool,avatar_url?:?string,provider_config_id?:?int} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO oauth_identities (user_id, provider, provider_config_id, provider_user_id, email, email_verified, avatar_url, created_at, last_login_at)
             VALUES (:uid, :provider, :config_id, :puid, :email, :verified, :avatar, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                'uid' => $data['user_id'],
                'provider' => $data['provider'],
                'config_id' => $data['provider_config_id'] ?? null,
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

    /** @param list<string>|array<int,string> $providers @return list<string> */
    private static function providerList(array $providers): array
    {
        $out = [];
        foreach ($providers as $provider) {
            $provider = trim((string) $provider);
            if ($provider !== '') {
                $out[$provider] = $provider;
            }
        }
        return array_values($out);
    }
}
