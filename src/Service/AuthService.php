<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\ValidationException;
use App\Domain\User;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

/**
 * Registration + credential verification. Passwords are stored only as Argon2id
 * hashes; login failures are intentionally indistinguishable (no account
 * enumeration).
 */
final class AuthService
{
    /** A valid Argon2id hash used to equalise timing when no account matches. */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$YzVtQVBsaXBkYXAwVE9vUw$NR06J7+ij+3czlK3xUfeY38Cy7DzipfC/ArfyUyckYI';

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private Config $config,
        private ?FirstPartyHookRegistry $hooks = null,
    ) {
    }

    /**
     * Validate and create a new account.
     *
     * @param array<string,mixed> $input
     * @param string $role 'user' normally; 'admin' for the first-run wizard
     */
    public function register(array $input, string $role = 'user'): User
    {
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $displayName = trim((string) ($input['display_name'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');

        $errors = [];

        if (!$this->validUsername($username)) {
            $errors['username'] = 'Username must be 3–32 characters: letters, numbers, or underscore, starting with a letter or number.';
        } elseif ($this->users->usernameExists($username)) {
            $errors['username'] = 'That username is already taken.';
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($this->users->emailExists($email)) {
            $errors['email'] = 'An account with that email already exists.';
        }

        $min = (int) $this->config->get('limits.password_min', 8);
        if (strlen($password) < $min) {
            $errors['password'] = "Password must be at least {$min} characters.";
        } elseif ($passwordConfirm !== '' && $password !== $passwordConfirm) {
            $errors['password_confirm'] = 'The passwords do not match.';
        }

        if ($displayName !== '' && mb_strlen($displayName) > (int) $this->config->get('limits.display_name_max', 64)) {
            $errors['display_name'] = 'Display name is too long.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, ['username' => $username, 'email' => $email, 'display_name' => $displayName]);
        }

        $id = $this->users->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => $this->hasher->hash($password),
            'display_name' => $displayName !== '' ? $displayName : null,
            'role' => $role,
            'status' => 'active',
        ]);

        $user = $this->users->findEntity($id);
        if ($user === null) {
            throw new \RuntimeException('Failed to load newly created user.');
        }
        if ($role === 'user') {
            $this->hooks?->emit('member.registered', [
                'user_id' => $user->id(),
                'oauth' => false,
                'email_verified' => false,
            ], 'user:' . $user->id() . ':registered');
        }
        return $user;
    }

    /** Return the user on valid credentials, or null (generic failure). */
    public function attempt(string $email, string $password): ?User
    {
        $row = $this->users->findByEmail(trim($email));
        if ($row === null) {
            // Equalise timing with the verify path so response time can't reveal
            // whether an account exists.
            $this->hasher->verify($password, self::DUMMY_HASH);
            return null;
        }
        $hash = is_string($row['password_hash'] ?? null) ? $row['password_hash'] : null;
        if (!$this->hasher->verify($password, $hash)) {
            return null;
        }

        if ($this->hasher->needsRehash((string) $hash)) {
            $this->users->updatePassword((int) $row['id'], $this->hasher->hash($password));
        }

        return User::fromRow($row);
    }

    private function validUsername(string $username): bool
    {
        $min = (int) $this->config->get('limits.username_min', 3);
        $max = (int) $this->config->get('limits.username_max', 32);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_]{' . ($min - 1) . ',' . ($max - 1) . '}$/', $username) === 1;
    }
}
