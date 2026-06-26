<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\WriteGate;

/**
 * Self-serve account slice (USER §8): edit display name / bio / location, and
 * change password (current password required). No email change in Phase 1.
 * Suspended/banned accounts are blocked here too (settings + password are
 * writes), via the shared write gate.
 */
final class AccountService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private WriteGate $writeGate,
        private Config $config,
    ) {
    }

    /** @param array<string,mixed> $input */
    public function updateProfile(User $user, array $input): void
    {
        $this->writeGate->assertCanWrite($user);

        $displayName = trim((string) ($input['display_name'] ?? ''));
        $bio = trim((string) ($input['bio'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));

        $errors = [];
        if (mb_strlen($displayName) > (int) $this->config->get('limits.display_name_max', 64)) {
            $errors['display_name'] = 'Display name is too long (max 64).';
        }
        if (mb_strlen($location) > (int) $this->config->get('limits.location_max', 64)) {
            $errors['location'] = 'Location is too long (max 64).';
        }
        if (mb_strlen($bio) > (int) $this->config->get('limits.bio_max', 1000)) {
            $errors['bio'] = 'Bio is too long (max 1000).';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        $this->users->updateProfile(
            $user->id(),
            $displayName !== '' ? $displayName : null,
            $bio !== '' ? $bio : null,
            $location !== '' ? $location : null,
        );
    }

    /** @param array<string,mixed> $input */
    public function changePassword(User $user, array $input): void
    {
        $this->writeGate->assertCanWrite($user);

        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');
        $confirm = (string) ($input['new_password_confirm'] ?? '');

        $errors = [];
        if (!$this->hasher->verify($current, $user->passwordHash())) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        $min = (int) $this->config->get('limits.password_min', 8);
        if (strlen($new) < $min) {
            $errors['new_password'] = "New password must be at least {$min} characters.";
        } elseif ($new !== $confirm) {
            $errors['new_password_confirm'] = 'The new passwords do not match.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->users->updatePassword($user->id(), $this->hasher->hash($new));
    }
}
