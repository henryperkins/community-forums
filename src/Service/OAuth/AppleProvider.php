<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * Sign in with Apple (OIDC). Apple's client secret is a short-lived ES256 JWT
 * the operator generates out of band and supplies as the client secret; without
 * it the provider is unconfigured. Apple relay / "hide my email" addresses are
 * respected — we never expose a real email obtained via Apple (USER §2.6).
 */
final class AppleProvider implements OAuthProvider
{
    private const AUTH = 'https://appleid.apple.com/auth/authorize';
    private const TOKEN = 'https://appleid.apple.com/auth/token';

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private HttpClient $http = new HttpClient(),
    ) {
    }

    public function name(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'Apple';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string
    {
        return self::AUTH . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): array
    {
        return $this->http->postForm(self::TOKEN, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function identity(array $tokens, ?string $expectedNonce = null): NormalizedIdentity
    {
        $claims = HttpClient::decodeJwtPayload((string) ($tokens['id_token'] ?? ''));
        return new NormalizedIdentity(
            provider: 'apple',
            providerUserId: (string) ($claims['sub'] ?? ''),
            email: isset($claims['email']) ? (string) $claims['email'] : null,
            // Apple asserts email_verified as a string|bool.
            emailVerified: filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            displayName: null, // Apple only sends the name on first consent (via form post).
            avatarUrl: null,
        );
    }
}
