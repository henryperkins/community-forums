<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * One configured application key with the two derivations RetroBoards has
 * always used for it (ADR 0059). Both are preserved exactly for compatibility:
 *
 * - Encryption key (SecretBox, AES-256-GCM): trim() the configured value. If what
 *   remains is all hex and at least 64 characters long, the key is its first 64
 *   hex digits as bytes; otherwise it is SHA-256 of the trimmed value. A value
 *   that trims to nothing has no encryption key.
 * - HMAC key (recovery codes, signed links, the OAuth state cookie, derived
 *   identifiers): the configured string byte for byte, with no trimming and no
 *   case folding.
 *
 * Two configured strings can therefore share an encryption key while keying
 * different HMACs (surrounding whitespace or a trailing newline, or for a hex
 * key, letter case or hex digits past the first 64). Each identifier names one
 * derivation: `encryptionId` the 32-byte AES key, `hmacId` the exact HMAC key.
 * They are 64-bit truncations of HMAC-SHA256 keyed by the secret over a fixed
 * label, so they can be stored and logged without revealing the key.
 */
final class AppKey
{
    private const ENCRYPTION_ID_LABEL = 'retroboards/app-key-id/encryption/v1';
    private const HMAC_ID_LABEL = 'retroboards/app-key-id/hmac/v1';

    private function __construct(
        public readonly string $source,
        private readonly string $hmacKey,
        private readonly ?string $encryptionKey,
        public readonly ?string $encryptionId,
        public readonly string $hmacId,
        public readonly bool $active,
        public readonly bool $verifies,
    ) {
    }

    public static function fromRaw(#[\SensitiveParameter] string $raw, string $source, bool $active, bool $verifies): self
    {
        $encryptionKey = self::encryptionKeyFor($raw);
        return new self(
            $source,
            $raw,
            $encryptionKey,
            $encryptionKey === null ? null : substr(hash_hmac('sha256', self::ENCRYPTION_ID_LABEL, $encryptionKey), 0, 16),
            substr(hash_hmac('sha256', self::HMAC_ID_LABEL, $raw), 0, 16),
            $active,
            $verifies,
        );
    }

    /** The historical SecretBox derivation, unchanged. Null when the value trims to nothing. */
    public static function encryptionKeyFor(#[\SensitiveParameter] string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (ctype_xdigit($trimmed) && strlen($trimmed) >= 64) {
            $bin = hex2bin(substr($trimmed, 0, 64));
            if (is_string($bin) && strlen($bin) === 32) {
                return $bin;
            }
        }
        return hash('sha256', $trimmed, true);
    }

    public function withVerifies(bool $verifies): self
    {
        return new self($this->source, $this->hmacKey, $this->encryptionKey, $this->encryptionId, $this->hmacId, $this->active, $verifies);
    }

    public function hasEncryptionKey(): bool
    {
        return $this->encryptionKey !== null;
    }

    /** @throws RuntimeException when the configured value trims to nothing */
    public function encryptionKey(): string
    {
        if ($this->encryptionKey === null) {
            throw new RuntimeException('APP_KEY is required for secret encryption.');
        }
        return $this->encryptionKey;
    }

    /** The exact configured string, as every HMAC consumer has always keyed with it. */
    public function hmacKey(): string
    {
        return $this->hmacKey;
    }

    public function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, $this->hmacKey);
    }

    /** @return array<string,mixed> never includes key material */
    public function __debugInfo(): array
    {
        return [
            'source' => $this->source,
            'encryptionId' => $this->encryptionId,
            'hmacId' => $this->hmacId,
            'active' => $this->active,
            'verifies' => $this->verifies,
        ];
    }
}
