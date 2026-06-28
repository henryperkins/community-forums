<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Small AES-256-GCM wrapper for app-local secrets. Callers store ciphertext,
 * nonce, and tag separately; plaintext never belongs in the database.
 */
final class SecretBox
{
    public function __construct(private string $appKey)
    {
    }

    /** @return array{ciphertext:string,nonce:string,tag:string} */
    public function encrypt(string $plaintext): array
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false || $tag === '') {
            throw new RuntimeException('Unable to encrypt secret.');
        }
        return ['ciphertext' => $ciphertext, 'nonce' => $nonce, 'tag' => $tag];
    }

    public function decrypt(string $ciphertext, string $nonce, string $tag): string
    {
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt secret.');
        }
        return $plaintext;
    }

    private function key(): string
    {
        $raw = trim($this->appKey);
        if ($raw === '') {
            throw new RuntimeException('APP_KEY is required for secret encryption.');
        }
        if (ctype_xdigit($raw) && strlen($raw) >= 64) {
            $bin = hex2bin(substr($raw, 0, 64));
            if (is_string($bin) && strlen($bin) === 32) {
                return $bin;
            }
        }
        return hash('sha256', $raw, true);
    }
}
