<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * The transport hands back only a status code, the decoded JSON document (or
 * null when the body was not valid JSON), and a bounded Retry-After. The raw
 * body string is not carried past decoding.
 */
final readonly class OpenAiTransportResponse
{
    /** @param array<string,mixed>|null $json */
    public function __construct(
        public int $statusCode,
        public ?array $json,
        public ?int $retryAfterSeconds = null,
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException('implausible HTTP status code');
        }
        if ($retryAfterSeconds !== null && $retryAfterSeconds < 0) {
            throw new InvalidArgumentException('retry-after must be nonnegative');
        }
    }
}
