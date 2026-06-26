<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * A pluggable OAuth/OIDC provider (USER §2.2). The core handles the shared
 * mechanics — `state`, PKCE, nonce, redirect/callback routing, identity
 * normalisation, and account resolution — so a provider only maps its quirks
 * into a NormalizedIdentity. Tokens are never persisted.
 */
interface OAuthProvider
{
    public function name(): string;

    /** Configured only when it has the credentials needed to run a flow. */
    public function isConfigured(): bool;

    /**
     * The provider's authorization URL to redirect the user to. Includes the
     * anti-CSRF `state`, the PKCE `code_challenge` (S256), and a `nonce`.
     */
    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string;

    /**
     * Exchange the callback `code` for tokens (PKCE verifier proves possession).
     *
     * @return array<string,mixed> raw token set
     */
    public function exchange(string $code, string $codeVerifier, string $redirectUri): array;

    /**
     * Map a token set to the normalised identity.
     *
     * @param array<string,mixed> $tokens
     */
    public function identity(array $tokens): NormalizedIdentity;
}
