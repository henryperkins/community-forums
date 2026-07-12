<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * The complete safe failure-code taxonomy for Thread Intelligence (ADR 0019).
 * These strings are the ONLY provider-adjacent error detail that may reach
 * logs, the jobs row, the generation ledger, or the admin console.
 */
final class ThreadIntelligenceFailureCode
{
    public const TRANSPORT = 'transport';
    public const RATE_LIMITED = 'rate_limited';
    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const AUTHENTICATION = 'authentication';
    public const INVALID_MODEL = 'invalid_model';
    public const OUTPUT_TRUNCATED = 'output_truncated';
    public const SCHEMA_INVALID = 'schema_invalid';
    public const VALIDATION_FAILED = 'validation_failed';
    public const MODERATION_TRANSPORT = 'moderation_transport';
    public const MODERATION_FLAGGED = 'moderation_flagged';
    public const STALE_EVIDENCE = 'stale_evidence';
    public const EVIDENCE_TOO_LARGE = 'evidence_too_large';

    public const ALL = [
        self::TRANSPORT,
        self::RATE_LIMITED,
        self::PROVIDER_UNAVAILABLE,
        self::AUTHENTICATION,
        self::INVALID_MODEL,
        self::OUTPUT_TRUNCATED,
        self::SCHEMA_INVALID,
        self::VALIDATION_FAILED,
        self::MODERATION_TRANSPORT,
        self::MODERATION_FLAGGED,
        self::STALE_EVIDENCE,
        self::EVIDENCE_TOO_LARGE,
    ];

    private function __construct()
    {
    }
}
