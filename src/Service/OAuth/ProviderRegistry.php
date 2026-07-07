<?php

declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * Builds the OAuth provider set from config (the `oauth` block) plus, when the
 * `provider_registry` flag is on, registry-backed generic-OIDC providers via a
 * lazy loader (P5-12). The loader fails dark — a missing table or DB error
 * yields an empty dynamic set, never a broken shell — and can never shadow a
 * builtin key. New providers slot in without touching the callback core.
 */
final class ProviderRegistry
{
    /** @var array<string,OAuthProvider> */
    private array $providers;

    /** @var array<string,OAuthProvider>|null */
    private ?array $dynamic = null;

    /** @var (callable():iterable<OAuthProvider>)|null */
    private $dynamicLoader;

    /**
     * @param array<string,array{client_id?:string,client_secret?:string}> $config
     * @param (callable():iterable<OAuthProvider>)|null $dynamicLoader
     */
    public function __construct(array $config, ?HttpClient $http = null, ?callable $dynamicLoader = null)
    {
        $http ??= new HttpClient();
        $this->dynamicLoader = $dynamicLoader;
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
        return $this->providers[$name] ?? $this->dynamic()[$name] ?? null;
    }

    /** @return array<string,OAuthProvider> */
    public function all(): array
    {
        return $this->providers + $this->dynamic();
    }

    /** @return list<string> names of providers that can run a flow right now */
    public function configuredNames(): array
    {
        $out = [];
        foreach ($this->all() as $name => $p) {
            if ($p->isConfigured()) {
                $out[] = $name;
            }
        }
        return $out;
    }

    /** @return array<string,OAuthProvider> registry-backed providers, loaded once, fail-dark */
    private function dynamic(): array
    {
        if ($this->dynamic !== null) {
            return $this->dynamic;
        }
        if ($this->dynamicLoader === null) {
            return $this->dynamic = [];
        }
        $out = [];
        try {
            foreach (($this->dynamicLoader)() as $provider) {
                $name = $provider->name();
                // Builtin keys are reserved; a registry row can never shadow one.
                if (!isset($this->providers[$name]) && !isset($out[$name])) {
                    $out[$name] = $provider;
                }
            }
        } catch (\Throwable) {
            $out = [];
        }
        return $this->dynamic = $out;
    }
}
