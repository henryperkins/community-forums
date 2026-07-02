<?php

declare(strict_types=1);

namespace Tests\Support\Phase5;

/**
 * Foundation F6 - Ed25519 test-key tooling minting the signed artifacts the
 * supply-chain evidence corpus needs: catalogue snapshots, releases,
 * advisories, key-rotation transitions, plus tampered/expired variants.
 * TEST/DEV ONLY (autoload-dev): production trust roots are operator-held
 * out-of-band and never generated here. The secret key is private to this
 * object: no accessor, never persisted.
 *
 * Contract for Inc 2 (P5-01 SP2): detached Ed25519 signature over the exact
 * JSON bytes of an rb-*.v1 document; digests are sha256 hex.
 */
final class SigningHarness
{
    private function __construct(
        private string $keyId,
        private string $publicKeyBytes,
        private string $secretKeyBytes,
    ) {
    }

    public static function generate(string $keyId = 'test-root-1'): self
    {
        $pair = sodium_crypto_sign_keypair();

        return new self(
            $keyId,
            sodium_crypto_sign_publickey($pair),
            sodium_crypto_sign_secretkey($pair),
        );
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    /** Raw 32-byte Ed25519 public key: the only key material that may reach a DB row. */
    public function publicKey(): string
    {
        return $this->publicKeyBytes;
    }

    public function sign(string $bytes): string
    {
        return sodium_crypto_sign_detached($bytes, $this->secretKeyBytes);
    }

    public static function verify(string $bytes, string $signature, string $publicKey): bool
    {
        try {
            return sodium_crypto_sign_verify_detached($signature, $bytes, $publicKey);
        } catch (\SodiumException) {
            return false;
        }
    }

    /** Flip one bit of the last byte: length-preserving, guaranteed different. */
    public static function tamper(string $bytes): string
    {
        $i = strlen($bytes) - 1;
        $bytes[$i] = chr(ord($bytes[$i]) ^ 0x01);

        return $bytes;
    }

    /** @param array<string,mixed> $overrides @return array{json:string,signature:string,key_id:string} */
    public function mintSnapshot(array $overrides = []): array
    {
        $release = $this->mintRelease();
        $doc = array_replace([
            'format' => 'rb-registry-snapshot.v1',
            'registry' => 'rb-test',
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
            'packages' => [[
                'uid' => 'acme/midnight-theme',
                'type' => 'theme',
                'releases' => [[
                    'version' => $release['version'],
                    'digest' => $release['digest'],
                    'core_min' => $release['manifest']['core']['min'] ?? null,
                    'core_max' => $release['manifest']['core']['max'] ?? null,
                    'channel' => 'stable',
                    'advisory' => 'none',
                ]],
            ]],
        ], $overrides);

        return $this->signedDoc($doc);
    }

    /** @return array{json:string,signature:string,key_id:string} */
    public function mintExpiredSnapshot(): array
    {
        return $this->mintSnapshot([
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 172800),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400),
        ]);
    }

    /**
     * A valid rb-manifest.v2 array (P5-02). Top-level overrides replace; `core`
     * and `permissions` merge one level deep with permission kind lists replaced
     * wholesale.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public function mintManifest(array $overrides = []): array
    {
        $base = [
            'format' => 'rb-manifest.v2',
            'uid' => 'acme/midnight-theme',
            'type' => 'theme',
            'version' => '1.0.0',
            'name' => 'Midnight Theme',
            'description' => 'A dark declarative theme for RetroBoards.',
            'license' => 'MIT',
            'core' => ['min' => '0.1.0', 'max' => null],
            'permissions' => [
                'capabilities' => [],
                'data_classes' => ['package.own_storage'],
                'api_scopes' => [],
                'events' => [],
                'outbound_hosts' => [],
                'jobs' => [],
            ],
            'storage_quota_kb' => 64,
            'support' => ['homepage' => 'https://acme.example/midnight'],
        ];

        foreach ($overrides as $key => $value) {
            if (in_array($key, ['core', 'permissions'], true) && is_array($value)) {
                $base[$key] = array_replace($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Mint a signed rb-release.v1 document: digest = sha256 over the exact JSON
     * string; review approval lives inside the signed bytes.
     *
     * @param array<string,mixed> $overrides uid | version | review_status | manifest
     * @return array{json:string,signature:string,key_id:string,digest:string,manifest:array<string,mixed>,manifest_json:string,version:string,uid:string}
     */
    public function mintRelease(array $overrides = []): array
    {
        $uid = (string) ($overrides['uid'] ?? 'acme/midnight-theme');
        $version = (string) ($overrides['version'] ?? '1.0.0');
        $manifest = $this->mintManifest(
            ((array) ($overrides['manifest'] ?? [])) + ['uid' => $uid, 'version' => $version],
        );
        $signed = $this->signedDoc([
            'format' => 'rb-release.v1',
            'uid' => $uid,
            'version' => $version,
            'review' => [
                'status' => (string) ($overrides['review_status'] ?? 'approved'),
                'decided_at' => '2026-07-01T00:00:00Z',
            ],
            'manifest' => $manifest,
        ]);

        return $signed + [
            'digest' => hash('sha256', $signed['json']),
            'manifest' => $manifest,
            'manifest_json' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'version' => $version,
            'uid' => $uid,
        ];
    }

    /**
     * The rb-release-envelope.v1 body the release fetch endpoint serves.
     *
     * @param array<string,mixed> $overrides passed through to mintRelease()
     * @return array{body:string,release:array<string,mixed>}
     */
    public function mintReleaseEnvelope(array $overrides = []): array
    {
        $release = $this->mintRelease($overrides);

        return [
            'body' => json_encode([
                'format' => 'rb-release-envelope.v1',
                'document' => $release['json'],
                'signature' => base64_encode($release['signature']),
                'key_id' => $release['key_id'],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'release' => $release,
        ];
    }

    /** @param array<string,mixed> $overrides @return array{json:string,signature:string,key_id:string} */
    public function mintAdvisory(array $overrides = []): array
    {
        return $this->signedDoc(array_replace([
            'format' => 'rb-advisory.v1',
            'advisory_uid' => 'RB-TEST-0001',
            'package_uid' => 'acme/midnight-theme',
            'affected_version_range' => '<=1.0.0',
            'severity' => 'high',
            'action' => 'block_new',
            'summary' => 'Test advisory fixture',
            'issued_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $overrides));
    }

    /** Old key signs the transition naming the successor. @return array{json:string,signature:string,key_id:string} */
    public function mintRotation(self $successor): array
    {
        return $this->signedDoc([
            'format' => 'rb-key-rotation.v1',
            'registry' => 'rb-test',
            'old_key_id' => $this->keyId,
            'new_key_id' => $successor->keyId(),
            'new_public_key' => base64_encode($successor->publicKey()),
            'effective_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /** @param array<string,mixed> $doc @return array{json:string,signature:string,key_id:string} */
    private function signedDoc(array $doc): array
    {
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode fixture document.');
        }

        return ['json' => $json, 'signature' => $this->sign($json), 'key_id' => $this->keyId];
    }
}
