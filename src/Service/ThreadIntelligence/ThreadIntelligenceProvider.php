<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Replaceable generation seam (DECISIONS §2, ADR 0019). Callers depend only on
 * the request/result data classes — never on provider response types. Throws
 * ThreadIntelligenceProviderException with a safe code on every failure.
 */
interface ThreadIntelligenceProvider
{
    public function generate(ThreadIntelligenceRequest $request): ThreadIntelligenceResult;
}
