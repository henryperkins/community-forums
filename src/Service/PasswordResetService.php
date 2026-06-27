<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\MailException;
use App\Mail\Mailer;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\PasswordHasher;

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
        private UserRepository $users,
        private VerificationRepository $verifications,
        private SessionRepository $sessions,
        private PasswordHasher $hasher,
        private Mailer $mailer,
        private Config $config,
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
        $userId = (int) $row['id'];

        // One live link at a time: retire any earlier outstanding tokens.
        $this->verifications->invalidateOutstanding($userId, self::TYPE);

        $rawToken = bin2hex(random_bytes(32));
        $ttl = $this->ttlSeconds();
        $this->verifications->create(
            $userId,
            self::TYPE,
            hash('sha256', $rawToken),
            gmdate('Y-m-d H:i:s', time() + $ttl),
        );

        $this->sendEmail((string) $row['email'], $rawToken, $ttl);
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
        return $this->verifications->findValid(hash('sha256', $rawToken), self::TYPE);
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

        $userId = (int) $verification['user_id'];

        // Consume first so a double-submit / link-reuse cannot reset twice.
        if ($this->verifications->markUsed((int) $verification['id']) === 0) {
            throw new ValidationException(['password' => 'This reset link has already been used. Please request a new one.'], []);
        }

        $this->users->updatePassword($userId, $this->hasher->hash($password));
        $this->verifications->invalidateOutstanding($userId, self::TYPE);
        // A reset implies possible compromise — drop every existing session so a
        // hijacked one cannot survive the password change.
        $this->sessions->revokeAllForUser($userId);

        $user = $this->users->findEntity($userId);
        if ($user === null) {
            throw new \RuntimeException('Account vanished during password reset.');
        }
        return $user;
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
        $appName = (string) $this->config->get('app.name', 'RetroBoards');

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
}
