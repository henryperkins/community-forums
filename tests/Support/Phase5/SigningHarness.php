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
        $doc = array_replace([
            'format' => 'rb-registry-snapshot.v1',
            'registry' => 'rb-test',
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
            'packages' => [[
                'uid' => 'acme/midnight-theme',
                'type' => 'theme',
                'releases' => [[
                    'version' => '1.0.0',
                    'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.0.0'),
                    'core_min' => '0.1.0',
                    'core_max' => null,
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
     * @param array<string,mixed> $overrides keys: uid, version, artifact (bytes the digest hashes), manifest (array)
     * @return array{json:string,signature:string,key_id:string,digest:string,manifest_json:string,version:string,uid:string}
     */
    public function mintRelease(array $overrides = []): array
    {
        $uid = (string) ($overrides['uid'] ?? 'acme/midnight-theme');
        $version = (string) ($overrides['version'] ?? '1.0.0');
        $artifact = (string) ($overrides['artifact'] ?? "artifact:{$uid}:{$version}");
        $manifest = (array) ($overrides['manifest'] ?? [
            // Placeholder fixture shape; the real manifest.v2 schema is Inc 3
            // (P5-02 SP1) scope and will extend this signed-doc contract.
            'format' => 'rb-manifest.v2',
            'uid' => $uid,
            'type' => 'theme',
            'version' => $version,
        ]);
        $digest = hash('sha256', $artifact);

        $signed = $this->signedDoc([
            'format' => 'rb-release.v1',
            'uid' => $uid,
            'version' => $version,
            'digest' => $digest,
            'manifest' => $manifest,
        ]);

        return $signed + [
            'digest' => $digest,
            'manifest_json' => json_encode($manifest, JSON_UNESCAPED_SLASHES) ?: '{}',
            'version' => $version,
            'uid' => $uid,
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
