<?php

declare(strict_types=1);

namespace App\Service\Spam;

/**
 * A spam scorer's opinion on a candidate submission (P3-05). `score` is clamped
 * to [0.0, 1.0] (0 = ham, 1 = spam); `label` is a short provider/reason tag
 * recorded in the anti-abuse audit trail.
 */
final class SpamVerdict
{
    public readonly float $score;
    public readonly string $label;

    public function __construct(float $score, string $label = 'spam')
    {
        $this->score = max(0.0, min(1.0, $score));
        $this->label = $label !== '' ? $label : 'spam';
    }
}
