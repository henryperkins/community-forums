<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Replaceable output-moderation seam (DECISIONS §2, ADR 0019): the final
 * automatic-publication safety check, run after schema validation and before
 * publication. Transport failure throws (fails closed for the attempt); a
 * flagged verdict is returned, never published.
 */
interface ThreadIntelligenceOutputModerator
{
    public function moderate(string $text): ThreadIntelligenceModerationResult;
}
