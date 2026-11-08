<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Controller;
use App\Core\Config;
use App\Core\Request;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\MfaRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\AppKey;
use App\Security\AppKeyRing;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\SecretUnavailableException;
use App\Security\Totp;
use App\Security\WriteGate;

final class MfaService
{
    /** Shown when the stored TOTP secret cannot be opened (sealed under a key this deployment lacks). */
    private const TOTP_UNAVAILABLE = 'Authenticator codes cannot be checked for this account right now.';

    private AppKeyRing $keys;

    public function __construct(
        private MfaRepository $mfa,
        private UserRepository $users,
        private ReauthGate $reauth,
        private SecretBox $secrets,
        private Totp $totp,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private Config $config,
        ?AppKeyRing $keys = null,
    ) {
        $this->keys = $keys ?? $secrets->keyRing();
    }

    public function enabledForUser(int $userId): bool
    {
        return $this->mfa->enabledForUser($userId);
    }

    /** @return array{enabled:bool,pending:bool,unused_recovery_codes:int} */
    public function status(int $userId): array
    {
        $row = $this->mfa->totpForUser($userId);
        $enabled = $row !== null && $row['enabled_at'] !== null && $row['disabled_at'] === null;
        $pending = $row !== null && !$enabled && $row['disabled_at'] === null;
        return [
            'enabled' => $enabled,
            'pending' => $pending,
            'unused_recovery_codes' => $this->mfa->unusedRecoveryCount($userId),
        ];
    }

    /** @return array{secret:string,uri:string} */
    public function startEnrollment(User $user, string $currentPassword): array
    {
        $this->writeGate->assertCanWrite($user);
        $this->requirePassword($user, $currentPassword);
        if ($this->enabledForUser($user->id())) {
            throw new ValidationException(['totp' => 'Two-factor authentication is already enabled.']);
        }

        $secret = $this->totp->generateSecret();
        $this->mfa->savePendingTotp($user->id(), $this->secrets->encrypt($secret));
        $this->log($user->id(), 'mfa_totp_enrollment_started', null, ['pending' => true]);

        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri(
                (string) $this->config->get('app.name', 'RetroBoards'),
                $user->email(),
                $secret,
            ),
        ];
    }

    /** @return list<string> */
    public function confirmEnrollment(User $user, string $currentPassword, string $code): array
    {
        $this->writeGate->assertCanWrite($user);
        $this->requirePassword($user, $currentPassword);
        $row = $this->mfa->totpForUser($user->id());
        if ($row === null || $row['enabled_at'] !== null || $row['disabled_at'] !== null) {
            throw new ValidationException(['totp_code' => 'Start two-factor enrollment before verifying a code.']);
        }

        try {
            $secret = $this->decryptSecret($row);
        } catch (SecretUnavailableException) {
            // Sealed under a key this deployment no longer has. Starting again
            // overwrites the pending row with a secret sealed under the current key.
            $this->reportUnreadableSecret($user->id(), 'pending enrollment');
            throw new ValidationException(['totp_code' => 'This setup can no longer be verified. Restart setup to get a new authenticator secret.']);
        }
        $step = $this->totp->verify($secret, $code, null);
        if ($step === null) {
            throw new ValidationException(['totp_code' => 'Enter the current 6-digit code from your authenticator app.']);
        }

        $codes = $this->generateRecoveryCodes();
        $this->mfa->enableTotp($user->id(), $step);
        $this->storeRecoveryCodes($user->id(), $codes);
        $this->log($user->id(), 'mfa_totp_enabled', ['enabled' => false], ['enabled' => true, 'recovery_codes' => count($codes)]);
        return $codes;
    }

    public function beginLoginChallenge(User $user, Request $request, string $nextPath): string
    {
        return $this->mfa->createLoginChallenge($user->id(), Controller::localPath($nextPath, '/'), $request->ip(), $request->userAgent());
    }

    /**
     * Resolve the account behind a still-valid login challenge WITHOUT consuming
     * it, so the caller can throttle second-factor guesses per account rather than
     * per challenge token (a fresh token must not buy a fresh guess budget).
     */
    public function challengeUser(string $token): ?User
    {
        $challenge = $this->mfa->findValidLoginChallenge($token);
        if ($challenge === null) {
            return null;
        }
        $user = $this->users->findEntity((int) $challenge['user_id']);
        return $user === null || $user->isBanned() ? null : $user;
    }

    /** @return array{user:User,next:string,method:string} */
    public function completeLoginChallenge(string $token, string $code): array
    {
        $challenge = $this->mfa->findValidLoginChallenge($token);
        if ($challenge === null) {
            throw new ValidationException(['code' => 'That two-factor challenge expired. Please sign in again.']);
        }
        $user = $this->users->findEntity((int) $challenge['user_id']);
        if ($user === null || $user->isBanned()) {
            throw new ValidationException(['code' => 'That account cannot sign in.']);
        }

        $check = $this->verifySecondFactor($user->id(), $code);
        $method = $check['method'];
        if ($method === null) {
            // De-silence failed second factors so brute-force leaves an audit trail.
            $this->log($user->id(), 'mfa_login_failed', null, $this->auditDetail(['method' => 'unknown'], $check));
            throw new ValidationException(['code' => $check['totp_unavailable']
                ? self::TOTP_UNAVAILABLE . ' Enter one of your recovery codes instead, or contact the forum operator.'
                : 'Enter a valid authenticator or recovery code.']);
        }
        if (!$this->mfa->consumeLoginChallenge((int) $challenge['id'])) {
            throw new ValidationException(['code' => 'That two-factor challenge was already used. Please sign in again.']);
        }

        $this->log($user->id(), $method === 'totp' ? 'mfa_totp_login' : 'mfa_recovery_login', null, $this->auditDetail(['method' => $method], $check));
        return ['user' => $user, 'next' => (string) $challenge['next_path'], 'method' => $method];
    }

    /** @return list<string> */
    public function rotateRecoveryCodes(User $user, string $currentPassword): array
    {
        $this->writeGate->assertCanWrite($user);
        $this->requirePassword($user, $currentPassword);
        if (!$this->enabledForUser($user->id())) {
            throw new ValidationException(['recovery' => 'Enable two-factor authentication before rotating recovery codes.']);
        }

        $codes = $this->generateRecoveryCodes();
        $this->storeRecoveryCodes($user->id(), $codes);
        $this->log($user->id(), 'mfa_recovery_codes_rotated', null, ['recovery_codes' => count($codes)]);
        return $codes;
    }

    public function disable(User $user, string $currentPassword, string $code): void
    {
        $this->writeGate->assertCanWrite($user);
        $this->requirePassword($user, $currentPassword);
        if (!$this->enabledForUser($user->id())) {
            throw new ValidationException(['totp' => 'Two-factor authentication is not enabled.']);
        }
        $check = $this->verifySecondFactor($user->id(), $code);
        if ($check['method'] === null) {
            throw new ValidationException(['disable_code' => $check['totp_unavailable']
                ? self::TOTP_UNAVAILABLE . ' Enter one of your recovery codes to disable two-factor authentication.'
                : 'Enter a valid authenticator or recovery code to disable two-factor authentication.']);
        }

        $this->mfa->disableTotp($user->id());
        $this->log($user->id(), 'mfa_totp_disabled', ['enabled' => true], $this->auditDetail(['enabled' => false], $check));
    }

    private function requirePassword(User $user, string $currentPassword): void
    {
        $this->reauth->requirePassword(
            $user,
            $currentPassword,
            missingPasswordError: 'Set a password in Security before managing two-factor authentication.',
        );
    }

    /**
     * Check an authenticator code, then a one-time recovery code. A stored TOTP
     * secret that cannot be opened never satisfies the check: the authenticator
     * step is skipped, the recovery code is still evaluated (and consumed
     * atomically), and the caller learns the secret was unavailable.
     *
     * @return array{method:?string,totp_unavailable:bool}
     */
    private function verifySecondFactor(int $userId, string $code): array
    {
        $row = $this->mfa->totpForUser($userId);
        if ($row === null || $row['enabled_at'] === null || $row['disabled_at'] !== null) {
            return ['method' => null, 'totp_unavailable' => false];
        }

        $secret = null;
        try {
            $secret = $this->decryptSecret($row);
        } catch (SecretUnavailableException) {
            $this->reportUnreadableSecret($userId, 'enabled');
        }
        if ($secret !== null) {
            $lastStep = $row['last_used_step'] === null ? null : (int) $row['last_used_step'];
            $step = $this->totp->verify($secret, $code, $lastStep);
            if ($step !== null) {
                $this->mfa->markTotpUsed($userId, $step);
                return ['method' => 'totp', 'totp_unavailable' => false];
            }
        }

        return ['method' => $this->consumeRecoveryCode($userId, $code) ? 'recovery' : null, 'totp_unavailable' => $secret === null];
    }

    /**
     * Try the code under each key permitted to verify (ADR 0059): the active key,
     * then previous keys listed in APP_PREVIOUS_KEY_VERIFY_IDS. Each attempt is one
     * atomic conditional UPDATE, and at most one key's hash can match a code. A
     * legacy code that matches proves its batch's key, which is then recorded.
     */
    private function consumeRecoveryCode(int $userId, string $code): bool
    {
        foreach ($this->keys->verificationKeys() as $key) {
            $hash = $this->recoveryHash($code, $key);
            if ($this->mfa->consumeRecoveryCodeHash($userId, $hash, $key->hmacId)) {
                $this->mfa->attributeRecoveryBatch($userId, $hash, $key->hmacId);
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $codes */
    private function storeRecoveryCodes(int $userId, array $codes): void
    {
        $active = $this->keys->active();
        $this->mfa->replaceRecoveryCodes(
            $userId,
            array_map(fn (string $c): string => $this->recoveryHash($c, $active), $codes),
            $active->hmacId,
        );
    }

    /**
     * @param array<string,mixed> $detail
     * @param array{method:?string,totp_unavailable:bool} $check
     * @return array<string,mixed>
     */
    private function auditDetail(array $detail, array $check): array
    {
        return $check['totp_unavailable'] ? $detail + ['totp_unavailable' => true] : $detail;
    }

    /** Operator signal: the member's secret was sealed under a key this deployment does not have. */
    private function reportUnreadableSecret(int $userId, string $state): void
    {
        error_log(sprintf('[RetroBoards] Two-factor secret (%s) for user #%d cannot be opened with the configured APP_KEY.', $state, $userId));
    }

    /** @param array<string,mixed> $row */
    private function decryptSecret(array $row): string
    {
        return $this->secrets->decrypt(
            (string) $row['secret_ciphertext'],
            (string) $row['secret_nonce'],
            (string) $row['secret_tag'],
            isset($row['secret_key_id']) ? (string) $row['secret_key_id'] : null,
        );
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5)));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        return $codes;
    }

    private function recoveryHash(string $code, AppKey $key): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '';
        return $key->hmac($normalized);
    }

    /** @param mixed $before @param mixed $after */
    private function log(int $userId, string $action, mixed $before, mixed $after): void
    {
        $this->audit->log([
            'actor_id' => $userId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $userId,
            'reason' => 'account security',
            'before' => $before,
            'after' => $after,
        ]);
    }
}
