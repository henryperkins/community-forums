<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * Canonical origin and RP-ID policy for WebAuthn ceremonies.
 */
final class RelyingParty
{
    private readonly string $scheme;
    private readonly string $host;
    private readonly ?int $port;
    private readonly string $rpId;

    public function __construct(string $appUrl, ?string $rpIdOverride, private readonly string $appEnv)
    {
        $parts = parse_url(trim($appUrl));
        $scheme = strtolower((string) (($parts ?: [])['scheme'] ?? ''));
        $host = strtolower((string) (($parts ?: [])['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new WebAuthnException('invalid_app_url', 'APP_URL must be an absolute http(s) URL to use passkeys.');
        }

        $this->scheme = $scheme;
        $this->host = $host;
        $port = ($parts ?: [])['port'] ?? null;
        $isDefaultPort = $port === null
            || ($scheme === 'https' && (int) $port === 443)
            || ($scheme === 'http' && (int) $port === 80);
        $this->port = $isDefaultPort ? null : (int) $port;

        $override = $rpIdOverride !== null ? strtolower(trim($rpIdOverride)) : '';
        if ($override !== '') {
            if ($override !== $host && !str_ends_with($host, '.' . $override)) {
                throw new WebAuthnException('invalid_rp_id', 'WEBAUTHN_RP_ID must equal the APP_URL host or be a parent domain of it.');
            }
            $this->rpId = $override;
            return;
        }

        $this->rpId = $host;
    }

    public function origin(): string
    {
        return $this->scheme . '://' . $this->host . ($this->port !== null ? ':' . $this->port : '');
    }

    public function rpId(): string
    {
        return $this->rpId;
    }

    public function rpIdHash(): string
    {
        return hash('sha256', $this->rpId, true);
    }

    public function assertUsable(): void
    {
        $local = $this->host === 'localhost'
            || str_ends_with($this->host, '.localhost')
            || $this->host === '127.0.0.1'
            || $this->host === '::1';
        if ($this->appEnv === 'production' && $this->scheme !== 'https' && !$local) {
            throw new WebAuthnException('insecure_origin', 'Passkeys require an HTTPS APP_URL in production.');
        }
    }
}
