<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * In-memory mailer for tests and local dev: captures every message instead of
 * sending it. Configured by default so worker/fan-out paths exercise the full
 * send flow.
 */
final class ArrayMailer implements Mailer
{
    /** @var array<int,array{to:string,subject:string,text:string,html:?string,message_id:string}> */
    public array $sent = [];

    private int $seq = 0;

    /** When true, the next send() throws — used to test worker retry/failure handling. */
    public bool $failNext = false;

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string
    {
        if ($this->failNext) {
            $this->failNext = false;
            throw new MailException('Simulated transport failure.');
        }
        $id = 'array-' . (++$this->seq);
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'text' => $textBody, 'html' => $htmlBody, 'message_id' => $id];
        return $id;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /** @return array<int,array{to:string,subject:string,text:string,html:?string,message_id:string}> */
    public function to(string $email): array
    {
        return array_values(array_filter($this->sent, static fn (array $m): bool => $m['to'] === $email));
    }

    public function count(): int
    {
        return count($this->sent);
    }
}
