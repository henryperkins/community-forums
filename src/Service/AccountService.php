<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserProfileFieldRepository;
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
        private Database $db,
        private UserRepository $users,
        private PasswordHasher $hasher,
        private WriteGate $writeGate,
        private Config $config,
        private ?UserPreferenceRepository $prefs = null,
        private ?FeatureFlags $flags = null,
        private ?UserProfileFieldRepository $profileFields = null,
    ) {
    }

    /** @param array<string,mixed> $input */
    public function updateProfile(User $user, array $input): void
    {
        $this->writeGate->assertCanWrite($user);

        $displayName = trim((string) ($input['display_name'] ?? ''));
        $bio = trim((string) ($input['bio'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        $website = trim((string) ($input['website'] ?? ''));
        $pronouns = trim((string) ($input['pronouns'] ?? ''));
        $signature = trim((string) ($input['signature'] ?? ''));

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
        if ($website !== '') {
            if (mb_strlen($website) > 255) {
                $errors['website'] = 'Website URL is too long (max 255).';
            } elseif (!preg_match('~^https?://~i', $website) || filter_var($website, FILTER_VALIDATE_URL) === false) {
                $errors['website'] = 'Enter a valid http(s) URL.';
            }
        }
        if (mb_strlen($pronouns) > 32) {
            $errors['pronouns'] = 'Pronouns are too long (max 32).';
        }
        if (mb_strlen($signature) > 500) {
            $errors['signature'] = 'Signature is too long (max 500).';
        }
        $signatureLines = $signature === '' ? [] : preg_split('/\R/u', $signature);
        if (is_array($signatureLines) && count($signatureLines) > 3) {
            $errors['signature'] = 'Signature is too tall (max 3 lines).';
        }

        $customFields = [];
        if ($this->flags?->enabled('custom_profile_fields') && $this->profileFields !== null) {
            $customFields = $this->customProfileFields($input, $errors);
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        // The display-name/bio row and the custom-field rows are one logical
        // profile update — write them atomically so a failure can't commit one
        // without the other.
        $this->db->transaction(function () use ($user, $displayName, $bio, $location, $website, $pronouns, $signature, $customFields): void {
            $this->users->updateProfileFull(
                $user->id(),
                $displayName !== '' ? $displayName : null,
                $bio !== '' ? $bio : null,
                $location !== '' ? $location : null,
                $website !== '' ? $website : null,
                $pronouns !== '' ? $pronouns : null,
                $signature !== '' ? $signature : null,
            );
            if ($this->flags?->enabled('custom_profile_fields') && $this->profileFields !== null) {
                $this->profileFields->replaceForUser($user->id(), $customFields);
            }
        });
    }

    /** @param array<string,mixed> $input @param array<string,string> $errors @return array<int,array{label:string,value:string}> */
    private function customProfileFields(array $input, array &$errors): array
    {
        $fields = [];
        for ($i = 1; $i <= 3; $i++) {
            $label = trim((string) ($input['custom_label_' . $i] ?? ''));
            $value = trim((string) ($input['custom_value_' . $i] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            if ($label === '' || $value === '') {
                $errors['custom_profile_fields'] = 'Custom profile fields need both a label and a value.';
                continue;
            }
            if (mb_strlen($label) > 40) {
                $errors['custom_profile_fields'] = 'Custom profile labels are limited to 40 characters.';
            }
            if (mb_strlen($value) > 160) {
                $errors['custom_profile_fields'] = 'Custom profile values are limited to 160 characters.';
            }
            $fields[] = ['label' => $label, 'value' => $value];
        }
        return array_slice($fields, 0, 3);
    }

    /**
     * Privacy controls (USER §4.7): profile visibility, DM policy, presence flag
     * (columns), plus leaderboard opt-out + email discoverability (prefs blob).
     *
     * @param array<string,mixed> $input
     */
    public function updatePrivacy(User $user, array $input): void
    {
        $this->writeGate->assertCanWrite($user);

        $visibility = in_array($input['profile_visibility'] ?? '', ['public', 'members'], true)
            ? (string) $input['profile_visibility'] : 'public';
        $allowDms = in_array($input['allow_dms'] ?? '', ['everyone', 'members', 'none'], true)
            ? (string) $input['allow_dms'] : 'members';
        $showPresence = array_key_exists('show_presence', $input) && (string) $input['show_presence'] !== '0';

        $this->users->updatePrivacy($user->id(), $visibility, $allowDms, $showPresence);

        if ($this->prefs !== null) {
            $this->prefs->merge($user->id(), [
                'hide_from_leaderboard' => array_key_exists('hide_from_leaderboard', $input) && (string) $input['hide_from_leaderboard'] !== '0',
                'discoverable_by_email' => array_key_exists('discoverable_by_email', $input) && (string) $input['discoverable_by_email'] !== '0',
            ]);
        }
    }

    /**
     * Set an initial password for an OAuth-only account (USER §2.4). No current
     * password is required because there is none; refuses if one already exists
     * (use changePassword instead).
     *
     * @param array<string,mixed> $input
     */
    public function setInitialPassword(User $user, array $input): void
    {
        $this->writeGate->assertCanWrite($user);
        if ($user->passwordHash() !== null) {
            throw new ValidationException(['new_password' => 'This account already has a password. Use change password instead.']);
        }
        $new = (string) ($input['new_password'] ?? '');
        $confirm = (string) ($input['new_password_confirm'] ?? '');

        $errors = [];
        $min = (int) $this->config->get('limits.password_min', 8);
        if (strlen($new) < $min) {
            $errors['new_password'] = "Password must be at least {$min} characters.";
        } elseif ($new !== $confirm) {
            $errors['new_password_confirm'] = 'The passwords do not match.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->users->setPassword($user->id(), $this->hasher->hash($new));
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
