<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\OAuth\NormalizedIdentity;

/**
 * OAuth account resolution + linking (USER §2.3/§2.4, P2-10). This is the
 * security-critical core: it decides whether a provider callback logs in an
 * existing identity, links to the signed-in account, must NOT silently merge a
 * verified-email collision, or creates a new account — and it protects the
 * last login method on unlink. Banned accounts can never sign in via a provider
 * (state beats provider). Provider tokens are never stored.
 *
 * resolve() returns an outcome array: ['action' => ..., 'user'? => User, 'email'? => string].
 *   login                  — existing identity, sign this user in
 *   created                — new account created from the identity
 *   linked                 — identity attached to the signed-in user
 *   already_linked         — this provider is already connected to the signed-in user
 *   already_linked_elsewhere — the identity belongs to a different account
 *   collision              — a local account owns this email; require login to link (never auto-merge)
 *   registration_closed    — public sign-ups are closed; a new account is refused (existing logins still work)
 *   banned                 — the resolved account is banned; refuse
 *   error                  — malformed identity
 */
final class OAuthService
{
    public function __construct(
        private Database $db,
        private OAuthIdentityRepository $identities,
        private UserRepository $users,
        private SettingRepository $settings,
        private ?FirstPartyHookRegistry $hooks = null,
    ) {
    }

    /**
     * @return array{action:string, user?:User, email?:string}
     */
    public function resolve(NormalizedIdentity $id, ?User $current): array
    {
        if ($id->providerUserId === '') {
            return ['action' => 'error'];
        }

        // 1. Returning user — the identity is already linked to an account.
        $existing = $this->identities->findByProvider($id->provider, $id->providerUserId);
        if ($existing !== null) {
            $user = $this->users->findEntity((int) $existing['user_id']);
            if ($user === null) {
                return ['action' => 'error'];
            }
            if ($user->isBanned()) {
                return ['action' => 'banned'];
            }
            if ($current !== null && $current->id() !== $user->id()) {
                return ['action' => 'already_linked_elsewhere'];
            }
            $this->identities->touchLogin((int) $existing['id']);
            return ['action' => 'login', 'user' => $user];
        }

        // 2. Explicit link to the signed-in account.
        if ($current !== null) {
            if ($current->isBanned()) {
                return ['action' => 'banned'];
            }
            if ($this->identities->existsForUserProvider($current->id(), $id->provider)) {
                return ['action' => 'already_linked', 'user' => $current];
            }
            $this->linkToUser($current->id(), $id);
            return ['action' => 'linked', 'user' => $current];
        }

        // 3. Email collision — a local account already owns this address. Never
        // auto-merge (provider-email spoofing). The user must log in to link it.
        if ($id->email !== null && $id->email !== '') {
            $local = $this->users->findByEmail($id->email);
            if ($local !== null) {
                return ['action' => 'collision', 'email' => $id->email];
            }
        }

        // 4. New account from the identity — but only when public registration is
        // open. An operator who closed sign-ups (P3-05 registration_mode) closes
        // the OAuth provisioning channel too, not just the email/password form;
        // otherwise "close sign-ups entirely" would leak a side door. Returning
        // logins (step 1) and linking to a signed-in account (step 2) are
        // deliberately unaffected — neither creates a new account.
        if ($this->settings->getString('registration_mode', 'open') === 'closed') {
            return ['action' => 'registration_closed'];
        }
        $user = $this->createFromIdentity($id);
        return ['action' => 'created', 'user' => $user];
    }

    /** Attach an identity to an account and import the provider avatar (USER §2.5). */
    public function linkToUser(int $userId, NormalizedIdentity $id): void
    {
        $this->db->transaction(function () use ($userId, $id): void {
            $this->identities->create([
                'user_id' => $userId,
                'provider' => $id->provider,
                'provider_user_id' => $id->providerUserId,
                'email' => $id->email,
                'email_verified' => $id->emailVerified,
                'avatar_url' => $id->avatarUrl,
                'provider_config_id' => $id->providerConfigId,
            ]);
            // A provider-verified email also verifies the local account's email.
            if ($id->emailVerified && $id->email !== null) {
                $local = $this->users->find($userId);
                if ($local !== null && strcasecmp((string) $local['email'], $id->email) === 0) {
                    $this->users->markEmailVerified($userId);
                }
            }
            if ($id->avatarUrl !== null && $id->avatarUrl !== '') {
                $this->users->setAvatarSource($userId, 'oauth');
            }
        });
    }

    /**
     * Unlink a provider, refusing if it would leave the account with no usable
     * login method (USER §2.4).
     *
     * @return bool true when a row was removed
     */
    public function unlink(User $user, string $provider): bool
    {
        $hasPassword = $user->passwordHash() !== null;
        $identityCount = $this->identities->countForUser($user->id());
        // After removing this provider, remaining = (identityCount - 1) + password.
        if (!$hasPassword && $identityCount <= 1) {
            throw new ValidationException(['provider' => 'Set a password first — this is your only way to sign in.']);
        }
        return $this->identities->delete($user->id(), $provider);
    }

    private function createFromIdentity(NormalizedIdentity $id): User
    {
        $emailVerified = $id->email !== null && $id->emailVerified;
        $user = $this->db->transaction(function () use ($id, $emailVerified): User {
            $username = $this->generateUsername($id->displayName ?? $this->localPart($id->email) ?? 'user');
            // Only a provider-VERIFIED email may occupy the globally-unique
            // users.email slot. An unverified provider email (e.g. GitHub can
            // surface one) would otherwise let an attacker squat a victim's
            // address and deny them registration, so it is parked on a synthetic
            // placeholder; the real address still rides on the oauth_identities
            // row and can be promoted once verified.
            $email = $emailVerified ? $id->email : ($username . '@' . $id->provider . '.oauth.invalid');

            $userId = $this->users->create([
                'username' => $username,
                'email' => $email,
                'password_hash' => null, // OAuth-only until they set a password
                'display_name' => $id->displayName,
                'role' => 'user',
                'status' => 'active',
            ]);
            if ($emailVerified) {
                $this->users->markEmailVerified($userId);
            }
            $this->linkToUser($userId, $id);

            $user = $this->users->findEntity($userId);
            if ($user === null) {
                throw new \RuntimeException('Failed to load the newly created account.');
            }
            return $user;
        });
        $this->hooks?->emit('member.registered', [
            'user_id' => $user->id(),
            'oauth' => true,
            'email_verified' => $emailVerified,
        ], 'user:' . $user->id() . ':registered');
        return $user;
    }

    /** Suggest a unique, valid handle from a display name / email local-part. */
    public function generateUsername(string $seed): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]/', '', $seed) ?? '';
        $base = ltrim($base, '_');
        if (strlen($base) < 3) {
            $base = 'user' . $base;
        }
        if (!preg_match('/^[A-Za-z0-9]/', $base)) {
            $base = 'u' . $base;
        }
        $base = substr($base, 0, 28);

        $candidate = $base;
        $i = 0;
        while ($this->users->usernameExists($candidate)) {
            $i++;
            $candidate = $base . $i;
        }
        return $candidate;
    }

    private function localPart(?string $email): ?string
    {
        if ($email === null || !str_contains($email, '@')) {
            return null;
        }
        return substr($email, 0, strpos($email, '@'));
    }
}
