<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * Builds the OAuth provider set from config (the `oauth` block). A provider is
 * present only if known; "configured" is decided per provider by its credentials.
 * New providers slot in here without touching the callback/resolution core.
 */
final class ProviderRegistry
{
    /** @var array<string,OAuthProvider> */
    private array $providers;

    /** @param array<string,array{client_id?:string,client_secret?:string}> $config */
    public function __construct(array $config, ?HttpClient $http = null)
    {
        $http ??= new HttpClient();
        $this->providers = [
            'google' => new GoogleProvider(
                (string) ($config['google']['client_id'] ?? ''),
                (string) ($config['google']['client_secret'] ?? ''),
                $http,
            ),
            'github' => new GitHubProvider(
                (string) ($config['github']['client_id'] ?? ''),
                (string) ($config['github']['client_secret'] ?? ''),
                $http,
            ),
            'apple' => new AppleProvider(
                (string) ($config['apple']['client_id'] ?? ''),
                (string) ($config['apple']['client_secret'] ?? ''),
                $http,
            ),
        ];
    }

    public function get(string $name): ?OAuthProvider
    {
        return $this->providers[$name] ?? null;
    }

    /** @return array<string,OAuthProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return list<string> names of providers that can run a flow right now */
    public function configuredNames(): array
    {
        $out = [];
        foreach ($this->providers as $name => $p) {
            if ($p->isConfigured()) {
                $out[] = $name;
            }
        }
        return $out;
    }
}
