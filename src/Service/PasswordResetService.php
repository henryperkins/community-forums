<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\MailException;
use App\Mail\Mailer;
use App\Repository\SettingRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\PasswordHasher;
use Throwable;

/**
 * Forgotten-password recovery (PHASE_2_PLAN §3 Gate A: "Account recovery").
 *
 * A raw random token is emailed; only its SHA-256 hash is stored in
 * `verifications`. Tokens are single-use and time-boxed. Requesting a reset is
 * deliberately indistinguishable for known vs unknown emails (no account
 * enumeration — the controller returns the same response either way and this
 * service never signals which case occurred). Completing a reset rotates the
 * password and revokes every existing session, since a reset implies the old
 * credentials may be compromised; the user then signs in fresh.
 */
final class PasswordResetService
{
    private const TYPE = 'password_reset';

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private VerificationRepository $verifications,
        private SessionRepository $sessions,
        private PasswordHasher $hasher,
        private Mailer $mailer,
        private Config $config,
        private ?SettingRepository $settings = null,
    ) {
    }

    /**
     * Issue a reset link for $email if it belongs to an account. Silent (returns
     * void, never throws on an unknown address) so callers cannot leak existence.
     */
    public function request(string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }
        $row = $this->users->findByEmail($email);
        if ($row === null) {
            return;
        }
        $rawToken = bin2hex(random_bytes(32));
        $ttl = $this->ttlSeconds();
        $recipient = $this->db->transaction(function () use ($row, $email, $rawToken, $ttl): ?string {
            // Email confirmation locks the account before touching its tokens.
            // Recheck the address after waiting for that same lock, rather than
            // issuing recovery mail from an earlier contact lookup.
            $fresh = $this->users->findForUpdate((int) $row['id']);
            if ($fresh === null || strcasecmp((string) $fresh['email'], $email) !== 0) {
                return null;
            }
            $userId = (int) $fresh['id'];
            $this->verifications->invalidateOutstanding($userId, self::TYPE);
            $this->verifications->create($userId, self::TYPE, hash('sha256', $rawToken),
                gmdate('Y-m-d H:i:s', time() + $ttl), (string) $fresh['email']);
            return (string) $fresh['email'];
        });

        if ($recipient !== null) {
            $this->sendEmail($recipient, $rawToken, $ttl);
        }
    }

    /**
     * The verification row for a raw token, or null if it is malformed, unknown,
     * already used, or expired.
     *
     * @return array<string,mixed>|null
     */
    public function findValid(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        // Tokens are 64 lowercase hex chars (bin2hex of 32 bytes). Reject anything
        // else before hitting the database.
        if (strlen($rawToken) !== 64 || ctype_xdigit($rawToken) === false) {
            return null;
        }
        $row = $this->verifications->findValid(hash('sha256', $rawToken), self::TYPE);
        if ($row !== null && $row['new_email'] !== null) {
            $user = $this->users->find((int) $row['user_id']);
            if ($user === null || strcasecmp((string) $user['email'], (string) $row['new_email']) !== 0) {
                return null;
            }
        }
        return $row;
    }

    /**
     * Apply a validated reset: set the new password, consume the token, retire any
     * sibling tokens, and revoke all sessions. The caller must pass a row from
     * findValid().
     *
     * @param array<string,mixed> $verification
     * @throws ValidationException on a weak/mismatched password or an already-used token
     */
    public function reset(array $verification, string $password, string $confirm): User
    {
        $min = (int) $this->config->get('limits.password_min', 8);
        $errors = [];
        if (strlen($password) < $min) {
            $errors['password'] = "Password must be at least {$min} characters.";
        } elseif ($confirm !== '' && $password !== $confirm) {
            $errors['password_confirm'] = 'The passwords do not match.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, []);
        }

        $passwordHash = $this->hasher->hash($password);
        return $this->db->transaction(function () use ($verification, $passwordHash): User {
            $userId = (int) $verification['user_id'];
            $fresh = $this->users->findForUpdate($userId);
            $token = $fresh === null ? null : $this->verifications->findValidForUpdate((string) $verification['token_hash'], self::TYPE, $userId);
            if ($token === null || (int) $token['id'] !== (int) $verification['id']
                || ($token['new_email'] !== null && strcasecmp((string) $token['new_email'], (string) $fresh['email']) !== 0)
                || $this->verifications->consumeValid((int) $token['id'], $userId, self::TYPE, (string) $token['token_hash']) !== 1) {
                throw new ValidationException(['password' => 'This reset link is invalid or has expired. Please request a new one.']);
            }

            $this->users->updatePassword($userId, $passwordHash);
            $this->verifications->invalidateOutstanding($userId, self::TYPE);
            // A reset implies possible compromise — drop every existing session.
            $this->sessions->revokeAllForUser($userId);
            return User::fromRow(array_replace($fresh, ['password_hash' => $passwordHash]));
        });
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) $this->config->get('auth.password_reset_ttl', 3600));
    }

    private function sendEmail(string $to, string $rawToken, int $ttl): void
    {
        // Fail closed when no transport is configured (mirrors the notification
        // layer): we simply can't send, and we still never reveal that upstream.
        if (!$this->mailer->isConfigured()) {
            return;
        }

        $base = rtrim((string) $this->config->get('app.url', ''), '/');
        $link = $base . '/reset?token=' . $rawToken;
        $minutes = max(1, (int) round($ttl / 60));
        $appName = $this->siteName();

        $subject = "Reset your {$appName} password";
        $text = "We received a request to reset your password.\n\n"
            . "Use this link within {$minutes} minutes to choose a new password:\n{$link}\n\n"
            . "If you didn't request this, you can safely ignore this email — your password won't change.";
        $safeLink = htmlspecialchars($link, ENT_QUOTES);
        $html = '<p>We received a request to reset your password.</p>'
            . '<p><a href="' . $safeLink . '">Choose a new password</a> (valid for ' . $minutes . ' minutes).</p>'
            . "<p>If you didn't request this, you can safely ignore this email — your password won't change.</p>";

        try {
            $this->mailer->send($to, $subject, $text, $html);
        } catch (MailException) {
            // Never surface delivery status to the requester (anti-enumeration).
        }
    }

    private function siteName(): string
    {
        $fallback = (string) $this->config->get('app.name', 'RetroBoards');
        if ($this->settings === null) {
            return $fallback;
        }
        try {
            return $this->settings->getString('site_name', $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
