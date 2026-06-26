<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * Google Sign-In (OIDC). The cleanest case: `sub` is the stable provider id and
 * the id_token asserts `email_verified` (USER §2.6). PKCE + state + nonce on
 * every flow.
 */
final class GoogleProvider implements OAuthProvider
{
    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private HttpClient $http = new HttpClient(),
    ) {
    }

    public function name(): string
    {
        return 'google';
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
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
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

    public function identity(array $tokens): NormalizedIdentity
    {
        $claims = HttpClient::decodeJwtPayload((string) ($tokens['id_token'] ?? ''));
        return new NormalizedIdentity(
            provider: 'google',
            providerUserId: (string) ($claims['sub'] ?? ''),
            email: isset($claims['email']) ? (string) $claims['email'] : null,
            emailVerified: (bool) ($claims['email_verified'] ?? false),
            displayName: isset($claims['name']) ? (string) $claims['name'] : null,
            avatarUrl: isset($claims['picture']) ? (string) $claims['picture'] : null,
        );
    }
}
