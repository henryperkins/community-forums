<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * The HTTP hop CloudflareMailer makes, isolated so the mailer's payload shaping
 * and response handling are testable without a network.
 */
interface CloudflareTransport
{
    /**
     * POST a JSON payload with a bearer token.
     *
     * @param array<string,mixed> $payload
     * @return array{status:int, body:string} status 0 means the request never completed
     */
    public function post(string $url, string $token, array $payload, int $timeoutSeconds): array;
}
