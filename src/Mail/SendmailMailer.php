<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Baseline transport using PHP's mail() (local sendmail/SMTP relay). This is the
 * default real driver until a provider (Postmark/SES/Resend) is wired behind the
 * same interface. isConfigured() is false until a From address is set, so the
 * notification layer fails closed (in-app keeps working).
 */
final class SendmailMailer implements Mailer
{
    public function __construct(
        private string $fromEmail,
        private string $fromName = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->fromEmail !== '' && str_contains($this->fromEmail, '@');
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string
    {
        if (!$this->isConfigured()) {
            throw new MailException('Mail is not configured (no From address).');
        }

        $boundary = 'rb-' . bin2hex(random_bytes(8));
        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $this->domain() . '>';
        $from = $this->fromName !== '' ? sprintf('%s <%s>', $this->fromName, $this->fromEmail) : $this->fromEmail;

        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Message-ID: ' . $messageId,
        ];

        if ($htmlBody !== null && $htmlBody !== '') {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--$boundary\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $textBody . "\r\n\r\n"
                . "--$boundary\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n\r\n"
                . "--$boundary--";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = $textBody;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        if ($ok === false) {
            throw new MailException('mail() rejected the message for ' . $to);
        }
        return $messageId;
    }

    private function domain(): string
    {
        $at = strrpos($this->fromEmail, '@');
        return $at === false ? 'localhost' : substr($this->fromEmail, $at + 1);
    }
}
