<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Mail\MailException;
use App\Mail\Mailer;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use Throwable;

/**
 * Registration email verification (PHASE_2_PLAN §3 Gate A: "registration
 * email-verification"). Shares the token mechanics of password reset: a raw
 * token is emailed, only its SHA-256 is stored, time-boxed via the
 * `verifications` table (type='email_verify').
 *
 * Verification is a SOFT signal in Phase 2 — it does not gate writes (the
 * account-state WriteGate is unchanged). Confirming an address sets
 * users.email_verified_at (idempotent) and re-runs auto-badge evaluation so the
 * "welcome" badge is granted; it is the seam for any future verified-only
 * gating.
 */
final class EmailVerificationService
{
    private const TYPE = 'email_verify';

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private VerificationRepository $verifications,
        private BadgeService $badges,
        private Mailer $mailer,
        private Config $config,
        private ?SettingRepository $settings = null,
    ) {
    }

    /** Issue (or re-issue) a verification link for an account, retiring any earlier one. */
    public function issue(int $userId, string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $ttl = $this->ttlSeconds();
        $recipient = $this->db->transaction(function () use ($userId, $email, $rawToken, $ttl): ?string {
            $fresh = $this->users->findForUpdate($userId);
            if ($fresh === null || strcasecmp((string) $fresh['email'], $email) !== 0) {
                return null;
            }
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
     * The verification row for a raw token, or null if malformed/unknown/used/expired.
     *
     * @return array<string,mixed>|null
     */
    public function findValid(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
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
     * Confirm only a still-valid proof of the current contact. The account lock
     * serializes issuance/consumption with contact changes and password resets.
     *
     * @param array<string,mixed> $verification
     */
    public function verify(array $verification): void
    {
        $this->db->transaction(function () use ($verification): void {
            $userId = (int) $verification['user_id'];
            $fresh = $this->users->findForUpdate($userId);
            $token = $fresh === null ? null : $this->verifications->findValidForUpdate((string) $verification['token_hash'], self::TYPE, $userId);
            if ($token === null || (int) $token['id'] !== (int) $verification['id']
                || ($token['new_email'] !== null && strcasecmp((string) $token['new_email'], (string) $fresh['email']) !== 0)
                || $this->verifications->consumeValid((int) $token['id'], $userId, self::TYPE, (string) $token['token_hash']) !== 1) {
                throw new ValidationException(['token' => 'This verification link is invalid or has expired. Please request a new one.']);
            }
            $this->users->markEmailVerified($userId);
            // The "welcome" badge requires a verified email — re-run auto-awards.
            $this->badges->evaluateForUser($userId);
        });
    }

    private function ttlSeconds(): int
    {
        return max(300, (int) $this->config->get('auth.email_verify_ttl', 86400));
    }

    private function sendEmail(string $to, string $rawToken, int $ttl): void
    {
        // Fail closed when no transport is configured; the user can resend later.
        if (!$this->mailer->isConfigured()) {
            return;
        }

        $base = rtrim((string) $this->config->get('app.url', ''), '/');
        $link = $base . '/verify?token=' . $rawToken;
        $hours = max(1, (int) round($ttl / 3600));
        $appName = $this->siteName();

        $subject = "Confirm your {$appName} email address";
        $text = "Welcome to {$appName}! Please confirm your email address.\n\n"
            . "Open this link within {$hours} hours to verify:\n{$link}\n\n"
            . "If you didn't create an account, you can safely ignore this email.";
        $safeLink = htmlspecialchars($link, ENT_QUOTES);
        $safeApp = htmlspecialchars($appName, ENT_QUOTES);
        $html = "<p>Welcome to {$safeApp}! Please confirm your email address.</p>"
            . '<p><a href="' . $safeLink . '">Verify my email</a> (valid for ' . $hours . ' hours).</p>'
            . "<p>If you didn't create an account, you can safely ignore this email.</p>";

        try {
            $this->mailer->send($to, $subject, $text, $html);
        } catch (MailException) {
            // Best-effort; resend is available from account settings.
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
