<?php

declare(strict_types=1);

namespace App\Service\OAuth\Oidc;

use App\Core\OidcVerificationException;
use App\Service\OAuth\NormalizedIdentity;

/**
 * Verified OIDC claims → NormalizedIdentity (P5-12). The subject claim is
 * fixed to `sub` — the identity key is never operator-remappable — and
 * email_verified is strict-boolean `true` only, so a sloppy provider (or a
 * hostile claim map) can never manufacture the verified-email signal that
 * feeds the collision rule (TM-ID-04). `claim_map_json` renames only the
 * cosmetic claims: email, email_verified, name, username, picture.
 */
final class ClaimMapper
{
    private const DEFAULT_MAP = [
        'email' => 'email',
        'email_verified' => 'email_verified',
        'name' => 'name',
        'username' => 'preferred_username',
        'picture' => 'picture',
    ];

    /**
     * @param array<string,mixed> $claims verified id_token claims
     * @param array<string,mixed> $row identity_providers row
     */
    public function map(array $claims, array $row): NormalizedIdentity
    {
        $sub = $claims['sub'] ?? null;
        $sub = is_string($sub) || is_int($sub) ? (string) $sub : '';
        if ($sub === '') {
            throw new OidcVerificationException('subject_missing');
        }

        $map = $this->claimMap($row);
        $email = $this->stringClaim($claims, $map['email']);
        // Verification cannot exist without an address to be verified.
        $emailVerified = $email !== null && ($claims[$map['email_verified']] ?? null) === true;

        $avatar = $this->stringClaim($claims, $map['picture']);
        if ($avatar !== null && !str_starts_with($avatar, 'https://')) {
            $avatar = null;
        }

        return new NormalizedIdentity(
            provider: (string) $row['provider_key'],
            providerUserId: $sub,
            email: $email,
            emailVerified: $emailVerified,
            displayName: $this->stringClaim($claims, $map['name']) ?? $this->stringClaim($claims, $map['username']),
            avatarUrl: $avatar,
            providerConfigId: isset($row['id']) ? (int) $row['id'] : null,
        );
    }

    /** @param array<string,mixed> $row @return array<string,string> */
    private function claimMap(array $row): array
    {
        $overrides = json_decode((string) ($row['claim_map_json'] ?? ''), true);
        if (!is_array($overrides)) {
            return self::DEFAULT_MAP;
        }
        $map = self::DEFAULT_MAP;
        foreach ($map as $field => $claim) {
            if (isset($overrides[$field]) && is_string($overrides[$field]) && $overrides[$field] !== '') {
                $map[$field] = $overrides[$field];
            }
        }
        return $map;
    }

    /** @param array<string,mixed> $claims */
    private function stringClaim(array $claims, string $name): ?string
    {
        $value = $claims[$name] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}
