<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\Telemetry;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\MailException;
use App\Mail\Mailer;
use App\Repository\ModerationLogRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnChallengeRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\LastOwnerGuard;
use App\Security\ReauthGate;
use App\Security\Session;
use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnException;
use App\Security\WebAuthn\WebAuthnVerifier;
use App\Security\WriteGate;
use App\Support\Base64Url;

final class PasskeyService
{
    public const CHALLENGE_TTL = 300;
    public const MAX_ACTIVE_CREDENTIALS = 8;
    private const LOGIN_FAILED = 'That passkey could not be used to sign in.';
    private const LOGIN_ALLOW_CREDENTIAL_SLOTS = self::MAX_ACTIVE_CREDENTIALS;
    private const LOGIN_TRANSPORT_HINTS = ['internal', 'hybrid', 'usb', 'nfc', 'ble'];

    public function __construct(
        private readonly WebAuthnCredentialRepository $credentials,
        private readonly WebAuthnChallengeRepository $challenges,
        private readonly WebAuthnVerifier $verifier,
        private readonly RelyingParty $rp,
        private readonly UserRepository $users,
        private readonly OAuthIdentityRepository $oauthIdentities,
        private readonly MfaService $mfaService,
        private readonly ReauthGate $reauth,
        private readonly WriteGate $writeGate,
        private readonly LastOwnerGuard $lastOwnerGuard,
        private readonly ModerationLogRepository $log,
        private readonly Mailer $mailer,
        private readonly Config $config,
        private readonly Database $db,
        private readonly ?Telemetry $telemetry = null,
    ) {
    }

    public static function sessionBinding(Session $session): string
    {
        return hash('sha256', $session->csrfSecret());
    }

    /** @return array{credentials:list<array<string,mixed>>,has_password:bool,has_provider:bool} */
    public function status(User $user): array
    {
        $rows = [];
        foreach ($this->credentials->activeForUser($user->id()) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'nickname' => (string) ($row['nickname'] ?? ''),
                'created_at' => (string) $row['created_at'],
                'last_used_at' => $row['last_used_at'],
                'transports' => (string) ($row['transports'] ?? ''),
                'backed_up' => (int) ($row['is_backed_up'] ?? 0) === 1,
            ];
        }

        return [
            'credentials' => $rows,
            'has_password' => $user->passwordHash() !== null,
            'has_provider' => $this->oauthIdentities->countForUser($user->id()) > 0,
        ];
    }

    public function assertFreshFactor(User $user, ?string $currentPassword, ?string $assertionJson, string $sessionHash): string
    {
        $probe = null;
        if ($assertionJson !== null && $assertionJson !== '') {
            $probe = function () use ($user, $assertionJson, $sessionHash): bool {
                $this->verifyStepUp($user, $sessionHash, $assertionJson);
                return true;
            };
        }

        return $this->reauth->requireFactor($user, $currentPassword, $probe);
    }

    /** @return array<string,mixed> */
    public function beginRegistration(User $user, string $sessionHash): array
    {
        $this->writeGate->assertCanWrite($user);
        $this->rp->assertUsable();
        $this->challenges->purgeExpired();

        $challenge = random_bytes(32);
        $this->challenges->mint($user->id(), $sessionHash, 'register', $challenge, self::CHALLENGE_TTL);

        $exclude = [];
        foreach ($this->credentials->activeForUser($user->id()) as $row) {
            $transports = (string) ($row['transports'] ?? '');
            $exclude[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode((string) $row['credential_id']),
                'transports' => $transports !== '' ? explode(',', $transports) : [],
            ];
        }

        return [
            'rp' => ['id' => $this->rp->rpId(), 'name' => (string) $this->config->get('app.name', 'RetroBoards')],
            'user' => [
                'id' => Base64Url::encode(pack('J', $user->id())),
                'name' => $user->username(),
                'displayName' => $user->displayName(),
            ],
            'challenge' => Base64Url::encode($challenge),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => self::CHALLENGE_TTL * 1000,
            'excludeCredentials' => $exclude,
            'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'preferred'],
            'attestation' => 'none',
        ];
    }

    /** @return array<string,mixed> */
    public function completeRegistration(User $user, string $sessionHash, string $credentialJson, ?string $nickname): array
    {
        $this->writeGate->assertCanWrite($user);
        $payload = $this->decodePayload($credentialJson);
        $challenge = $this->challengeFromPayload($payload);
        $nickname = $nickname !== null ? trim($nickname) : null;
        if ($nickname === '') {
            $nickname = null;
        } elseif ($nickname !== null && mb_strlen($nickname) > 120) {
            $nickname = mb_substr($nickname, 0, 120);
        }

        $row = $this->db->transaction(function () use ($user, $sessionHash, $payload, $challenge, $nickname): array {
            if (!$this->challenges->consume($challenge, $sessionHash, 'register', $user->id())) {
                throw new ValidationException(['passkey' => 'This passkey request expired or was already used - try again.']);
            }
            if ($this->credentials->countActiveForUser($user->id()) >= self::MAX_ACTIVE_CREDENTIALS) {
                throw new ValidationException(['passkey' => 'Remove an old passkey before adding another one.']);
            }

            $verified = $this->verifier->verifyRegistration($payload, $challenge);
            if ($this->credentials->findActiveByCredentialId($verified->credentialId) !== null) {
                throw new ValidationException(['passkey' => 'This passkey is already registered.']);
            }

            $isDiscoverable = (bool) (($payload['credProps']['rk'] ?? false) === true);
            try {
                $id = $this->credentials->create([
                    'user_id' => $user->id(),
                    'credential_id' => $verified->credentialId,
                    'public_key' => $verified->publicKey,
                    'sign_count' => $verified->signCount,
                    'aaguid' => $verified->aaguid,
                    'transports' => $verified->transports,
                    'is_discoverable' => $isDiscoverable ? 1 : 0,
                    'is_backup_eligible' => $verified->backupEligible ? 1 : 0,
                    'is_backed_up' => $verified->backedUp ? 1 : 0,
                    'nickname' => $nickname,
                ]);
            } catch (\PDOException) {
                throw new ValidationException(['passkey' => 'This passkey is already registered.']);
            }

            $this->audit($user->id(), 'passkey_registered', ['credential' => $id, 'nickname' => $nickname]);
            return ['id' => $id, 'nickname' => $nickname];
        });

        $this->notify(
            $user,
            'A passkey was added to your account',
            'A new passkey' . ($row['nickname'] !== null ? ' ("' . $row['nickname'] . '")' : '') . ' was just added to your account.',
        );
        $this->telemetry?->emit('passkey.registered', ['user' => $user->id()]);

        return $row;
    }

    /** @return array<string,mixed> */
    public function beginStepUp(User $user, string $sessionHash): array
    {
        $this->rp->assertUsable();
        $this->challenges->purgeExpired();
        $challenge = random_bytes(32);
        $this->challenges->mint($user->id(), $sessionHash, 'step_up', $challenge, self::CHALLENGE_TTL);
        return $this->requestOptions($challenge, $this->credentials->activeForUser($user->id()), 'required');
    }

    public function verifyStepUp(User $user, string $sessionHash, string $credentialJson): void
    {
        $payload = $this->decodePayload($credentialJson);
        $challenge = $this->challengeFromPayload($payload);
        $rawId = Base64Url::decode((string) ($payload['rawId'] ?? ''));
        $row = ($rawId !== null && $rawId !== '') ? $this->credentials->findActiveByCredentialId($rawId) : null;
        if ($row === null || (int) $row['user_id'] !== $user->id()) {
            throw new ValidationException(['passkey' => 'That passkey is not registered to this account.']);
        }
        if (!$this->challenges->consume($challenge, $sessionHash, 'step_up', $user->id())) {
            throw new ValidationException(['passkey' => 'The passkey confirmation expired - try again.']);
        }

        $result = $this->verifier->verifyAssertion($payload, $challenge, (string) $row['public_key'], (int) $row['sign_count'], true);
        $this->credentials->updateOnUse((int) $row['id'], $result->signCount);
        if ($result->counterAnomaly) {
            $this->recordAnomaly($user->id(), (int) $row['id']);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function beginLogin(?string $email, string $sessionHash): array
    {
        $this->rp->assertUsable();
        $this->challenges->purgeExpired();
        $email = strtolower(trim((string) $email));
        $challenge = random_bytes(32);

        $userRow = $email !== '' ? $this->users->findByEmail($email) : null;
        $credentials = $userRow !== null ? $this->credentials->activeForUser((int) $userRow['id']) : [];
        if ($userRow === null || $credentials === []) {
            return $this->loginRequestOptions($challenge, [], $email);
        }

        $this->challenges->mint((int) $userRow['id'], $sessionHash, 'login', $challenge, self::CHALLENGE_TTL);
        return $this->loginRequestOptions($challenge, $credentials, $email);
    }

    /**
     * @return array{user:User,used_uv:bool}
     */
    public function completeLogin(string $credentialJson, string $sessionHash): array
    {
        $payload = $this->decodePayload($credentialJson);
        $challenge = $this->challengeFromPayload($payload);
        $rawId = Base64Url::decode((string) ($payload['rawId'] ?? ''));
        $row = ($rawId !== null && $rawId !== '') ? $this->credentials->findActiveByCredentialId($rawId) : null;
        if ($row === null) {
            throw new ValidationException(['passkey' => self::LOGIN_FAILED]);
        }

        $userId = (int) $row['user_id'];
        if (!$this->challenges->consume($challenge, $sessionHash, 'login', $userId)) {
            throw new ValidationException(['passkey' => self::LOGIN_FAILED]);
        }

        $user = $this->users->findEntity($userId);
        if ($user === null || $user->isBanned()) {
            throw new ValidationException(['passkey' => 'This account is not permitted to sign in.']);
        }

        $requireUv = $this->mfaService->enabledForUser($userId);
        try {
            $result = $this->verifier->verifyAssertion($payload, $challenge, (string) $row['public_key'], (int) $row['sign_count'], $requireUv);
        } catch (WebAuthnException $e) {
            if ($e->code === 'uv_required') {
                throw new ValidationException(['passkey' => 'This account uses two-factor authentication: use a passkey with a screen lock, or sign in with your password and code.']);
            }
            throw new ValidationException(['passkey' => self::LOGIN_FAILED]);
        }

        $this->credentials->updateOnUse((int) $row['id'], $result->signCount);
        if ($result->counterAnomaly) {
            $this->recordAnomaly($userId, (int) $row['id']);
        }
        $this->audit($userId, 'passkey_login', ['credential' => (int) $row['id'], 'uv' => $result->userVerified]);
        $this->telemetry?->emit('passkey.login', ['user' => $userId]);

        return ['user' => $user, 'used_uv' => $result->userVerified];
    }

    public function rename(User $user, int $credentialId, string $nickname): void
    {
        $this->writeGate->assertCanWrite($user);
        $nickname = trim($nickname);
        if ($nickname === '' || mb_strlen($nickname) > 120) {
            throw new ValidationException(['nickname' => 'Pick a name between 1 and 120 characters.']);
        }
        if (!$this->credentials->rename($user->id(), $credentialId, $nickname)) {
            throw new ValidationException(['passkey' => 'That passkey was not found.']);
        }
        $this->audit($user->id(), 'passkey_renamed', ['credential' => $credentialId, 'nickname' => $nickname]);
    }

    public function remove(User $user, int $credentialId, ?string $currentPassword, ?string $assertionJson, string $sessionHash): void
    {
        $this->writeGate->assertCanWrite($user);
        $this->assertFreshFactor($user, $currentPassword, $assertionJson, $sessionHash);

        $removedNickname = $this->db->transaction(function () use ($user, $credentialId): ?string {
            $rows = $this->credentials->activeForUserForUpdate($user->id());
            $target = null;
            foreach ($rows as $row) {
                if ((int) $row['id'] === $credentialId) {
                    $target = $row;
                    break;
                }
            }
            if ($target === null) {
                throw new ValidationException(['passkey' => 'That passkey was not found.']);
            }

            $fresh = $this->users->findEntity($user->id());
            $hasPassword = $fresh !== null && $fresh->passwordHash() !== null;
            $hasProvider = $this->oauthIdentities->countForUser($user->id()) > 0;
            if (count($rows) === 1 && !$hasPassword && !$hasProvider) {
                $this->lastOwnerGuard->assertNotLastOwnerForUpdate($user, 'passkey');
                throw new ValidationException(['passkey' => 'Add a password or another passkey before removing your only way to sign in.']);
            }

            $this->credentials->revoke($user->id(), $credentialId);
            $this->audit($user->id(), 'passkey_revoked', ['credential' => $credentialId, 'nickname' => $target['nickname']]);
            return $target['nickname'] !== null ? (string) $target['nickname'] : null;
        });

        $this->notify(
            $user,
            'A passkey was removed from your account',
            'A passkey' . ($removedNickname !== null ? ' ("' . $removedNickname . '")' : '') . ' was removed from your account.',
        );
        $this->telemetry?->emit('passkey.revoked', ['user' => $user->id()]);
    }

    /** @param list<array<string,mixed>> $credentialRows @return array<string,mixed> */
    private function requestOptions(string $challenge, array $credentialRows, string $userVerification): array
    {
        $allow = [];
        foreach ($credentialRows as $row) {
            $transports = (string) ($row['transports'] ?? '');
            $allow[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode((string) $row['credential_id']),
                'transports' => $transports !== '' ? explode(',', $transports) : [],
            ];
        }

        return [
            'challenge' => Base64Url::encode($challenge),
            'rpId' => $this->rp->rpId(),
            'timeout' => self::CHALLENGE_TTL * 1000,
            'allowCredentials' => $allow,
            'userVerification' => $userVerification,
        ];
    }

    /** @param list<array<string,mixed>> $credentialRows @return array<string,mixed> */
    private function loginRequestOptions(string $challenge, array $credentialRows, string $email): array
    {
        $key = (string) $this->config->get('app.key', '');
        if ($key === '') {
            throw new WebAuthnException('missing_app_key', 'APP_KEY is required for enumeration-safe passkey login decoys.');
        }

        $allow = [];
        foreach (array_slice($credentialRows, 0, self::LOGIN_ALLOW_CREDENTIAL_SLOTS) as $row) {
            $allow[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode((string) $row['credential_id']),
                'transports' => self::LOGIN_TRANSPORT_HINTS,
            ];
        }

        for ($i = count($allow); $i < self::LOGIN_ALLOW_CREDENTIAL_SLOTS; $i++) {
            $allow[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode(hash_hmac('sha256', 'passkey-decoy:' . $email . ':' . $i, $key, true)),
                'transports' => self::LOGIN_TRANSPORT_HINTS,
            ];
        }

        usort($allow, static function (array $a, array $b) use ($key, $email): int {
            $ha = hash_hmac('sha256', 'passkey-slot:' . $email . ':' . (string) $a['id'], $key);
            $hb = hash_hmac('sha256', 'passkey-slot:' . $email . ':' . (string) $b['id'], $key);
            return $ha <=> $hb;
        });

        return [
            'challenge' => Base64Url::encode($challenge),
            'rpId' => $this->rp->rpId(),
            'timeout' => self::CHALLENGE_TTL * 1000,
            'allowCredentials' => $allow,
            'userVerification' => 'preferred',
        ];
    }

    /** @return array<string,mixed> */
    private function decodePayload(string $credentialJson): array
    {
        $payload = json_decode($credentialJson, true);
        if (!is_array($payload)) {
            throw new ValidationException(['passkey' => 'The passkey response could not be read.']);
        }
        return $payload;
    }

    /** @param array<string,mixed> $payload */
    private function challengeFromPayload(array $payload): string
    {
        $response = $payload['response'] ?? null;
        if (!is_array($response)) {
            throw new ValidationException(['passkey' => 'The passkey response could not be read.']);
        }

        $clientDataRaw = Base64Url::decode((string) ($response['clientDataJSON'] ?? ''));
        $clientData = $clientDataRaw !== null ? json_decode($clientDataRaw, true) : null;
        $challenge = is_array($clientData) ? Base64Url::decode((string) ($clientData['challenge'] ?? '')) : null;
        if ($challenge === null || $challenge === '') {
            throw new ValidationException(['passkey' => 'The passkey response could not be read.']);
        }
        return $challenge;
    }

    private function recordAnomaly(int $userId, int $credentialId): void
    {
        $this->audit($userId, 'passkey_counter_anomaly', ['credential' => $credentialId]);
        $this->telemetry?->emit('passkey.counter_anomaly', ['user' => $userId, 'credential' => $credentialId]);
    }

    /** @param array<string,mixed> $context */
    private function audit(int $userId, string $action, array $context = []): void
    {
        $this->log->log([
            'actor_id' => $userId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $userId,
            'reason' => 'account security',
            'after' => $context,
        ]);
    }

    private function notify(User $user, string $subject, string $line): void
    {
        if (!$this->mailer->isConfigured()) {
            return;
        }

        $appName = (string) $this->config->get('app.name', 'RetroBoards');
        $text = $line . "\n\nIf this was not you, sign in with your password, review Settings > Security, and remove anything you do not recognize.";
        try {
            $this->mailer->send($user->email(), '[' . $appName . '] ' . $subject, $text, null);
        } catch (MailException) {
            // Best-effort security notice; the moderation_log row is durable.
        }
    }
}
