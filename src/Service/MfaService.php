<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Request;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\MfaRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\Totp;
use App\Security\WriteGate;

final class MfaService
{
    public function __construct(
        private MfaRepository $mfa,
        private UserRepository $users,
        private ReauthGate $reauth,
        private SecretBox $secrets,
        private Totp $totp,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private Config $config,
    ) {
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
        if ($row === null || ($row['enabled_at'] !== null && $row['disabled_at'] === null)) {
            throw new ValidationException(['totp_code' => 'Start two-factor enrollment before verifying a code.']);
        }

        $secret = $this->decryptSecret($row);
        $step = $this->totp->verify($secret, $code, null);
        if ($step === null) {
            throw new ValidationException(['totp_code' => 'Enter the current 6-digit code from your authenticator app.']);
        }

        $codes = $this->generateRecoveryCodes();
        $this->mfa->enableTotp($user->id(), $step);
        $this->mfa->replaceRecoveryCodes($user->id(), array_map(fn (string $c): string => $this->recoveryHash($c), $codes));
        $this->log($user->id(), 'mfa_totp_enabled', ['enabled' => false], ['enabled' => true, 'recovery_codes' => count($codes)]);
        return $codes;
    }

    public function beginLoginChallenge(User $user, Request $request, string $nextPath): string
    {
        return $this->mfa->createLoginChallenge($user->id(), $this->safeNext($nextPath), $request->ip(), $request->userAgent());
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

        $method = $this->verifySecondFactor($user->id(), $code);
        if ($method === null) {
            throw new ValidationException(['code' => 'Enter a valid authenticator or recovery code.']);
        }
        if (!$this->mfa->consumeLoginChallenge((int) $challenge['id'])) {
            throw new ValidationException(['code' => 'That two-factor challenge was already used. Please sign in again.']);
        }

        $this->log($user->id(), $method === 'totp' ? 'mfa_totp_login' : 'mfa_recovery_login', null, ['method' => $method]);
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
        $this->mfa->replaceRecoveryCodes($user->id(), array_map(fn (string $c): string => $this->recoveryHash($c), $codes));
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
        if ($this->verifySecondFactor($user->id(), $code) === null) {
            throw new ValidationException(['disable_code' => 'Enter a valid authenticator or recovery code to disable two-factor authentication.']);
        }

        $this->mfa->disableTotp($user->id());
        $this->log($user->id(), 'mfa_totp_disabled', ['enabled' => true], ['enabled' => false]);
    }

    private function requirePassword(User $user, string $currentPassword): void
    {
        $this->reauth->requirePassword(
            $user,
            $currentPassword,
            'current_password',
            'Set a password before managing two-factor authentication.',
        );
    }

    private function verifySecondFactor(int $userId, string $code): ?string
    {
        $row = $this->mfa->totpForUser($userId);
        if ($row === null || $row['enabled_at'] === null || $row['disabled_at'] !== null) {
            return null;
        }

        $secret = $this->decryptSecret($row);
        $lastStep = $row['last_used_step'] === null ? null : (int) $row['last_used_step'];
        $step = $this->totp->verify($secret, $code, $lastStep);
        if ($step !== null) {
            $this->mfa->markTotpUsed($userId, $step);
            return 'totp';
        }

        if ($this->mfa->consumeRecoveryCodeHash($userId, $this->recoveryHash($code))) {
            return 'recovery';
        }
        return null;
    }

    /** @param array<string,mixed> $row */
    private function decryptSecret(array $row): string
    {
        return $this->secrets->decrypt(
            (string) $row['secret_ciphertext'],
            (string) $row['secret_nonce'],
            (string) $row['secret_tag'],
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

    private function recoveryHash(string $code): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '';
        return hash_hmac('sha256', $normalized, (string) $this->config->get('app.key', ''));
    }

    private function safeNext(string $next): string
    {
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_starts_with($next, '/\\')) {
            return '/';
        }
        return $next;
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
