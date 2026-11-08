<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\AppKey;
use App\Security\AppKeyRing;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\OAuth\NormalizedIdentity;
use PDOException;
use Throwable;

/** Owner-authenticated, single-use contact change; the current contact remains active until confirmation. */
final class EmailChangeService
{
    private const TYPE = 'email_change';
    private const UNAVAILABLE = 'That email address is unavailable. Choose another address.';
    private const INVALID_LINK = 'This email change link is invalid or expired. Request a new link from account settings.';

    private AppKeyRing $keys;

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private VerificationRepository $verifications,
        private SessionRepository $sessions,
        private EmailDeliveryRepository $deliveries,
        private ModerationLogRepository $audit,
        private Mailer $mailer,
        private Config $config,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private FeatureFlags $flags,
        private OAuthIdentityRepository $oauthIdentities,
        private ?PasskeyService $passkeys = null,
        private ?EmailDomainVerifier $domainVerifier = null,
        private ?SettingRepository $settings = null,
        ?AppKeyRing $keys = null,
    ) {
        $this->keys = $keys ?? AppKeyRing::fromConfig($config);
    }

    /** Validate the candidate and sending readiness before starting a provider round trip. */
    public function prepare(User $actor, string $email): string
    {
        $this->assertEnabled();
        $email = trim($email);
        $fresh = $this->currentActor($actor, $email);
        $this->validateCandidate($fresh, $email);
        $this->assertMailReady($email);
        return $email;
    }

    /** The optional identity is supplied only by the server-verified, session-bound provider callback. */
    public function issue(
        User $actor,
        string $email,
        ?string $currentPassword,
        ?string $passkeyAssertion,
        string $sessionBinding,
        ?NormalizedIdentity $identity = null,
    ): void {
        $this->assertEnabled();
        $email = trim($email);
        $rawToken = bin2hex(random_bytes(32));
        $ttl = $this->ttlSeconds();

        try {
            $id = $this->db->transaction(function () use ($actor, $email, $currentPassword, $passkeyAssertion, $sessionBinding, $identity, $rawToken, $ttl): int {
                $fresh = $this->currentActor($actor, $email, true);
                $this->validateCandidate($fresh, $email);
                $this->assertMailReady($email);
                if ($identity !== null) {
                    $this->assertLinkedIdentity($fresh, $identity);
                } elseif ($this->passkeys !== null && $this->flags->enabled('passkeys')) {
                    $this->passkeys->assertFreshFactor($fresh, $currentPassword, $passkeyAssertion, $sessionBinding);
                } else {
                    $this->reauth->requireFactor($fresh, $currentPassword);
                }

                $this->verifications->invalidateOutstanding($fresh->id(), self::TYPE);
                $active = $this->keys->active();
                return $this->verifications->create(
                    $fresh->id(), self::TYPE, $this->tokenHash($fresh, $rawToken, $active),
                    gmdate('Y-m-d H:i:s', time() + $ttl), $email, $active->hmacId,
                );
            });
        } catch (ValidationException $error) {
            // Factors and assertions are never form-repopulation data.
            throw new ValidationException($error->errors, ['new_email' => $email]);
        }

        // The network transport runs after releasing the account lock. A failed
        // send retires only this issue, even if a newer request has replaced it.
        try {
            $this->sendVerification($email, $rawToken, $ttl);
        } catch (Throwable) {
            $this->verifications->markUsed($id);
            throw new ValidationException(
                ['new_email' => 'We could not send the verification email. Your current address is unchanged. Please try again or contact the forum operator.'],
                ['new_email' => $email],
            );
        }
    }

    /** @return array{id:int,new_email:string,expires_at:string}|null */
    public function pending(User $actor): ?array
    {
        $this->assertEnabled();
        $row = $this->verifications->findOutstandingForUser($actor->id(), self::TYPE);
        return $row === null ? null : $this->publicVerification($row);
    }

    /** Read-only lookup; a navigation or email scanner never consumes a link. @return array{id:int,new_email:string,expires_at:string}|null */
    public function findConfirmation(User $actor, string $rawToken): ?array
    {
        $this->assertEnabled();
        $fresh = $this->users->findEntity($actor->id());
        if ($fresh === null) {
            return null;
        }
        $row = $this->findForActor($fresh, $rawToken);
        return $row === null ? null : $this->publicVerification($row);
    }

    /** Switch contact, consume the link and queue the old-contact notice in one transaction. */
    public function confirm(User $actor, string $rawToken, ?string $currentSessionId): string
    {
        $this->assertEnabled();
        $email = '';
        try {
            return $this->db->transaction(function () use ($actor, $rawToken, $currentSessionId, &$email): string {
                $fresh = $this->currentActor($actor, '', true);
                $row = $this->findForActor($fresh, $rawToken);
                if ($row === null) {
                    throw new ValidationException(['new_email' => self::INVALID_LINK]);
                }
                $email = trim((string) $row['new_email']);
                $this->validateCandidate($fresh, $email);
                if ($this->verifications->consumeValid((int) $row['id'], $fresh->id(), self::TYPE, (string) $row['token_hash']) !== 1) {
                    throw new ValidationException(['new_email' => self::INVALID_LINK]);
                }
                $this->users->changeVerifiedEmail($fresh->id(), $email);
                foreach ([self::TYPE, 'email_verify', 'password_reset'] as $type) {
                    $this->verifications->invalidateOutstanding($fresh->id(), $type);
                }

                $session = $currentSessionId === null ? null : $this->sessions->findActive($currentSessionId);
                if ($session !== null && (int) $session['user_id'] === $fresh->id()) {
                    $this->sessions->revokeOthersForUser($fresh->id(), $currentSessionId);
                } else {
                    $this->sessions->revokeAllForUser($fresh->id());
                }

                $this->deliveries->suppressQueuedForPreviousEmail($fresh->id(), $fresh->email());
                $this->deliveries->enqueue($fresh->id(), $fresh->email(), 'system', null,
                    'email-change:' . (int) $row['id'], ['type' => 'email_changed']);
                $this->audit->log([
                    'actor_id' => $fresh->id(), 'action' => 'email_changed', 'target_type' => 'user', 'target_id' => $fresh->id(),
                    'after' => ['verification_id' => (int) $row['id']],
                ]);
                return $email;
            });
        } catch (ValidationException $error) {
            throw new ValidationException($error->errors, ['new_email' => $email]);
        } catch (PDOException $error) {
            // The unique users.email index is authoritative even across accounts
            // confirming the same candidate simultaneously. Never expose SQL/PII.
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException(['new_email' => self::UNAVAILABLE], ['new_email' => $email]);
            }
            throw $error;
        }
    }

    public function cancel(User $actor): void
    {
        $this->assertEnabled();
        $this->db->transaction(function () use ($actor): void {
            $fresh = $this->currentActor($actor, '', true);
            $this->verifications->invalidateOutstanding($fresh->id(), self::TYPE);
        });
    }

    private function assertEnabled(): void
    {
        if (!$this->flags->enabled('email_change')) {
            throw new NotFoundException();
        }
    }

    private function currentActor(User $actor, string $email, bool $lock = false): User
    {
        $row = $lock ? $this->users->findForUpdate($actor->id()) : $this->users->find($actor->id());
        if ($row === null) {
            throw new ValidationException(['new_email' => 'This account is no longer available.'], ['new_email' => $email]);
        }
        $fresh = User::fromRow($row);
        $this->writeGate->assertCanWrite($fresh);
        return $fresh;
    }

    private function validateCandidate(User $actor, string $email): void
    {
        if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['new_email' => 'Enter a valid email address.'], ['new_email' => $email]);
        }
        if (strcasecmp($actor->email(), $email) === 0) {
            throw new ValidationException(['new_email' => 'Enter a different email address from your current address.'], ['new_email' => $email]);
        }
        if ($this->users->emailExists($email)) {
            throw new ValidationException(['new_email' => self::UNAVAILABLE], ['new_email' => $email]);
        }
    }

    private function assertMailReady(string $email): void
    {
        $error = null;
        if ($this->keys->active()->hmacKey() === '') {
            $error = 'Email changes are unavailable until the forum operator configures the application key.';
        } elseif (!$this->mailer->isConfigured()) {
            $error = 'Email sending is not configured. Contact the forum operator before changing your email address.';
        } elseif ($this->domainVerifier?->blockedReason() !== null) {
            $error = 'Email sending is blocked until the forum operator verifies the sending domain. Please contact the operator.';
        }
        if ($error !== null) {
            throw new ValidationException(['new_email' => $error], ['new_email' => $email]);
        }
    }

    private function assertLinkedIdentity(User $actor, NormalizedIdentity $identity): void
    {
        $linked = $this->oauthIdentities->findByProvider($identity->provider, $identity->providerUserId);
        $configId = isset($linked['provider_config_id']) ? (int) $linked['provider_config_id'] : null;
        if ($linked === null || (int) $linked['user_id'] !== $actor->id()
            || (string) $linked['provider'] !== $identity->provider
            || (string) $linked['provider_user_id'] !== $identity->providerUserId
            || $configId !== $identity->providerConfigId) {
            throw new ValidationException(['current_password' => 'Use a sign-in provider already linked to this account to confirm the change.']);
        }
    }

    /** @return array<string,mixed>|null */
    private function findForActor(User $actor, string $rawToken): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $rawToken) !== 1 || $this->keys->active()->hmacKey() === '') {
            return null;
        }
        // The active key, then previous keys permitted to verify (ADR 0059). A row
        // attributed to one key is only accepted under that key.
        foreach ($this->keys->verificationKeys() as $key) {
            $row = $this->verifications->findValid($this->tokenHash($actor, $rawToken, $key), self::TYPE);
            if ($row !== null && (int) $row['user_id'] === $actor->id()
                && ($row['hmac_key_id'] === null || (string) $row['hmac_key_id'] === $key->hmacId)) {
                return $row;
            }
        }
        return null;
    }

    private function tokenHash(User $actor, string $rawToken, AppKey $key): string
    {
        // JSON preserves field boundaries and distinguishes an OAuth-only account
        // from a password-bearing one without persisting the credential snapshot.
        $bound = json_encode([self::TYPE, $actor->id(), $actor->email(), $actor->passwordHash(), $rawToken], JSON_THROW_ON_ERROR);
        return $key->hmac($bound);
    }

    /** @param array<string,mixed> $row @return array{id:int,new_email:string,expires_at:string} */
    private function publicVerification(array $row): array
    {
        return ['id' => (int) $row['id'], 'new_email' => (string) $row['new_email'], 'expires_at' => (string) $row['expires_at']];
    }

    private function ttlSeconds(): int
    {
        return max(300, min(86400, (int) $this->config->get('auth.email_change_ttl', 3600)));
    }

    private function sendVerification(string $email, string $rawToken, int $ttl): void
    {
        $url = rtrim((string) $this->config->get('app.url', ''), '/') . '/settings/account/email/confirm?token=' . $rawToken;
        $minutes = max(1, (int) ceil($ttl / 60));
        $name = $this->siteName();
        $text = "You requested a new email address for your {$name} account.\n\n"
            . "Sign in to the same account and confirm this address within {$minutes} minutes:\n{$url}\n\n"
            . 'Your current email address remains active until you confirm. If you did not request this change, ignore this email.';
        $html = '<p>You requested a new email address for your ' . htmlspecialchars($name, ENT_QUOTES) . ' account.</p>'
            . '<p>Sign in to the same account and <a href="' . htmlspecialchars($url, ENT_QUOTES) . '">confirm this email address</a> within ' . $minutes . ' minutes.</p>'
            . '<p>Your current email address remains active until you confirm. If you did not request this change, ignore this email.</p>';
        $this->mailer->send($email, 'Confirm your new ' . $name . ' email address', $text, $html);
    }

    private function siteName(): string
    {
        $fallback = (string) $this->config->get('app.name', 'RetroBoards');
        try {
            return $this->settings?->getString('site_name', $fallback) ?? $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
