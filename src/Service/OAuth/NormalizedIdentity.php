<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * The provider-agnostic identity each OAuthProvider maps its callback into
 * (USER §2.2). The core account-resolution logic only ever sees this shape —
 * providers absorb their own quirks. Keyed on (provider, provider_user_id),
 * never email, so a provider email change never breaks login.
 */
final class NormalizedIdentity
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $displayName,
        public readonly ?string $avatarUrl,
    ) {
    }
}
