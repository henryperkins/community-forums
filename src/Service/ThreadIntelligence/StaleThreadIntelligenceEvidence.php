<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;
use RuntimeException;

/** A validated response no longer describes the locked, current public source. */
final class StaleThreadIntelligenceEvidence extends RuntimeException
{
    public readonly string $reason;

    public function __construct(string $reason)
    {
        if ($reason === '') {
            throw new InvalidArgumentException('stale evidence requires a bounded reason code');
        }
        $this->reason = substr($reason, 0, 64);
        parent::__construct($this->reason);
    }
}
