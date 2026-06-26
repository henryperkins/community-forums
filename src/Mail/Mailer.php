<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Swappable mail transport (DECISIONS §2: "SMTP behind a Mailer interface",
 * swap to Postmark/SES/Resend later). Implementations send one message and
 * return a provider message id, or throw MailException on failure so the worker
 * can record the row as failed and retry.
 */
interface Mailer
{
    /**
     * @return string provider message id
     * @throws MailException
     */
    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string;

    /**
     * Whether a real sending path is configured. The notification layer fails
     * closed when this is false — it skips email but still delivers in-app
     * (PHASE_2_PLAN Milestone 2 exit gate).
     */
    public function isConfigured(): bool;
}
