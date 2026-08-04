<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use InvalidArgumentException;

/**
 * Out-of-band credential recovery for operators (`bin/console user:password`).
 *
 * Deliberately NOT the same path as AccountService::changePassword: that one
 * proves the current password and runs the write gate, which is exactly what a
 * locked-out operator cannot satisfy. Email fails closed when no From address is
 * configured, so /forgot is unavailable on a deployment that has not finished
 * wiring mail — this is the remaining way back in, and it is gated by holding
 * the database credentials rather than by anything the app can check.
 *
 * Account state is intentionally ignored: a suspended or banned administrator
 * still needs a working password to be un-suspended through the console.
 */
final class AccountRecoveryService
{
    /** Unambiguous alphabet — no 0/O or 1/l/I to mistype when read off a terminal. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private PasswordHasher $hasher,
        private Config $config,
    ) {
    }

    /**
     * Set a new password for the account matching an email address or username.
     *
     * @param string|null $password null generates one and returns it
     * @return array{user:array<string,mixed>, password:string, generated:bool}
     */
    public function resetPassword(string $identifier, ?string $password = null): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('Provide the account email address or username.');
        }

        $user = $this->users->findByEmail($identifier) ?? $this->users->findByUsername($identifier);
        if ($user === null) {
            throw new InvalidArgumentException(
                'No account matches "' . $identifier . '". Run `php bin/console user:list` to see what exists.',
            );
        }

        $generated = $password === null;
        $password ??= self::generatePassword();

        $minimum = (int) $this->config->get('limits.password_min', 8);
        if (strlen($password) < $minimum) {
            throw new InvalidArgumentException("The password must be at least {$minimum} characters.");
        }

        $this->users->updatePassword((int) $user['id'], $this->hasher->hash($password));
        // A credential change must never leave older sessions alive. The console
        // has no "current" session to preserve, so every one of them goes — if
        // the account was lost because someone else holds a session, this ends it.
        $this->sessions->revokeAllForUser((int) $user['id']);

        return ['user' => $user, 'password' => $password, 'generated' => $generated];
    }

    /** ~116 bits over the unambiguous alphabet at the default length. */
    public static function generatePassword(int $length = 20): string
    {
        $password = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }
        return $password;
    }
}
