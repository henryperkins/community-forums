<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Argon2id password hashing (DESIGN §11). Hashes are never logged or stored in
 * plaintext anywhere.
 */
final class PasswordHasher
{
    /**
     * Process-wide Argon2id cost override. Production never sets this, so it
     * keeps PHP's secure defaults (DESIGN §11). The test harness sets a trivial
     * cost in tests/bootstrap.php — default Argon2id is ~300ms per hash by
     * design, which otherwise dominates the suite. See setDefaultOptions().
     *
     * @var array<string,int>|null
     */
    private static ?array $defaultOptions = null;

    /** @var array<string,int> */
    private array $options;

    /** @param array<string,int> $options Argon2id cost overrides (empty ⇒ secure defaults) */
    public function __construct(array $options = [])
    {
        $this->options = $options ?: (self::$defaultOptions ?? []);
    }

    /**
     * Set the process-wide cost used when a PasswordHasher is constructed with
     * no explicit options. Intended for the test harness only; passing null
     * restores PHP's secure defaults.
     *
     * @param array<string,int>|null $options
     */
    public static function setDefaultOptions(?array $options): void
    {
        self::$defaultOptions = $options;
    }

    /** @return array<string,int>|null the current process-wide override (null = PHP secure defaults) */
    public static function defaultOptions(): ?array
    {
        return self::$defaultOptions;
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options);
    }

    public function verify(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }
}
