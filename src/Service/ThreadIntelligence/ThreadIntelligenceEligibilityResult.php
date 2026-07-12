<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Immutable, shared eligibility decision for enqueue, worker, and HTTP paths. */
final readonly class ThreadIntelligenceEligibilityResult
{
    public bool $eligible;
    public string $code;
    public string $message;
    public ?DateTimeImmutable $nextEligibleAt;

    public function __construct(
        bool $eligible,
        string $code,
        string $message,
        ?DateTimeImmutable $nextEligibleAt = null,
    ) {
        if ($code === '' || $message === '') {
            throw new InvalidArgumentException('eligibility decisions require a code and message');
        }
        $this->eligible = $eligible;
        $this->code = $code;
        $this->message = $message;
        $this->nextEligibleAt = $nextEligibleAt?->setTimezone(new DateTimeZone('UTC'));
    }
}
