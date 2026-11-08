<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Small AES-256-GCM wrapper for app-local secrets. Callers store ciphertext,
 * nonce, and tag separately; plaintext never belongs in the database.
 *
 * Encryption always uses the active key and reports its encryption key id, which
 * callers store beside the ciphertext (ADR 0059). Decryption with a recorded key
 * id uses exactly that key. A legacy record without one is tried against each
 * key that may decrypt, active first; GCM authentication rejects every wrong key.
 */
final class SecretBox
{
    private readonly AppKeyRing $keys;

    public function __construct(#[\SensitiveParameter] AppKeyRing|string $keys)
    {
        $this->keys = $keys instanceof AppKeyRing ? $keys : AppKeyRing::single($keys);
    }

    public function keyRing(): AppKeyRing
    {
        return $this->keys;
    }

    /** @return array{ciphertext:string,nonce:string,tag:string,key_id:string} */
    public function encrypt(string $plaintext): array
    {
        $key = $this->keys->active();
        $encryptionKey = $key->encryptionKey();
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false || $tag === '') {
            throw new RuntimeException('Unable to encrypt secret.');
        }
        return ['ciphertext' => $ciphertext, 'nonce' => $nonce, 'tag' => $tag, 'key_id' => (string) $key->encryptionId];
    }

    /**
     * @throws SecretUnavailableException when no permitted key can open the record
     * @throws RuntimeException when no APP_KEY is configured
     */
    public function decrypt(string $ciphertext, string $nonce, string $tag, ?string $keyId = null): string
    {
        return $this->open($ciphertext, $nonce, $tag, $keyId)['plaintext'];
    }

    /**
     * Decrypt and report which encryption key opened the record.
     *
     * @return array{plaintext:string,key_id:string}
     * @throws SecretUnavailableException when no permitted key can open the record
     * @throws RuntimeException when no APP_KEY is configured
     */
    public function open(string $ciphertext, string $nonce, string $tag, ?string $keyId = null): array
    {
        // Same configuration failure as before the key ring: a missing APP_KEY is not an unreadable record.
        $this->keys->active()->encryptionKey();
        if ($keyId !== null && $keyId !== '') {
            $key = $this->keys->forEncryptionId($keyId);
            if ($key === null) {
                throw new SecretUnavailableException('Unable to decrypt secret: encryption key ' . $keyId . ' is not configured.');
            }
            $candidates = [$key];
        } else {
            $candidates = $this->keys->decryptionKeys();
        }
        foreach ($candidates as $key) {
            $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key->encryptionKey(), OPENSSL_RAW_DATA, $nonce, $tag);
            if ($plaintext !== false) {
                return ['plaintext' => $plaintext, 'key_id' => (string) $key->encryptionId];
            }
        }
        throw new SecretUnavailableException('Unable to decrypt secret.');
    }
}
