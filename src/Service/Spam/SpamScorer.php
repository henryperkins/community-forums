<?php

declare(strict_types=1);

namespace App\Service\Spam;

use App\Domain\User;

/**
 * Spam-scoring provider seam (P3-05). The central {@see \App\Service\AntiAbuseService}
 * consults the bound scorer as one reviewable rule alongside the word/link/
 * duplicate/flood checks; its score is clamped to the operator's mode exactly
 * like every other rule, so enabling a provider never escalates beyond the
 * chosen posture and every action stays auditable.
 *
 * Gate A ships only the seam plus a no-op default ({@see NullSpamScorer}). A
 * first-party or external provider is Gate B / P3-13 and is enabled by rebinding
 * this interface in the container — no anti-abuse engine changes required.
 *
 * Implementations MUST be side-effect free and fail safe: return null to abstain,
 * and never perform punitive/destructive actions — the allow/flag/hold/block
 * decision stays centralized in AntiAbuseService. (The service also guards calls
 * so a thrown exception is treated as an abstention, but providers should not
 * rely on that.)
 */
interface SpamScorer
{
    /**
     * Score a candidate submission.
     *
     * @param 'thread'|'reply'|'dm' $context
     * @return SpamVerdict|null null = no opinion (abstain)
     */
    public function score(User $user, string $context, string $text): ?SpamVerdict;
}
