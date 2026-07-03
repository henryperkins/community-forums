<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ValidationException;
use App\Domain\User;

/**
 * Unified recent-reauthentication gate for high-impact actions (Foundation F7).
 * Policy: one factor list and one window, owned here. The current window is
 * "present the factor with the request itself" (window zero), matching the
 * accepted behavior of the call sites this consolidates. A non-zero session
 * window would require session-scoped state and lands with the increment that
 * needs it. Inc 7 added FACTOR_PASSKEY beside FACTOR_PASSWORD as a step-up
 * alternative behind this same API.
 */
final class ReauthGate
{
    public const FACTOR_PASSWORD = 'password';
    public const FACTOR_PASSKEY = 'passkey';

    public function __construct(private PasswordHasher $hasher)
    {
    }

    /**
     * Assert the actor just re-presented their password; throws the exact
     * legacy ValidationException on failure. $missingPasswordError, when given,
     * is thrown instead if the account has no password at all.
     */
    public function requirePassword(
        User $actor,
        string $currentPassword,
        string $field = 'current_password',
        ?string $missingPasswordError = null,
    ): void {
        if ($missingPasswordError !== null && $actor->passwordHash() === null) {
            throw new ValidationException([$field => $missingPasswordError]);
        }
        if (!$this->hasher->verify($currentPassword, $actor->passwordHash())) {
            throw new ValidationException([$field => 'Your current password is incorrect.']);
        }
    }

    public function requireFactor(
        User $actor,
        ?string $currentPassword,
        ?\Closure $passkeyProbe = null,
        string $field = 'current_password',
    ): string {
        if ($passkeyProbe !== null && $passkeyProbe() === true) {
            return self::FACTOR_PASSKEY;
        }
        if ($currentPassword !== null && $currentPassword !== '') {
            $this->requirePassword($actor, $currentPassword, $field);
            return self::FACTOR_PASSWORD;
        }

        throw new ValidationException([$field => 'Confirm this change with your password or a passkey.']);
    }

    /** Boolean form for collect-errors flows (AccountService::changePassword). */
    public function verifyPassword(User $actor, string $currentPassword): bool
    {
        return $this->hasher->verify($currentPassword, $actor->passwordHash());
    }
}
