<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Cloudflare Email Sending, over the REST API.
 *
 * The Workers binding (env.EMAIL.send) is the recommended path on Cloudflare,
 * but this app is PHP served by Apache inside a Container — it has no Worker
 * bindings — so the REST endpoint is the correct integration. The REST field
 * names differ from the binding's in ways that fail quietly if mixed up: `from`
 * takes `address` (not `email`) and the reply-to key is `reply_to` (not
 * `replyTo`).
 *
 * The sending domain must be onboarded first (`wrangler email sending enable
 * <domain>`); sending from a domain that is not onboarded is rejected by the
 * API, not silently dropped. See docs/runbooks/deployment-cloudflare.md §8.
 */
final class CloudflareMailer implements Mailer
{
    private const ENDPOINT = 'https://api.cloudflare.com/client/v4/accounts/%s/email/sending/send';

    public function __construct(
        private CloudflareTransport $transport,
        private string $accountId,
        private string $apiToken,
        private string $fromEmail,
        private string $fromName = '',
        private int $timeoutSeconds = 10,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->accountId !== ''
            && $this->apiToken !== ''
            && $this->fromEmail !== ''
            && str_contains($this->fromEmail, '@');
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string
    {
        if (!$this->isConfigured()) {
            throw new MailException(
                'Cloudflare Email Sending is not configured (needs MAIL_FROM, MAIL_CLOUDFLARE_ACCOUNT_ID and MAIL_CLOUDFLARE_API_TOKEN).',
            );
        }

        $payload = [
            'to' => $to,
            'from' => $this->fromName !== ''
                ? ['address' => $this->fromEmail, 'name' => $this->fromName]
                : $this->fromEmail,
            'subject' => $subject,
            // Always send both parts when we have them: some clients render only
            // text, and a text alternative measurably helps spam scoring.
            'text' => $textBody,
        ];
        if ($htmlBody !== null && $htmlBody !== '') {
            $payload['html'] = $htmlBody;
        }

        $result = $this->transport->post(
            sprintf(self::ENDPOINT, rawurlencode($this->accountId)),
            $this->apiToken,
            $payload,
            $this->timeoutSeconds,
        );

        return $this->interpret($to, $result['status'], $result['body']);
    }

    /**
     * Turn an API response into a message id, or a MailException the email
     * worker can record and retry. Every failure path is a throw — the worker
     * marks the row failed on MailException, so a silent success here would
     * strand a member waiting on a verification link they never receive.
     */
    private function interpret(string $to, int $status, string $body): string
    {
        if ($status === 0) {
            throw new MailException('Cloudflare Email Sending was unreachable (' . $body . ').');
        }

        $decoded = json_decode($body, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status !== 200 || ($decoded['success'] ?? false) !== true) {
            throw new MailException(sprintf(
                'Cloudflare Email Sending rejected the message for %s (HTTP %d): %s',
                $to,
                $status,
                $this->errorText($decoded),
            ));
        }

        // 200 + success:true still carries per-recipient outcomes. A recipient in
        // permanent_bounces was NOT sent to, and retrying it only damages sender
        // reputation — surface it rather than reporting a send that never landed.
        $result = is_array($decoded['result'] ?? null) ? $decoded['result'] : [];
        $delivered = is_array($result['delivered'] ?? null) ? $result['delivered'] : [];
        $queued = is_array($result['queued'] ?? null) ? $result['queued'] : [];
        $bounced = is_array($result['permanent_bounces'] ?? null) ? $result['permanent_bounces'] : [];

        if ($delivered === [] && $queued === []) {
            throw new MailException($bounced !== []
                ? 'Cloudflare Email Sending permanently bounced the message for ' . $to . '.'
                : 'Cloudflare Email Sending accepted nothing for ' . $to . '.');
        }

        // The REST API reports delivery state rather than a message id, so the
        // id in email_deliveries is ours — the same thing SendmailMailer does.
        return 'cf-' . bin2hex(random_bytes(12));
    }

    /** @param array<string,mixed> $decoded */
    private function errorText(array $decoded): string
    {
        $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];
        $messages = [];
        foreach ($errors as $error) {
            if (is_array($error) && isset($error['message'])) {
                $messages[] = isset($error['code'])
                    ? '[' . $error['code'] . '] ' . (string) $error['message']
                    : (string) $error['message'];
            }
        }
        return $messages === [] ? 'no error detail returned' : implode('; ', $messages);
    }
}
