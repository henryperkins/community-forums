<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Replaceable HTTP seam (DECISIONS §2, ADR 0019). Implementations own the
 * fixed-host exchange, response-size cap, and safe error classification; the
 * production adapter accepts exactly /v1/responses and /v1/moderations and
 * never exposes the credential.
 */
interface OpenAiTransport
{
    /** @param array<string,mixed> $payload */
    public function post(string $path, array $payload, int $timeoutSeconds): OpenAiTransportResponse;
}
