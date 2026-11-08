<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;

/**
 * The application key ring (ADR 0059).
 *
 * - `APP_KEY` is the active key: every new encryption and every new signature
 *   uses it, and it always decrypts and verifies.
 * - `APP_PREVIOUS_KEY_1` … `_3` are earlier (or, while staging a rotation,
 *   upcoming) keys, each taken byte for byte. Every configured previous key may
 *   DECRYPT stored secrets. It may VERIFY HMAC artifacts (opt-out links,
 *   email-change confirmations, the OAuth state cookie, recovery codes) only when
 *   its HMAC key id is listed in `APP_PREVIOUS_KEY_VERIFY_IDS`. A compromised or
 *   backup-restore key is simply left off that list. Resubscription is never
 *   verified with a previous key.
 *
 * Construction never throws. Configuration problems are collected in errors();
 * `key:check` reports them and the container entrypoint refuses to start. At
 * runtime an invalid entry is ignored, so a mistake can only withhold a previous
 * key or a verification permission, never grant one.
 */
final class AppKeyRing
{
    public const PREVIOUS_SLOTS = 3;

    /**
     * @param list<AppKey> $previous in slot order
     * @param list<string> $errors
     */
    private function __construct(
        private readonly AppKey $active,
        private readonly array $previous,
        private readonly array $errors,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $slots = [];
        $configured = $config->get('app.previous_keys', []);
        foreach (is_array($configured) ? $configured : [] as $slot => $raw) {
            $slots[(int) $slot] = is_string($raw) ? $raw : null;
        }
        return self::build(
            (string) $config->get('app.key', ''),
            $slots,
            (string) $config->get('app.previous_key_verify_ids', ''),
        );
    }

    /** A ring holding only an active key: the pre-keyring behavior. */
    public static function single(#[\SensitiveParameter] string $active): self
    {
        return self::build($active);
    }

    /**
     * @param array<int,?string> $previous slot (1-3) => configured value; null or '' means not set
     */
    public static function build(
        #[\SensitiveParameter] string $active,
        #[\SensitiveParameter] array $previous = [],
        string $verifyIds = '',
    ): self {
        $errors = [];
        $activeKey = AppKey::fromRaw($active, 'APP_KEY', true, true);
        $seen = [$active => 'APP_KEY'];
        $candidates = [];
        ksort($previous);
        foreach ($previous as $slot => $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $name = 'APP_PREVIOUS_KEY_' . $slot;
            if (!is_int($slot) || $slot < 1 || $slot > self::PREVIOUS_SLOTS) {
                $errors[] = $name . ' is not supported; use APP_PREVIOUS_KEY_1 to APP_PREVIOUS_KEY_' . self::PREVIOUS_SLOTS . '.';
                continue;
            }
            if (!$activeKey->hasEncryptionKey()) {
                $errors[] = $name . ' is set but APP_KEY is empty.';
                continue;
            }
            if (trim($raw) === '') {
                $errors[] = $name . ' is blank.';
                continue;
            }
            if (isset($seen[$raw])) {
                $errors[] = $name . ' repeats ' . $seen[$raw] . ' byte for byte.';
                continue;
            }
            $key = AppKey::fromRaw($raw, $name, false, false);
            foreach ([$activeKey, ...$candidates] as $other) {
                if ($other->hmacId === $key->hmacId) {
                    // Distinct strings with one 64-bit id: never expected; refuse rather than guess.
                    $errors[] = $name . ' has the same HMAC key id as ' . $other->source . '.';
                    continue 2;
                }
            }
            $seen[$raw] = $name;
            $candidates[] = $key;
        }

        $allowed = [];
        $entries = array_values(array_filter(array_map('trim', explode(',', $verifyIds)), static fn (string $v): bool => $v !== ''));
        foreach ($entries as $index => $id) {
            if (preg_match('/\A[0-9a-f]{16}\z/D', $id) !== 1) {
                // Never echo the entry: a pasted key would end up in the log.
                $errors[] = 'APP_PREVIOUS_KEY_VERIFY_IDS entry ' . ($index + 1) . ' is not a 16-character lowercase hex HMAC key id.';
                continue;
            }
            if ($id === $activeKey->hmacId) {
                $errors[] = 'APP_PREVIOUS_KEY_VERIFY_IDS lists the active key (' . $id . '); APP_KEY always verifies.';
                continue;
            }
            $matched = false;
            foreach ($candidates as $candidate) {
                $matched = $matched || $candidate->hmacId === $id;
            }
            if (!$matched) {
                $errors[] = 'APP_PREVIOUS_KEY_VERIFY_IDS lists ' . $id . ', which is not the HMAC key id of any configured previous key.';
                continue;
            }
            $allowed[$id] = true;
        }

        $previousKeys = array_map(
            static fn (AppKey $key): AppKey => isset($allowed[$key->hmacId]) ? $key->withVerifies(true) : $key,
            $candidates,
        );
        return new self($activeKey, $previousKeys, $errors);
    }

    public function active(): AppKey
    {
        return $this->active;
    }

    /** @return list<AppKey> in slot order */
    public function previous(): array
    {
        return $this->previous;
    }

    /** @return list<string> configuration errors; never contain key material */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * Keys that may open stored secrets: the active key, then each previous key,
     * one per distinct encryption key.
     *
     * @return list<AppKey>
     */
    public function decryptionKeys(): array
    {
        $keys = [];
        foreach ([$this->active, ...$this->previous] as $key) {
            if ($key->hasEncryptionKey() && !isset($keys[$key->encryptionId])) {
                $keys[$key->encryptionId] = $key;
            }
        }
        return array_values($keys);
    }

    /**
     * Keys that may verify HMAC artifacts: the active key, then each previous key
     * whose HMAC key id is listed in APP_PREVIOUS_KEY_VERIFY_IDS.
     *
     * @return list<AppKey>
     */
    public function verificationKeys(): array
    {
        return [$this->active, ...array_values(array_filter($this->previous, static fn (AppKey $key): bool => $key->verifies))];
    }

    public function forEncryptionId(string $id): ?AppKey
    {
        foreach ($this->decryptionKeys() as $key) {
            if ($key->encryptionId === $id) {
                return $key;
            }
        }
        return null;
    }

    /** Any configured key with this HMAC key id, whatever its permissions. */
    public function forHmacId(string $id): ?AppKey
    {
        foreach ([$this->active, ...$this->previous] as $key) {
            if ($key->hmacId === $id) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Operator description for key:check and key:status. Never includes key material.
     *
     * @return list<array{source:string,role:string,encryption_id:?string,hmac_id:string,decrypts:bool,verifies:bool,shares_encryption_key_with:?string}>
     */
    public function describe(): array
    {
        $rows = [];
        $firstWithEncryptionId = [];
        foreach ([$this->active, ...$this->previous] as $key) {
            $shares = $key->encryptionId !== null ? ($firstWithEncryptionId[$key->encryptionId] ?? null) : null;
            if ($key->encryptionId !== null && $shares === null) {
                $firstWithEncryptionId[$key->encryptionId] = $key->source;
            }
            $rows[] = [
                'source' => $key->source,
                'role' => $key->active ? 'active' : 'previous',
                'encryption_id' => $key->encryptionId,
                'hmac_id' => $key->hmacId,
                'decrypts' => $key->hasEncryptionKey(),
                'verifies' => $key->active || $key->verifies,
                'shares_encryption_key_with' => $shares,
            ];
        }
        return $rows;
    }
}
