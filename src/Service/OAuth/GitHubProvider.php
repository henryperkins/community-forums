<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * GitHub Sign-In (OAuth2). The numeric `id` is the stable provider id. Email may
 * be private, so we request `user:email`, fetch the primary, and treat an
 * unverified GitHub email as UNVERIFIED — it must not satisfy collision-merge
 * (USER §2.6).
 */
final class GitHubProvider implements OAuthProvider
{
    private const AUTH = 'https://github.com/login/oauth/authorize';
    private const TOKEN = 'https://github.com/login/oauth/access_token';
    private const API_USER = 'https://api.github.com/user';
    private const API_EMAILS = 'https://api.github.com/user/emails';

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private HttpClient $http = new HttpClient(),
    ) {
    }

    public function name(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string
    {
        // GitHub supports PKCE; state is the primary CSRF guard.
        return self::AUTH . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'read:user user:email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'allow_signup' => 'true',
        ]);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): array
    {
        return $this->http->postForm(self::TOKEN, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function identity(array $tokens, ?string $expectedNonce = null): NormalizedIdentity
    {
        $token = (string) ($tokens['access_token'] ?? '');
        $user = $token !== '' ? $this->http->getJson(self::API_USER, $token) : [];

        // Resolve the primary, verified email (the profile email may be null/public-only).
        $email = null;
        $verified = false;
        if ($token !== '') {
            foreach ($this->http->getJson(self::API_EMAILS, $token) as $row) {
                if (is_array($row) && !empty($row['primary'])) {
                    $email = isset($row['email']) ? (string) $row['email'] : null;
                    $verified = (bool) ($row['verified'] ?? false);
                    break;
                }
            }
        }

        return new NormalizedIdentity(
            provider: 'github',
            providerUserId: (string) ($user['id'] ?? ''),
            email: $email,
            emailVerified: $verified,
            displayName: isset($user['name']) && $user['name'] !== null ? (string) $user['name'] : (isset($user['login']) ? (string) $user['login'] : null),
            avatarUrl: isset($user['avatar_url']) ? (string) $user['avatar_url'] : null,
        );
    }
}
