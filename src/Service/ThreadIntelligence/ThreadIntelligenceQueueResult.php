<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Immutable curator-refresh result consumed verbatim by HTTP/admin surfaces. */
final readonly class ThreadIntelligenceQueueResult
{
    public bool $queued;
    public string $code;
    public string $message;
    public ?DateTimeImmutable $nextEligibleAt;

    public function __construct(
        bool $queued,
        string $code,
        string $message,
        ?DateTimeImmutable $nextEligibleAt = null,
    ) {
        if ($code === '' || $message === '') {
            throw new InvalidArgumentException('queue decisions require a code and message');
        }
        $this->queued = $queued;
        $this->code = $code;
        $this->message = $message;
        $this->nextEligibleAt = $nextEligibleAt?->setTimezone(new DateTimeZone('UTC'));
    }
}
