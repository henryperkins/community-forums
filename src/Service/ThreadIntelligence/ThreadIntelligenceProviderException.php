<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;
use RuntimeException;

/**
 * The only exception the provider/moderation/validation boundary may throw.
 *
 * It exposes exactly a safe code, an optional bounded Retry-After, and whether
 * the failure blocks the provider site-wide (authentication / invalid model).
 * Its message is the safe code itself — never a response body, header, or
 * credential — so it can cross into logs and ledgers unredacted.
 */
final class ThreadIntelligenceProviderException extends RuntimeException
{
    public function __construct(
        private readonly string $safeCode,
        private readonly ?int $retryAfterSeconds = null,
        private readonly bool $blocksProvider = false,
    ) {
        if (!in_array($safeCode, ThreadIntelligenceFailureCode::ALL, true)) {
            throw new InvalidArgumentException('unknown thread-intelligence failure code');
        }
        if ($retryAfterSeconds !== null && $retryAfterSeconds < 0) {
            throw new InvalidArgumentException('retry-after must be nonnegative');
        }
        parent::__construct($safeCode);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function blocksProvider(): bool
    {
        return $this->blocksProvider;
    }
}
