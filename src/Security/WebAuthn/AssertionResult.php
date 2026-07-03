<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/** Verified assertion output. Counter anomalies are signals, not refusals. */
final class AssertionResult
{
    public function __construct(
        public readonly bool $userVerified,
        public readonly int $signCount,
        public readonly bool $counterAnomaly,
    ) {
    }
}
